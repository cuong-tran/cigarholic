#!/usr/bin/env bash
# Per-site setup on the Cigarholic VPS (FND-08, OPS-01, OPS-03, OPS-09). Idempotent.
#
# Usage (as root, after provision.sh):
#   site.sh <staging|production>
#
#   STAGING_BASIC_AUTH_USER / STAGING_BASIC_AUTH_PASSWORD   required the first time for staging
#   LETSENCRYPT_EMAIL        optional; certbot expiry notices (otherwise registered without email)
#   SITE_FORCE_CERTBOT=1     request the certificate even when DNS does not point here
#   INFRA_DIR                the repository's infra/ directory (see provision.sh); .env.example is
#                            expected next to it (INFRA_DIR/../.env.example)
#
# Creates: system user cigarholic-<site>, /var/www/<site>/{releases,shared,current} owned by the
# deploy user, shared/.env from .env.example (random DB password and salts; only if missing),
# MariaDB database and user, PHP-FPM pool, Nginx vhost from infra/server/nginx/*.tpl, TLS (Let's
# Encrypt HTTP-01 when the hostname resolves to this server, else a self-signed placeholder).
# WordPress itself is installed by the deploy workflow; `wp` is never run here.
set -euo pipefail

SITE="${1:-}"
case "$SITE" in
  staging)
    HOST=staging.cigarholic.com
    REDIRECT_HOSTS=()
    PM_MAX=10; PM_START=2; PM_MIN_SPARE=2; PM_MAX_SPARE=4
    ;;
  production)
    HOST=cigarholic.com
    REDIRECT_HOSTS=(www.cigarholic.com max2lab.com www.max2lab.com)
    PM_MAX=24; PM_START=6; PM_MIN_SPARE=4; PM_MAX_SPARE=10
    ;;
  *) echo "usage: site.sh <staging|production>" >&2; exit 2 ;;
esac

PHP_VERSION=8.5
SITE_USER="cigarholic-$SITE"
ROOT="/var/www/$SITE"
DB_NAME="cigarholic_$SITE"
DB_USER="cigarholic_$SITE"
PHP_SOCKET="/run/php/cigarholic-$SITE.sock"
CERT_DIR="/etc/nginx/cigarholic/$SITE"
SELF_SIGNED_DIR="/etc/ssl/cigarholic/$SITE"

log()  { printf '[site:%s] %s\n' "$SITE" "$*"; }
warn() { printf '[site:%s] WARNING: %s\n' "$SITE" "$*" >&2; }
die()  { printf '[site:%s] ERROR: %s\n' "$SITE" "$*" >&2; exit 1; }

[ "$(id -u)" -eq 0 ] || die "run as root"
id deploy >/dev/null 2>&1 || die "the deploy user does not exist: run provision.sh first"

if [ -z "${INFRA_DIR:-}" ]; then
  if [ -f "$0" ] && [ "$(basename "$0")" = "site.sh" ]; then
    INFRA_DIR="$(cd "$(dirname "$(readlink -f "$0")")/.." && pwd)"
  else
    for candidate in "$HOME/cigarholic-infra/infra" /root/cigarholic-infra/infra /opt/cigarholic/infra; do
      [ -d "$candidate/server" ] && INFRA_DIR="$candidate" && break
    done
  fi
fi
[ -n "${INFRA_DIR:-}" ] && [ -d "$INFRA_DIR/server/nginx" ] || die "INFRA_DIR not found; upload the repository's infra/ directory and set INFRA_DIR"
SERVER_DIR="$INFRA_DIR/server"
ENV_EXAMPLE="$INFRA_DIR/../.env.example"
[ -f "$ENV_EXAMPLE" ] || die ".env.example not found at $ENV_EXAMPLE"

HAS_SYSTEMD=1
if [ "${PROVISION_NO_SYSTEMD:-0}" = "1" ] || [ ! -d /run/systemd/system ] || ! command -v systemctl >/dev/null 2>&1; then
  HAS_SYSTEMD=0
  log "no systemd detected: configuration is written, services are not reloaded"
fi
svc() { if [ "$HAS_SYSTEMD" = 1 ]; then systemctl "$@"; else log "skip (no systemd): systemctl $*"; fi; }

# Hosts without IPv6 (some containers, some VPS plans) cannot bind [::]; drop those listen lines.
HAS_IPV6=0
[ -s /proc/net/if_inet6 ] && HAS_IPV6=1
[ "$HAS_IPV6" = 1 ] || log "no IPv6 on this host: IPv6 listen directives are left out"

# render <template> <destination> [mode]: replaces @@VARS@@ and installs when the content differs.
render() {
  local tpl=$1 dst=$2 mode=${3:-0644} tmp
  tmp=$(mktemp)
  sed -e "s#@@SITE@@#$SITE#g" -e "s#@@HOST@@#$HOST#g" -e "s#@@ROOT@@#$ROOT#g" \
      -e "s#@@USER@@#$SITE_USER#g" -e "s#@@PHP_SOCKET@@#$PHP_SOCKET#g" -e "s#@@CERT_DIR@@#$CERT_DIR#g" \
      -e "s#@@PM_MAX@@#$PM_MAX#g" -e "s#@@PM_START@@#$PM_START#g" \
      -e "s#@@PM_MIN_SPARE@@#$PM_MIN_SPARE#g" -e "s#@@PM_MAX_SPARE@@#$PM_MAX_SPARE#g" \
      "$tpl" >"$tmp"
  [ "$HAS_IPV6" = 1 ] || sed -i '/listen \[::\]/d' "$tmp"
  if [ -f "$dst" ] && cmp -s "$tmp" "$dst"; then rm -f "$tmp"; return 1; fi
  install -D -m "$mode" "$tmp" "$dst"
  rm -f "$tmp"
  log "wrote $dst"
  return 0
}

# random_secret <length> [set]: printable, no quotes, backslashes, dollars or spaces. Reads a fixed
# amount of urandom so no stage of the pipe is closed early (pipefail would turn that into exit 141).
random_secret() {
  head -c 8192 /dev/urandom | tr -dc "${2:-A-Za-z0-9!#%^&*()_=+[]{}:,.<>?-}" | cut -c1-"$1"
}

# =================================================================================================
# 1. System user and directories (owner deploy, group = site user, ACLs for shared writable dirs)
# =================================================================================================
if ! id "$SITE_USER" >/dev/null 2>&1; then
  useradd --system --user-group --home-dir "$ROOT" --no-create-home --shell /usr/sbin/nologin "$SITE_USER"
  log "created system user $SITE_USER"
fi
[[ " $(id -nG deploy) " == *" $SITE_USER "* ]] || usermod -aG "$SITE_USER" deploy

install -d -m 0755 -o deploy -g "$SITE_USER" "$ROOT" "$ROOT/releases" "$ROOT/shared" "$ROOT/shared/web" "$ROOT/shared/web/app"
install -d -m 0755 -o deploy -g "$SITE_USER" "$ROOT/letsencrypt"
for d in shared/web/app/uploads shared/logs shared/sessions; do
  install -d -m 2775 -o deploy -g "$SITE_USER" "$ROOT/$d"
  setfacl -m "u:deploy:rwX,u:$SITE_USER:rwX,g:$SITE_USER:rwX" "$ROOT/$d"
  setfacl -d -m "u:deploy:rwX,u:$SITE_USER:rwX,g:$SITE_USER:rwX" "$ROOT/$d"
done
install -d -m 2770 -o deploy -g "$SITE_USER" "$ROOT/shared/backups"
chmod o-rwx "$ROOT/shared/sessions"
touch "$ROOT/shared/logs/php-error.log" "$ROOT/shared/logs/php-slow.log" "$ROOT/shared/logs/debug.log"
chown deploy:"$SITE_USER" "$ROOT"/shared/logs/*.log
chmod 0664 "$ROOT"/shared/logs/*.log

# =================================================================================================
# 2. shared/.env from the repository's .env.example (only when missing)
# =================================================================================================
ENV_FILE="$ROOT/shared/.env"
if [ ! -f "$ENV_FILE" ]; then
  DB_PASSWORD=$(random_secret 32 'A-Za-z0-9')
  declare -A VALUES=(
    [DB_NAME]="$DB_NAME" [DB_USER]="$DB_USER" [DB_PASSWORD]="$DB_PASSWORD" [DB_HOST]="localhost"
    [WP_ENV]="$SITE" [WP_HOME]="https://$HOST"
    [WP_REDIS_HOST]="127.0.0.1" [WP_REDIS_PORT]="6379" [WP_REDIS_PREFIX]="cigarholic_$SITE"
    [SMTP_HOST]="" [SMTP_PORT]="" [POSTMARK_SERVER_TOKEN]=""
    [TURNSTILE_SITE_KEY]="" [TURNSTILE_SECRET_KEY]="" [GA4_MEASUREMENT_ID]="" [CIGARHOLIC_FEATURES]=""
  )
  for k in AUTH_KEY SECURE_AUTH_KEY LOGGED_IN_KEY NONCE_KEY AUTH_SALT SECURE_AUTH_SALT LOGGED_IN_SALT NONCE_SALT; do
    VALUES[$k]=$(random_secret 64)
  done
  tmp=$(mktemp)
  {
    echo "# Generated by infra/server/site.sh for $SITE on $(date -u +%Y-%m-%dT%H:%M:%SZ) from .env.example."
    echo "# Application keys are upserted by the deploy workflow (infra/server/env-upsert.sh). Never edit by hand."
    while IFS= read -r line || [ -n "$line" ]; do
      # Known keys get the site's value; every other line (comments, WP_SITEURL="${WP_HOME}/wp",
      # commented-out optional overrides) is copied as is.
      if [[ "$line" =~ ^([A-Z0-9_]+)=(.*)$ ]] && [ -n "${VALUES[${BASH_REMATCH[1]}]+x}" ]; then
        printf "%s='%s'\n" "${BASH_REMATCH[1]}" "${VALUES[${BASH_REMATCH[1]}]}"
      else
        printf '%s\n' "$line"
      fi
    done <"$ENV_EXAMPLE"
  } >"$tmp"
  install -m 0640 -o deploy -g "$SITE_USER" "$tmp" "$ENV_FILE"
  rm -f "$tmp"
  log "generated $ENV_FILE (database password and salts are random; keys arrive with the first deploy)"
else
  chown deploy:"$SITE_USER" "$ENV_FILE"
  chmod 0640 "$ENV_FILE"
  DB_PASSWORD=$(sed -n "s/^DB_PASSWORD='\(.*\)'$/\1/p" "$ENV_FILE")
  [ -n "$DB_PASSWORD" ] || die "DB_PASSWORD missing from $ENV_FILE"
  log "$ENV_FILE exists, kept"
fi

# =================================================================================================
# 3. MariaDB database and user (re-asserted from .env on every run)
# =================================================================================================
if mariadb-admin ping --silent >/dev/null 2>&1; then
  mariadb <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL
  log "database $DB_NAME and user $DB_USER@localhost in place"
else
  warn "MariaDB is not running: database and user not created; re-run site.sh $SITE once it is"
fi

# =================================================================================================
# 4. PHP-FPM pool
# =================================================================================================
render "$SERVER_DIR/php/pool.conf.tpl" "/etc/php/$PHP_VERSION/fpm/pool.d/cigarholic-$SITE.conf" && POOL_CHANGED=1 || POOL_CHANGED=0
"php-fpm$PHP_VERSION" -t >/dev/null 2>&1 || die "php-fpm configuration test failed: $("php-fpm$PHP_VERSION" -t 2>&1 | tail -n 3)"
[ "$POOL_CHANGED" = 1 ] && svc reload "php$PHP_VERSION-fpm" || true

# =================================================================================================
# 5. TLS: self-signed placeholder first, so Nginx can always start
# =================================================================================================
install -d -m 0755 "$CERT_DIR"
if [ ! -f "$SELF_SIGNED_DIR/fullchain.pem" ]; then
  install -d -m 0700 "$SELF_SIGNED_DIR"
  openssl req -x509 -newkey rsa:2048 -nodes -days 3650 -subj "/CN=$HOST" \
    -keyout "$SELF_SIGNED_DIR/privkey.pem" -out "$SELF_SIGNED_DIR/fullchain.pem" >/dev/null 2>&1
  chmod 0600 "$SELF_SIGNED_DIR/privkey.pem"
  log "created a self-signed placeholder certificate for $HOST"
fi
if [ ! -e "$CERT_DIR/fullchain.pem" ]; then
  ln -sfn "$SELF_SIGNED_DIR/fullchain.pem" "$CERT_DIR/fullchain.pem"
  ln -sfn "$SELF_SIGNED_DIR/privkey.pem" "$CERT_DIR/privkey.pem"
fi

# =================================================================================================
# 6. Nginx: zones, per-site includes, vhost (+ production redirects)
# =================================================================================================
NGINX_CHANGED=0
render "$SERVER_DIR/nginx/http-zones.conf.tpl" "/etc/nginx/conf.d/cigarholic-$SITE.conf" && NGINX_CHANGED=1
install -d -m 0755 "/etc/nginx/cigarholic/$SITE"
install -d -m 0700 -o www-data -g www-data "/var/cache/nginx/cigarholic-$SITE"

HEADERS_TMP=$(mktemp); AUTH_TMP=$(mktemp)
if [ "$SITE" = staging ]; then
  HTPASSWD="/etc/nginx/cigarholic/$SITE/htpasswd"
  if [ -n "${STAGING_BASIC_AUTH_USER:-}" ] && [ -n "${STAGING_BASIC_AUTH_PASSWORD:-}" ]; then
    hash=$(printf '%s' "$STAGING_BASIC_AUTH_PASSWORD" | openssl passwd -apr1 -stdin)
    printf '%s:%s\n' "$STAGING_BASIC_AUTH_USER" "$hash" >"$HTPASSWD.tmp"
    chmod 0640 "$HTPASSWD.tmp"; chown root:www-data "$HTPASSWD.tmp"
    mv -f "$HTPASSWD.tmp" "$HTPASSWD"
    log "basic auth credentials written"
  elif [ ! -s "$HTPASSWD" ]; then
    die "staging needs STAGING_BASIC_AUTH_USER and STAGING_BASIC_AUTH_PASSWORD the first time"
  fi
  cat >"$HEADERS_TMP" <<'EOF'
# staging: never indexed (WordPress also sets DISALLOW_INDEXING)
add_header X-Robots-Tag "noindex, nofollow, noarchive" always;
EOF
  cat >"$AUTH_TMP" <<EOF
# staging: HTTP basic auth, disabled per request by the map in conf.d/cigarholic-common.conf
auth_basic \$cigarholic_staging_auth;
auth_basic_user_file $HTPASSWD;
EOF
else
  echo "# production: no extra headers" >"$HEADERS_TMP"
  echo "# production: no basic auth" >"$AUTH_TMP"
fi
for pair in "$HEADERS_TMP:headers.conf" "$AUTH_TMP:auth.conf"; do
  src=${pair%%:*}; name=${pair##*:}
  if [ ! -f "/etc/nginx/cigarholic/$SITE/$name" ] || ! cmp -s "$src" "/etc/nginx/cigarholic/$SITE/$name"; then
    install -m 0644 "$src" "/etc/nginx/cigarholic/$SITE/$name"; NGINX_CHANGED=1
  fi
  rm -f "$src"
done

render "$SERVER_DIR/nginx/site.conf.tpl" "/etc/nginx/sites-available/cigarholic-$SITE" && NGINX_CHANGED=1
ln -sfn "/etc/nginx/sites-available/cigarholic-$SITE" "/etc/nginx/sites-enabled/cigarholic-$SITE"
if [ "$SITE" = production ]; then
  render "$SERVER_DIR/nginx/redirects-production.conf.tpl" /etc/nginx/sites-available/cigarholic-production-redirects && NGINX_CHANGED=1
  ln -sfn /etc/nginx/sites-available/cigarholic-production-redirects /etc/nginx/sites-enabled/cigarholic-production-redirects
fi
touch "/var/log/nginx/cigarholic-$SITE.access.log" "/var/log/nginx/cigarholic-$SITE.error.log"

nginx -t >/dev/null 2>&1 || die "nginx configuration test failed: $(nginx -t 2>&1 | tail -n 3)"
if [ "$NGINX_CHANGED" = 1 ]; then
  if [ "$HAS_SYSTEMD" = 1 ] && systemctl is-active --quiet nginx; then systemctl reload nginx; else svc restart nginx || true; fi
fi

# =================================================================================================
# 7. Let's Encrypt when the hostnames resolve to this server (HTTP-01 through the acme location)
# =================================================================================================
local_ips=$(ip -4 -o addr show scope global 2>/dev/null | awk '{print $4}' | cut -d/ -f1 | sort -u)
resolving=()
for h in "$HOST" "${REDIRECT_HOSTS[@]}"; do
  answers=$(getent ahostsv4 "$h" 2>/dev/null | awk '{print $1}' | sort -u || true)
  if [ -n "$answers" ] && [ -n "$local_ips" ] && comm -12 <(echo "$answers") <(echo "$local_ips") | grep -q .; then
    resolving+=("$h")
  fi
done
if [ "${SITE_FORCE_CERTBOT:-0}" = 1 ]; then resolving=("$HOST" "${REDIRECT_HOSTS[@]}"); fi

if [ ${#resolving[@]} -eq 0 ]; then
  log "DNS: none of $HOST ${REDIRECT_HOSTS[*]:-} points at this server yet; keeping the self-signed placeholder."
  log "     Create the A records (docs/runbooks/server.md) and re-run site.sh $SITE to get a Let's Encrypt certificate."
elif [ "$HAS_SYSTEMD" = 0 ]; then
  log "skip (no systemd): certbot for ${resolving[*]}"
else
  [[ " ${resolving[*]} " == *" $HOST "* ]] || die "the primary host $HOST must resolve here before requesting a certificate"
  domain_args=()
  for h in "${resolving[@]}"; do domain_args+=(-d "$h"); done
  email_args=(--register-unsafely-without-email)
  [ -n "${LETSENCRYPT_EMAIL:-}" ] && email_args=(--email "$LETSENCRYPT_EMAIL" --no-eff-email)
  if certbot certonly --webroot -w "$ROOT/letsencrypt" --cert-name "cigarholic-$SITE" "${domain_args[@]}" \
      --non-interactive --agree-tos "${email_args[@]}" --keep-until-expiring --expand; then
    live="/etc/letsencrypt/live/cigarholic-$SITE"
    if [ "$(readlink -f "$CERT_DIR/fullchain.pem")" != "$(readlink -f "$live/fullchain.pem")" ]; then
      ln -sfn "$live/fullchain.pem" "$CERT_DIR/fullchain.pem"
      ln -sfn "$live/privkey.pem" "$CERT_DIR/privkey.pem"
      nginx -t >/dev/null && systemctl reload nginx
      log "Let's Encrypt certificate active for ${resolving[*]}"
    else
      log "Let's Encrypt certificate already active for ${resolving[*]}"
    fi
  else
    warn "certbot failed; the self-signed placeholder stays. Re-run site.sh $SITE after fixing DNS or port 80."
  fi
fi

# =================================================================================================
# 8. fail2ban picks up the new log files; logrotate is global (provision.sh)
# =================================================================================================
if [ "$HAS_SYSTEMD" = 1 ] && systemctl is-active --quiet fail2ban; then fail2ban-client reload >/dev/null 2>&1 || true; fi

log "done."
log "  user       $SITE_USER   root $ROOT   db $DB_NAME"
log "  php-fpm    $PHP_SOCKET"
log "  nginx      https://$HOST (${REDIRECT_HOSTS[*]:-no redirect hosts})"
log "  next       deploy workflow (Deployer creates releases/ and current/), then wp core install through wp-cli.yml"

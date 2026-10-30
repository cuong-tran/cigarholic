#!/usr/bin/env bash
# OPS-01/OPS-02/FND-07: create or update one site on a server prepared by bootstrap.sh. Run as root.
# Safe to re-run: existing database passwords, salts and credentials are kept.
#
#   bash server/add-site.sh --site cigarholic-staging --domain staging.example.com --env staging \
#     --email owner@example.com --deploy-key "ssh-ed25519 AAAA... github-actions"
#
#   --site NAME        Linux user, directory (/srv/www/NAME), database and PHP pool name
#   --domain DOMAIN    canonical hostname
#   --env ENV          staging or production (sets WP_ENV)
#   --email EMAIL      WordPress admin email and Let's Encrypt contact
#   --deploy-key KEY   SSH public key allowed to deploy as the site user (repeatable)
#   --alias DOMAIN     extra hostname that redirects to the canonical one, e.g. www (repeatable)
#   --no-tls           skip Let's Encrypt (the site still answers on 443 with a self-signed cert)
#   --http-auth-stdin  read "user:password" for HTTP basic auth from stdin (keeps it off the
#                      command line); the HTTP_AUTH environment variable works too. Staging always
#                      gets basic auth, with a generated password when neither is given.
#
# Generated passwords are written to /root/wp-credentials/NAME.txt (root-only).
set -euo pipefail
umask 022

PHP_VERSION="${PHP_VERSION:-8.3}"
SITE="" DOMAIN="" WP_ENV="" EMAIL="" TLS=1
DEPLOY_KEYS=() ALIASES=()

usage() { awk 'NR > 1 && !/^#/ { exit } NR > 1 { sub(/^# ?/, ""); print }' "$0"; exit "${1:-0}"; }
log() { printf '\n==> %s\n' "$*"; }
warn() { printf 'WARNING: %s\n' "$*" >&2; }
die() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }
random() { openssl rand -hex "${1:-24}"; }

while [ $# -gt 0 ]; do
  case "$1" in
    --site) SITE="$2"; shift 2 ;;
    --domain) DOMAIN="$2"; shift 2 ;;
    --env) WP_ENV="$2"; shift 2 ;;
    --email) EMAIL="$2"; shift 2 ;;
    --deploy-key) DEPLOY_KEYS+=("$2"); shift 2 ;;
    --alias) ALIASES+=("$2"); shift 2 ;;
    --no-tls) TLS=0; shift ;;
    --http-auth-stdin) IFS= read -r HTTP_AUTH; shift ;;
    -h | --help) usage 0 ;;
    *) die "unknown option: $1 (see --help)" ;;
  esac
done

[ "$(id -u)" -eq 0 ] || die "run as root"
[[ "$SITE" =~ ^[a-z][a-z0-9-]{1,30}$ ]] || die "--site must be lowercase letters, digits and dashes"
[[ "$DOMAIN" =~ ^[a-z0-9.-]+\.[a-z]{2,}$ ]] || die "--domain must be a hostname like staging.example.com"
[[ "$WP_ENV" =~ ^(staging|production)$ ]] || die "--env must be staging or production"
[[ "$EMAIL" == *@* ]] || die "--email is required"
for alias in "${ALIASES[@]}"; do
  [[ "$alias" =~ ^[a-z0-9.-]+\.[a-z]{2,}$ ]] || die "bad --alias: $alias"
done
command -v nginx >/dev/null || die "run server/bootstrap.sh first"

SITE_ROOT="/srv/www/$SITE"
SHARED="$SITE_ROOT/shared"
DB_NAME="${SITE//-/_}"
DB_USER="$DB_NAME"
CREDENTIALS="/root/wp-credentials/$SITE.txt"
FPM_SOCKET="/run/php/php$PHP_VERSION-fpm-$SITE.sock"
HTPASSWD="/etc/nginx/htpasswd/$SITE"

mkdir -p /root/wp-credentials
chmod 700 /root/wp-credentials
touch "$CREDENTIALS"
chmod 600 "$CREDENTIALS"

credential() { sed -n "s/^$1=//p" "$CREDENTIALS" | head -n1; }
save_credential() {
  if grep -q "^$1=" "$CREDENTIALS"; then
    sed -i "s|^$1=.*|$1=$2|" "$CREDENTIALS"
  else
    echo "$1=$2" >>"$CREDENTIALS"
  fi
}

log "Site user $SITE"
if ! id "$SITE" >/dev/null 2>&1; then
  install -d -m 755 /srv/www
  useradd --create-home --home-dir "$SITE_ROOT" --shell /bin/bash "$SITE"
fi
# '*' = no password but not locked, so SSH key logins work and password logins cannot.
usermod -p '*' "$SITE"
# Nginx (www-data) may read the site; other site users may not.
chown "$SITE:www-data" "$SITE_ROOT"
chmod 750 "$SITE_ROOT"

install -d -o "$SITE" -g "$SITE" -m 755 "$SITE_ROOT/releases" "$SHARED" "$SHARED/web" "$SHARED/web/app"
install -d -o "$SITE" -g "$SITE" -m 755 "$SHARED/web/app/uploads"
install -d -o "$SITE" -g "$SITE" -m 700 "$SHARED/backups" "$SITE_ROOT/.ssh"

if [ ${#DEPLOY_KEYS[@]} -gt 0 ]; then
  touch "$SITE_ROOT/.ssh/authorized_keys"
  for key in "${DEPLOY_KEYS[@]}"; do
    # Compare the key itself, not the comment after it.
    blob="$(awk '{ print $2 }' <<<"$key")"
    [ -n "$blob" ] || die "bad --deploy-key: $key"
    grep -qF " $blob" "$SITE_ROOT/.ssh/authorized_keys" || echo "$key" >>"$SITE_ROOT/.ssh/authorized_keys"
  done
  chown "$SITE:$SITE" "$SITE_ROOT/.ssh/authorized_keys"
  chmod 600 "$SITE_ROOT/.ssh/authorized_keys"
elif [ ! -s "$SITE_ROOT/.ssh/authorized_keys" ]; then
  warn "no --deploy-key given and none installed yet; GitHub Actions cannot deploy until one is added."
fi

log "Database $DB_NAME"
DB_PASSWORD="$(credential DB_PASSWORD)"
if [ -z "$DB_PASSWORD" ]; then
  DB_PASSWORD="$(random 24)"
  save_credential DB_PASSWORD "$DB_PASSWORD"
fi
mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL

log "Environment file $SHARED/.env"
if [ ! -f "$SHARED/.env" ]; then
  {
    echo "# Created by server/add-site.sh. Secrets live here only; never commit this file."
    echo "WP_ENV='$WP_ENV'"
    echo "WP_HOME='https://$DOMAIN'"
    echo "WP_SITEURL=\"\${WP_HOME}/wp\""
    echo "DB_NAME='$DB_NAME'"
    echo "DB_USER='$DB_USER'"
    echo "DB_PASSWORD='$DB_PASSWORD'"
    echo "DB_HOST='localhost'"
    echo "# WP-Cron runs from /etc/cron.d/$SITE instead of on page loads."
    echo "DISABLE_WP_CRON=true"
    echo "# Outside production, wp_mail() is trapped unless every recipient is listed here."
    echo "MAIL_ALLOWLIST='$EMAIL'"
    for key in AUTH_KEY SECURE_AUTH_KEY LOGGED_IN_KEY NONCE_KEY AUTH_SALT SECURE_AUTH_SALT LOGGED_IN_SALT NONCE_SALT; do
      echo "$key='$(random 32)'"
    done
  } >"$SHARED/.env"
else
  echo "Keeping the existing .env (edit it by hand to change WP_HOME or other values)."
fi
chown "$SITE:$SITE" "$SHARED/.env"
chmod 600 "$SHARED/.env"

# Read by server/deploy-release.sh on the first deploy, then deleted.
if [ -z "$(credential WP_ADMIN_PASSWORD)" ]; then
  save_credential WP_ADMIN_USER "admin"
  save_credential WP_ADMIN_PASSWORD "$(random 12)"
  save_credential WP_ADMIN_EMAIL "$EMAIL"
  install -o "$SITE" -g "$SITE" -m 600 /dev/null "$SHARED/.wp-install"
  {
    echo "ADMIN_USER=$(credential WP_ADMIN_USER)"
    echo "ADMIN_PASSWORD=$(credential WP_ADMIN_PASSWORD)"
    echo "ADMIN_EMAIL=$EMAIL"
    echo "TITLE=Cigarholic"
  } >"$SHARED/.wp-install"
fi

log "PHP-FPM pool $SITE"
cat >"/etc/php/$PHP_VERSION/fpm/pool.d/$SITE.conf" <<EOF
; Managed by server/add-site.sh
[$SITE]
user = $SITE
group = $SITE
listen = $FPM_SOCKET
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
pm = dynamic
pm.max_children = 12
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 4
pm.max_requests = 500
php_admin_value[error_log] = /var/log/php$PHP_VERSION-fpm-$SITE.log
php_admin_flag[log_errors] = on
EOF
touch "/var/log/php$PHP_VERSION-fpm-$SITE.log"
chown "$SITE:$SITE" "/var/log/php$PHP_VERSION-fpm-$SITE.log"
"php-fpm$PHP_VERSION" -t
systemctl reload "php$PHP_VERSION-fpm"

log "WP-Cron"
cat >"/etc/cron.d/$SITE" <<EOF
# Managed by server/add-site.sh
*/5 * * * * $SITE [ -e $SITE_ROOT/current ] && cd $SITE_ROOT/current && /usr/local/bin/wp cron event run --due-now --quiet >/dev/null 2>&1
EOF
chmod 644 "/etc/cron.d/$SITE"

AUTH_BLOCK=""
if [ "$WP_ENV" = staging ] || [ -n "${HTTP_AUTH:-}" ]; then
  log "HTTP basic auth"
  if [ -n "${HTTP_AUTH:-}" ]; then
    auth_user="${HTTP_AUTH%%:*}"
    auth_password="${HTTP_AUTH#*:}"
  elif [ -n "$(credential HTTP_AUTH_PASSWORD)" ]; then
    auth_user="$(credential HTTP_AUTH_USER)"
    auth_password="$(credential HTTP_AUTH_PASSWORD)"
  else
    auth_user="cigarholic"
    auth_password="$(random 10)"
  fi
  save_credential HTTP_AUTH_USER "$auth_user"
  save_credential HTTP_AUTH_PASSWORD "$auth_password"
  htpasswd -bcB "$HTPASSWD" "$auth_user" "$auth_password" 2>/dev/null
  chown root:www-data "$HTPASSWD"
  chmod 640 "$HTPASSWD"
  # Requests from the server itself (deploy smoke tests, WP-Cron loopbacks) skip the password.
  AUTH_BLOCK="    satisfy any;
    allow 127.0.0.1;
    allow ::1;
    deny all;
    auth_basic \"$SITE\";
    auth_basic_user_file $HTPASSWD;"
fi

ALL_NAMES="$DOMAIN ${ALIASES[*]}"
ALL_NAMES="${ALL_NAMES% }"

# Listen on IPv6 too when the server has it.
listen6() { [ -s /proc/net/if_inet6 ] && printf '    listen [::]:%s;' "$1"; true; }

write_vhost() {
  local cert="/etc/ssl/certs/ssl-cert-snakeoil.pem"
  local key="/etc/ssl/private/ssl-cert-snakeoil.key"
  if [ -f "/etc/letsencrypt/live/$DOMAIN/fullchain.pem" ]; then
    cert="/etc/letsencrypt/live/$DOMAIN/fullchain.pem"
    key="/etc/letsencrypt/live/$DOMAIN/privkey.pem"
  fi

  local redirect_block=""
  if [ ${#ALIASES[@]} -gt 0 ]; then
    redirect_block="
server {
    listen 443 ssl http2;
$(listen6 '443 ssl http2')
    server_name ${ALIASES[*]};
    ssl_certificate $cert;
    ssl_certificate_key $key;
    location ^~ /.well-known/acme-challenge/ { root /var/www/letsencrypt; }
    location / { return 301 https://$DOMAIN\$request_uri; }
}"
  fi

  cat >"/etc/nginx/sites-available/$SITE.conf" <<EOF
# Managed by server/add-site.sh; re-run it instead of editing by hand.
server {
    listen 80;
$(listen6 80)
    server_name $ALL_NAMES;
    location ^~ /.well-known/acme-challenge/ { root /var/www/letsencrypt; }
    location / { return 301 https://$DOMAIN\$request_uri; }
}
$redirect_block
server {
    listen 443 ssl http2;
$(listen6 '443 ssl http2')
    server_name $DOMAIN;

    ssl_certificate $cert;
    ssl_certificate_key $key;

    root $SITE_ROOT/current/web;
    index index.php;

    access_log /var/log/nginx/$SITE.access.log;
    error_log /var/log/nginx/$SITE.error.log;

    location ^~ /.well-known/acme-challenge/ {
        root /var/www/letsencrypt;
        auth_basic off;
        allow all;
    }

$AUTH_BLOCK

    # OPS-03: block XML-RPC, dotfiles, and PHP inside uploads. 'return' rather than 'deny' so
    # staging's 'satisfy any' basic auth cannot override it.
    location ~* (^|/)xmlrpc\.php\$ { return 403; }
    location ~ /\.(?!well-known) { return 403; }
    location ~* ^/app/uploads/.*\.php\$ { return 403; }

    location ~* \.(?:css|js|mjs|jpe?g|gif|png|webp|avif|svg|ico|woff2?)\$ {
        expires 30d;
        access_log off;
        try_files \$uri =404;
    }

    location / {
        try_files \$uri \$uri/ /index.php?\$args;
    }

    location ~ \.php\$ {
        try_files \$uri =404;
        include fastcgi_params;
        # Resolve the 'current' symlink so each release gets its own OPcache entries.
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT \$realpath_root;
        fastcgi_param HTTPS on;
        fastcgi_pass unix:$FPM_SOCKET;
        fastcgi_read_timeout 120s;
    }
}
EOF
  ln -sfn "/etc/nginx/sites-available/$SITE.conf" "/etc/nginx/sites-enabled/$SITE.conf"
  nginx -t
  systemctl reload nginx
}

log "Nginx vhost $DOMAIN"
write_vhost

if [ "$TLS" -eq 1 ] && [ ! -f "/etc/letsencrypt/live/$DOMAIN/fullchain.pem" ]; then
  log "Let's Encrypt certificate"
  domain_args=()
  for name in $ALL_NAMES; do domain_args+=(-d "$name"); done
  if certbot certonly --webroot -w /var/www/letsencrypt --cert-name "$DOMAIN" "${domain_args[@]}" \
    --email "$EMAIL" --agree-tos --non-interactive --keep-until-expiring; then
    write_vhost
  else
    warn "certificate request failed. Point DNS for $ALL_NAMES at this server, then re-run this script."
  fi
fi

log "Done: $SITE"
cat <<EOF
  URL:          https://$DOMAIN
  Site root:    $SITE_ROOT (current -> active release)
  Environment:  $SHARED/.env
  Deploy as:    ssh $SITE@<this server>
  Credentials:  $CREDENTIALS (WordPress admin, database, basic auth)
EOF

#!/usr/bin/env bash
# Base provisioning for the Cigarholic VPS: a clean Ubuntu 26.04 LTS becomes a WordPress host
# (FND-08, OPS-01). Idempotent: every step checks before it changes and can be re-run any time.
#
# Usage (as root):
#   provision.sh [--disable-root-login] [--sshd-only] [--deploy-key "<ssh public key>"]
#
#   DEPLOY_AUTHORIZED_KEY   public key for the limited `deploy` user (or --deploy-key)
#   SSH_PORT                port ufw opens for SSH (default 22)
#   PROVISION_NO_SYSTEMD=1  skip service start/enable (auto-detected inside containers)
#   HTTPS_PROXY             honored by the few HTTPS downloads (WP-CLI, Composer)
#   INFRA_DIR               the repository's infra/ directory on this machine; defaults to the
#                           directory this script lives in, or ~/cigarholic-infra/infra when the
#                           script is streamed over SSH by .github/actions/ssh
#
# Everything comes from the Ubuntu archive (apt 3, deb822 sources): Nginx, PHP 8.5-FPM, MariaDB 11.8,
# Valkey, certbot, fail2ban, ufw, unattended-upgrades, rclone. WP-CLI and Composer are downloaded as
# pinned phars with checksum verification. Per-site setup is site.sh.
set -euo pipefail

export DEBIAN_FRONTEND=noninteractive
PHP_VERSION=8.5
# Pinned phars; each download is verified against the checksum file published next to it.
WP_CLI_VERSION=2.12.0
COMPOSER_VERSION=2.8.12

DISABLE_ROOT_LOGIN=0
SSHD_ONLY=0
DEPLOY_KEY="${DEPLOY_AUTHORIZED_KEY:-}"
SSH_PORT="${SSH_PORT:-22}"

while [ $# -gt 0 ]; do
  case "$1" in
    --disable-root-login) DISABLE_ROOT_LOGIN=1 ;;
    --sshd-only) SSHD_ONLY=1 ;;
    --deploy-key) DEPLOY_KEY="${2:-}"; shift ;;
    -h|--help) sed -n '2,20p' "$0"; exit 0 ;;
    *) echo "unknown argument: $1" >&2; exit 2 ;;
  esac
  shift
done

log()  { printf '[provision] %s\n' "$*"; }
warn() { printf '[provision] WARNING: %s\n' "$*" >&2; }
die()  { printf '[provision] ERROR: %s\n' "$*" >&2; exit 1; }

[ "$(id -u)" -eq 0 ] || die "run as root"

# --- locate the repository's infra/ directory (templates, filters, units) -------------------------
if [ -z "${INFRA_DIR:-}" ]; then
  if [ -f "$0" ] && [ "$(basename "$0")" = "provision.sh" ]; then
    INFRA_DIR="$(cd "$(dirname "$(readlink -f "$0")")/.." && pwd)"
  else
    for candidate in "$HOME/cigarholic-infra/infra" /root/cigarholic-infra/infra /opt/cigarholic/infra; do
      [ -d "$candidate/server" ] && INFRA_DIR="$candidate" && break
    done
  fi
fi
[ -n "${INFRA_DIR:-}" ] && [ -d "$INFRA_DIR/server" ] || die "INFRA_DIR not found; upload the repository's infra/ directory and set INFRA_DIR"
SERVER_DIR="$INFRA_DIR/server"
log "using templates from $SERVER_DIR"

# --- systemd detection ----------------------------------------------------------------------------
HAS_SYSTEMD=1
if [ "${PROVISION_NO_SYSTEMD:-0}" = "1" ] || [ ! -d /run/systemd/system ] || ! command -v systemctl >/dev/null 2>&1; then
  HAS_SYSTEMD=0
  export PROVISION_NO_SYSTEMD=1
  log "no systemd detected (container?): services will be installed and configured but not started or enabled"
  # Stop package maintainer scripts from trying to start daemons.
  printf '#!/bin/sh\nexit 101\n' >/usr/sbin/policy-rc.d
  chmod +x /usr/sbin/policy-rc.d
fi

# svc <systemctl verb...>: runs systemctl, or logs the skip when there is no systemd.
svc() {
  if [ "$HAS_SYSTEMD" = 1 ]; then
    systemctl "$@"
  else
    log "skip (no systemd): systemctl $*"
  fi
}

# install_file <source> <destination> [mode]: copies when content differs; returns 0 when changed.
install_file() {
  local src=$1 dst=$2 mode=${3:-0644}
  if [ -f "$dst" ] && cmp -s "$src" "$dst"; then
    chmod "$mode" "$dst"
    return 1
  fi
  install -D -m "$mode" "$src" "$dst"
  log "wrote $dst"
  return 0
}

# write_file <destination> [mode] <<EOF ... EOF: same as install_file for inline content.
write_file() {
  local dst=$1 mode=${2:-0644} tmp
  tmp=$(mktemp)
  cat >"$tmp"
  if install_file "$tmp" "$dst" "$mode"; then rm -f "$tmp"; return 0; fi
  rm -f "$tmp"
  return 1
}

fetch() { # fetch <url> <destination>; honors HTTPS_PROXY through curl
  curl -fsSL --retry 3 --retry-delay 2 --proto '=https' -o "$2" "$1"
}

pkg_available() { apt-cache policy "$1" 2>/dev/null | grep -q 'Candidate: [0-9]'; }

# =================================================================================================
# sshd hardening (also the only step of --sshd-only)
# =================================================================================================
configure_sshd() {
  local permit_root=prohibit-password
  if [ "$DISABLE_ROOT_LOGIN" = 1 ]; then
    if ! id deploy >/dev/null 2>&1 || [ ! -s /home/deploy/.ssh/authorized_keys ]; then
      die "--disable-root-login refused: the deploy user has no authorized key yet"
    fi
    permit_root=no
  fi
  mkdir -p /etc/ssh/sshd_config.d
  # 00- sorts before any cloud-init or provider drop-in; the first value wins in sshd.
  write_file /etc/ssh/sshd_config.d/00-cigarholic.conf <<EOF || true
# Managed by infra/server/provision.sh
PermitRootLogin $permit_root
PasswordAuthentication no
KbdInteractiveAuthentication no
ChallengeResponseAuthentication no
PubkeyAuthentication yes
PermitEmptyPasswords no
X11Forwarding no
MaxAuthTries 4
LoginGraceTime 30
ClientAliveInterval 300
ClientAliveCountMax 2
AllowAgentForwarding no
EOF
  if command -v sshd >/dev/null 2>&1; then
    sshd -t || die "sshd configuration test failed"
    if [ "$HAS_SYSTEMD" = 1 ]; then
      systemctl reload ssh 2>/dev/null || systemctl reload sshd 2>/dev/null || warn "could not reload ssh"
    fi
  else
    warn "sshd binary not found; configuration written but not tested"
  fi
  log "sshd: keys only, PermitRootLogin $permit_root"
}

if [ "$SSHD_ONLY" = 1 ]; then
  configure_sshd
  exit 0
fi

# =================================================================================================
# 1. Time zone
# =================================================================================================
if [ "$(cat /etc/timezone 2>/dev/null)" != "UTC" ] || [ "$(readlink -f /etc/localtime)" != "/usr/share/zoneinfo/UTC" ]; then
  if [ "$HAS_SYSTEMD" = 1 ] && command -v timedatectl >/dev/null 2>&1; then
    timedatectl set-timezone UTC
  else
    ln -sf /usr/share/zoneinfo/UTC /etc/localtime
    echo UTC >/etc/timezone
  fi
  log "timezone set to UTC"
fi

# =================================================================================================
# 2. Packages (Ubuntu archive only; apt 3 with deb822 .sources)
# =================================================================================================
if ! grep -rqs '^Components:.*universe' /etc/apt/sources.list.d/*.sources; then
  warn "no deb822 source lists the universe component; PHP extensions and fail2ban live there"
fi
ls /etc/apt/sources.list.d/*.sources >/dev/null 2>&1 || warn "no deb822 .sources files found; is this Ubuntu 26.04?"

apt-get update -qq
PACKAGES=(
  ca-certificates curl gnupg unzip git rsync acl logrotate openssl sudo
  nginx
  "php${PHP_VERSION}-fpm" "php${PHP_VERSION}-cli" "php${PHP_VERSION}-mysql" "php${PHP_VERSION}-curl"
  "php${PHP_VERSION}-gd" "php${PHP_VERSION}-intl" "php${PHP_VERSION}-mbstring" "php${PHP_VERSION}-xml"
  "php${PHP_VERSION}-zip" "php${PHP_VERSION}-bcmath" "php${PHP_VERSION}-imagick" "php${PHP_VERSION}-redis"
  "php${PHP_VERSION}-soap"
  mariadb-server mariadb-client
  valkey-server valkey-tools
  ufw fail2ban unattended-upgrades
  certbot
  openssh-server
  rclone
)
# opcache is a separate package on some releases and part of php-common on others.
pkg_available "php${PHP_VERSION}-opcache" && PACKAGES+=("php${PHP_VERSION}-opcache")

MISSING=()
for p in "${PACKAGES[@]}"; do
  dpkg-query -W -f='${Status}' "$p" 2>/dev/null | grep -q 'install ok installed' || MISSING+=("$p")
done
if [ ${#MISSING[@]} -gt 0 ]; then
  log "installing: ${MISSING[*]}"
  apt-get install -y -qq --no-install-recommends "${MISSING[@]}"
else
  log "all packages already installed"
fi
update-ca-certificates >/dev/null 2>&1 || true

# =================================================================================================
# 3. PHP-FPM
# =================================================================================================
write_file "/etc/php/${PHP_VERSION}/fpm/conf.d/90-cigarholic.ini" <<'EOF' && PHP_CHANGED=1 || PHP_CHANGED=0
; Managed by infra/server/provision.sh
expose_php = Off
date.timezone = UTC
memory_limit = 512M
upload_max_filesize = 64M
post_max_size = 64M
max_execution_time = 120
max_input_time = 120
max_input_vars = 5000
session.cookie_httponly = 1
session.use_strict_mode = 1
opcache.enable = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 32
opcache.max_accelerated_files = 50000
opcache.validate_timestamps = 1
opcache.revalidate_freq = 60
opcache.save_comments = 1
realpath_cache_size = 4096K
realpath_cache_ttl = 600
EOF
write_file "/etc/php/${PHP_VERSION}/cli/conf.d/90-cigarholic.ini" <<'EOF' || true
; Managed by infra/server/provision.sh (WP-CLI and Composer)
date.timezone = UTC
memory_limit = 1G
EOF
mkdir -p /run/php
svc enable --now "php${PHP_VERSION}-fpm" || true
[ "$PHP_CHANGED" = 1 ] && svc reload "php${PHP_VERSION}-fpm" || true

# =================================================================================================
# 4. MariaDB
# =================================================================================================
write_file /etc/mysql/mariadb.conf.d/60-cigarholic.cnf <<'EOF' && DB_CHANGED=1 || DB_CHANGED=0
# Managed by infra/server/provision.sh. 16 GB host shared by two sites, PHP-FPM and Valkey.
[mysqld]
bind-address            = 127.0.0.1
skip-name-resolve
character-set-server    = utf8mb4
collation-server        = utf8mb4_unicode_ci
innodb_buffer_pool_size = 2G
innodb_log_file_size    = 256M
innodb_flush_method     = O_DIRECT
innodb_flush_log_at_trx_commit = 1
max_connections         = 150
table_open_cache        = 2000
tmp_table_size          = 64M
max_heap_table_size     = 64M
max_allowed_packet      = 64M
slow_query_log          = 1
long_query_time         = 2
slow_query_log_file     = /var/log/mysql/mariadb-slow.log
EOF
svc enable --now mariadb || true
[ "$DB_CHANGED" = 1 ] && svc restart mariadb || true
if [ "$HAS_SYSTEMD" = 1 ] && mariadb-admin ping --silent >/dev/null 2>&1; then
  # Non-interactive equivalent of mariadb-secure-installation. root keeps unix_socket auth.
  mariadb --batch --skip-column-names -e "SELECT CONCAT(\"'\",User,\"'@'\",Host,\"'\") FROM mysql.global_priv WHERE User=''" \
    | while read -r u; do [ -n "$u" ] && mariadb -e "DROP USER IF EXISTS $u"; done
  mariadb --batch --skip-column-names -e "SELECT CONCAT(\"'\",User,\"'@'\",Host,\"'\") FROM mysql.global_priv WHERE User='root' AND Host NOT IN ('localhost','127.0.0.1','::1')" \
    | while read -r u; do [ -n "$u" ] && mariadb -e "DROP USER IF EXISTS $u"; done
  mariadb -e "DROP DATABASE IF EXISTS test; DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%'; FLUSH PRIVILEGES;"
  log "mariadb: anonymous users, remote root and the test database removed"
else
  log "skip (mariadb not running): secure-installation SQL; site.sh re-asserts users when it runs"
fi

# =================================================================================================
# 5. Valkey (Redis protocol, object cache for both sites)
# =================================================================================================
mkdir -p /etc/valkey/conf.d
write_file /etc/valkey/conf.d/cigarholic.conf <<'EOF' && VK_CHANGED=1 || VK_CHANGED=0
# Managed by infra/server/provision.sh: a cache, not a store. Keys are prefixed per site.
maxmemory 1gb
maxmemory-policy allkeys-lru
save ""
appendonly no
EOF
if ! grep -q '^include /etc/valkey/conf.d/cigarholic.conf' /etc/valkey/valkey.conf; then
  printf '\n# Cigarholic overrides (last include wins)\ninclude /etc/valkey/conf.d/cigarholic.conf\n' >>/etc/valkey/valkey.conf
  VK_CHANGED=1
  log "valkey.conf includes conf.d/cigarholic.conf"
fi
svc enable --now valkey-server || true
[ "$VK_CHANGED" = 1 ] && svc restart valkey-server || true

# =================================================================================================
# 6. Nginx: shared pieces (sites are added by site.sh)
# =================================================================================================
install_file "$SERVER_DIR/nginx/conf.d/cigarholic-common.conf" /etc/nginx/conf.d/cigarholic-common.conf && NGINX_CHANGED=1 || NGINX_CHANGED=0
for snippet in "$SERVER_DIR"/nginx/snippets/*.conf; do
  install_file "$snippet" "/etc/nginx/snippets/$(basename "$snippet")" && NGINX_CHANGED=1
done
DEFAULT_SITE_TMP=$(mktemp)
cp "$SERVER_DIR/nginx/sites/cigarholic-default.conf" "$DEFAULT_SITE_TMP"
if [ ! -s /proc/net/if_inet6 ]; then
  log "no IPv6 on this host: IPv6 listen directives are left out"
  sed -i '/listen \[::\]/d' "$DEFAULT_SITE_TMP"
fi
install_file "$DEFAULT_SITE_TMP" /etc/nginx/sites-available/cigarholic-default && NGINX_CHANGED=1
rm -f "$DEFAULT_SITE_TMP"
ln -sfn /etc/nginx/sites-available/cigarholic-default /etc/nginx/sites-enabled/cigarholic-default
if [ -e /etc/nginx/sites-enabled/default ]; then
  rm -f /etc/nginx/sites-enabled/default
  NGINX_CHANGED=1
  log "removed the stock default site"
fi
mkdir -p /var/cache/nginx /etc/nginx/cigarholic
chown www-data:www-data /var/cache/nginx
if nginx -t >/dev/null 2>&1; then
  svc enable --now nginx || true
  [ "$NGINX_CHANGED" = 1 ] && svc reload nginx || true
else
  nginx -t || warn "nginx configuration test failed; fix before starting"
fi

# =================================================================================================
# 7. Firewall: 22 (or SSH_PORT), 80, 443 only
# =================================================================================================
if [ "$HAS_SYSTEMD" = 1 ]; then
  ufw default deny incoming >/dev/null
  ufw default allow outgoing >/dev/null
  ufw allow "${SSH_PORT}/tcp" comment 'SSH' >/dev/null
  ufw allow 80/tcp comment 'HTTP' >/dev/null
  ufw allow 443/tcp comment 'HTTPS' >/dev/null
  ufw --force enable >/dev/null
  log "ufw: allow ${SSH_PORT}, 80, 443; deny the rest"
else
  log "skip (no systemd/iptables): ufw rules and enable"
fi

# =================================================================================================
# 8. fail2ban: sshd, nginx and a wp-login filter
# =================================================================================================
install_file "$SERVER_DIR/fail2ban/filter.d/wp-login.conf" /etc/fail2ban/filter.d/wp-login.conf && F2B_CHANGED=1 || F2B_CHANGED=0
install_file "$SERVER_DIR/fail2ban/jail.d/cigarholic.local" /etc/fail2ban/jail.d/cigarholic.local && F2B_CHANGED=1
svc enable --now fail2ban || true
[ "$F2B_CHANGED" = 1 ] && svc reload fail2ban || true

# =================================================================================================
# 9. Unattended security updates
# =================================================================================================
write_file /etc/apt/apt.conf.d/20auto-upgrades <<'EOF' || true
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
APT::Periodic::AutocleanInterval "7";
EOF
write_file /etc/apt/apt.conf.d/52cigarholic-unattended-upgrades <<'EOF' || true
// Managed by infra/server/provision.sh: security updates only, no automatic reboot.
Unattended-Upgrade::Allowed-Origins {
    "${distro_id}:${distro_codename}-security";
    "${distro_id}ESMApps:${distro_codename}-apps-security";
    "${distro_id}ESM:${distro_codename}-infra-security";
};
Unattended-Upgrade::Remove-Unused-Kernel-Packages "true";
Unattended-Upgrade::Remove-Unused-Dependencies "true";
Unattended-Upgrade::Automatic-Reboot "false";
Unattended-Upgrade::Verbose "true";
EOF
svc enable --now unattended-upgrades || true

# =================================================================================================
# 10. certbot renewal hook (certificates are requested by site.sh)
# =================================================================================================
write_file /etc/letsencrypt/renewal-hooks/deploy/reload-nginx.sh 0755 <<'EOF' || true
#!/bin/sh
# Managed by infra/server/provision.sh
nginx -t && systemctl reload nginx
EOF
svc enable --now certbot.timer || true

# =================================================================================================
# 11. WP-CLI and Composer phars in /usr/local/bin
# =================================================================================================
install_phar() { # install_phar <name> <version> <url> <sha-algo> <sha-url> <version-probe>
  local name=$1 version=$2 url=$3 algo=$4 sha_url=$5 probe=$6 tmp expected actual
  if [ -x "/usr/local/bin/$name" ] && "/usr/local/bin/$name" $probe 2>/dev/null | grep -q "$version"; then
    log "$name $version already installed"
    return 0
  fi
  tmp=$(mktemp -d)
  fetch "$url" "$tmp/$name" || { warn "download of $name failed (network?)"; rm -rf "$tmp"; return 0; }
  fetch "$sha_url" "$tmp/$name.sum" || { warn "download of the $name checksum failed"; rm -rf "$tmp"; return 0; }
  expected=$(awk '{print $1}' "$tmp/$name.sum" | head -n1)
  actual=$("${algo}sum" "$tmp/$name" | awk '{print $1}')
  if [ "$expected" != "$actual" ]; then
    rm -rf "$tmp"
    die "$name checksum mismatch (expected $expected, got $actual)"
  fi
  install -m 0755 "$tmp/$name" "/usr/local/bin/$name"
  rm -rf "$tmp"
  log "installed $name $version"
}
install_phar wp "$WP_CLI_VERSION" \
  "https://github.com/wp-cli/wp-cli/releases/download/v${WP_CLI_VERSION}/wp-cli-${WP_CLI_VERSION}.phar" \
  sha512 "https://github.com/wp-cli/wp-cli/releases/download/v${WP_CLI_VERSION}/wp-cli-${WP_CLI_VERSION}.phar.sha512" \
  "--version --allow-root"
install_phar composer "$COMPOSER_VERSION" \
  "https://getcomposer.org/download/${COMPOSER_VERSION}/composer.phar" \
  sha256 "https://getcomposer.org/download/${COMPOSER_VERSION}/composer.phar.sha256sum" \
  "--version"

# =================================================================================================
# 12. Swap when RAM < 8 GB
# =================================================================================================
MEM_MB=$(awk '/MemTotal/ {printf "%d", $2/1024}' /proc/meminfo)
if [ "$MEM_MB" -lt 8192 ]; then
  if [ ! -f /swapfile ]; then
    fallocate -l 2G /swapfile || dd if=/dev/zero of=/swapfile bs=1M count=2048 status=none
    chmod 600 /swapfile
    mkswap /swapfile >/dev/null
    log "created 2G /swapfile (RAM ${MEM_MB} MB)"
  fi
  grep -q '^/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >>/etc/fstab
  swapon /swapfile 2>/dev/null || log "skip: swapon not permitted here"
else
  log "RAM ${MEM_MB} MB: no swap file needed"
fi

# =================================================================================================
# 13. logrotate for the sites' WordPress logs (nginx has its own from the package)
# =================================================================================================
write_file /etc/logrotate.d/cigarholic-sites <<'EOF' || true
# Managed by infra/server/provision.sh
/var/www/*/shared/logs/*.log {
    daily
    rotate 14
    missingok
    notifempty
    compress
    delaycompress
    copytruncate
}
EOF

# =================================================================================================
# 14. deploy user, sudoers, helpers
# =================================================================================================
if ! id deploy >/dev/null 2>&1; then
  useradd --create-home --shell /bin/bash --user-group deploy
  log "created user deploy"
fi
install -d -m 0700 -o deploy -g deploy /home/deploy/.ssh
if [ -n "$DEPLOY_KEY" ]; then
  if ! grep -qxF "$DEPLOY_KEY" /home/deploy/.ssh/authorized_keys 2>/dev/null; then
    printf '%s\n' "$DEPLOY_KEY" >>/home/deploy/.ssh/authorized_keys
    log "added the deploy authorized key"
  fi
  chown deploy:deploy /home/deploy/.ssh/authorized_keys
  chmod 0600 /home/deploy/.ssh/authorized_keys
else
  warn "DEPLOY_AUTHORIZED_KEY not given: the deploy user has no key (pass it to add one)"
fi
install_file "$SERVER_DIR/bin/cigarholic-cache-purge" /usr/local/sbin/cigarholic-cache-purge 0755 || true
install_file "$SERVER_DIR/sudoers/cigarholic-deploy" /etc/sudoers.d/cigarholic-deploy 0440 || true
visudo -cf /etc/sudoers.d/cigarholic-deploy >/dev/null || die "sudoers file invalid"

# =================================================================================================
# 15. Nightly production backup timer (credentials: /etc/cigarholic/backup.env, written by backup.sh)
# =================================================================================================
install -d -m 0700 /etc/cigarholic
install_file "$INFRA_DIR/backup/backup.sh" /usr/local/bin/cigarholic-backup 0755 || true
install_file "$SERVER_DIR/systemd/cigarholic-backup.service" /etc/systemd/system/cigarholic-backup.service && UNIT_CHANGED=1 || UNIT_CHANGED=0
install_file "$SERVER_DIR/systemd/cigarholic-backup.timer" /etc/systemd/system/cigarholic-backup.timer && UNIT_CHANGED=1
[ "$UNIT_CHANGED" = 1 ] && svc daemon-reload || true
svc enable --now cigarholic-backup.timer || true

# =================================================================================================
# 16. sshd last, so a mistake above never locks anyone out before the rest is in place
# =================================================================================================
configure_sshd

# =================================================================================================
log "done. Versions:"
printf '  %-16s %s\n' nginx "$(nginx -v 2>&1 | sed 's#.*/##')"
printf '  %-16s %s\n' php "$(php -v | head -n1 | awk '{print $2}')"
printf '  %-16s %s\n' mariadb "$(mariadb --version | grep -oE '[0-9]+\.[0-9]+\.[0-9]+-MariaDB' | sed -n '1s/-MariaDB//p')"
printf '  %-16s %s\n' valkey "$(valkey-server --version | awk '{print $3}' | sed 's/^v=//')"
printf '  %-16s %s\n' certbot "$(certbot --version 2>&1 | awk '{print $2}')"
printf '  %-16s %s\n' fail2ban "$(fail2ban-client --version 2>/dev/null | head -n1 | awk '{print $2}')"
printf '  %-16s %s\n' wp-cli "$(/usr/local/bin/wp --version --allow-root 2>/dev/null | awk '{print $2}' || echo missing)"
printf '  %-16s %s\n' composer "$(/usr/local/bin/composer --version 2>/dev/null | awk '{print $3}' || echo missing)"
log "next: site.sh staging / site.sh production"

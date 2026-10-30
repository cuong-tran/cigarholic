#!/usr/bin/env bash
# OPS-01: server-wide setup for a fresh Ubuntu 26.04 (or 24.04) VPS. Safe to re-run. Run as root:
#
#   bash server/bootstrap.sh [--harden-ssh]
#
# Installs Nginx, PHP-FPM, MariaDB, Redis, WP-CLI and Certbot; turns on the firewall, fail2ban and
# automatic security updates. PHP is the release's own version (8.5 on 26.04, 8.3 on 24.04), so it
# gets Ubuntu security updates. Sites are added afterwards with server/add-site.sh.
#
#   --harden-ssh   turn off SSH password logins (key-only, root included). Use it only once you can
#                  log in with an SSH key, or you will lock yourself out.
set -euo pipefail
umask 022

HARDEN_SSH=0

for arg in "$@"; do
  case "$arg" in
    --harden-ssh) HARDEN_SSH=1 ;;
    *) echo "Unknown option: $arg" >&2; exit 1 ;;
  esac
done

log() { printf '\n==> %s\n' "$*"; }
warn() { printf 'WARNING: %s\n' "$*" >&2; }

[ "$(id -u)" -eq 0 ] || { echo "Run as root." >&2; exit 1; }
# shellcheck source=/dev/null
. /etc/os-release
case "${ID:-}-${VERSION_ID:-}" in
  ubuntu-26.04 | ubuntu-24.04) ;;
  *) warn "written for Ubuntu 26.04 and 24.04; this is ${PRETTY_NAME:-unknown}." ;;
esac
if systemctl is-active --quiet apache2 2>/dev/null; then
  echo "Apache is running and holds ports 80/443. Stop it first: systemctl disable --now apache2" >&2
  exit 1
fi

export DEBIAN_FRONTEND=noninteractive

log "Installing packages"
apt-get update -q
apt-get install -y -q --no-install-recommends \
  nginx mariadb-server mariadb-client redis-server \
  php-fpm php-cli php-mysql php-curl php-gd php-intl php-mbstring php-xml php-zip php-bcmath \
  php-imagick php-redis \
  certbot ufw fail2ban unattended-upgrades cron logrotate \
  ca-certificates curl git rsync unzip less ssl-cert apache2-utils

log "Installing WP-CLI"
if ! command -v wp >/dev/null 2>&1; then
  curl -fsSL -o /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
  chmod 755 /usr/local/bin/wp
fi
wp --allow-root --version

PHP_VERSION="$(php -r 'echo PHP_MAJOR_VERSION, ".", PHP_MINOR_VERSION;')"
log "Configuring PHP $PHP_VERSION"
cat >"/etc/php/$PHP_VERSION/fpm/conf.d/90-cigarholic.ini" <<'EOF'
; Managed by server/bootstrap.sh
expose_php = Off
memory_limit = 256M
upload_max_filesize = 64M
post_max_size = 64M
max_execution_time = 60
max_input_vars = 3000
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 20000
EOF

log "Configuring Redis"
if ! grep -q '^maxmemory ' /etc/redis/redis.conf; then
  printf '\n# Managed by server/bootstrap.sh\nmaxmemory 256mb\nmaxmemory-policy allkeys-lru\n' >>/etc/redis/redis.conf
fi

log "Configuring Nginx"
mkdir -p /var/www/letsencrypt /etc/nginx/htpasswd
rm -f /etc/nginx/sites-enabled/default
# Listen on IPv6 too when the server has it.
listen6() { [ -s /proc/net/if_inet6 ] && printf '    listen [::]:%s;' "$1"; true; }
# Hide the Nginx version. Edited in place: 26.04's nginx.conf sets server_tokens itself.
sed -i -E 's/^(\s*)#?\s*server_tokens\s+\S+;.*/\1server_tokens off;/' /etc/nginx/nginx.conf
cat >/etc/nginx/conf.d/00-cigarholic.conf <<EOF
# Managed by server/bootstrap.sh
client_max_body_size 64m;
gzip_vary on;
gzip_proxied any;
gzip_types text/css application/javascript application/json image/svg+xml text/plain text/xml;

# Refuse requests for hostnames that no site claims.
server {
    listen 80 default_server;
$(listen6 '80 default_server')
    server_name _;
    location ^~ /.well-known/acme-challenge/ { root /var/www/letsencrypt; }
    location / { return 444; }
}
server {
    listen 443 ssl default_server;
$(listen6 '443 ssl default_server')
    server_name _;
    ssl_reject_handshake on;
}
EOF
# Restore visitor IPs when the site sits behind Cloudflare (trusts Cloudflare's ranges only).
if cf4="$(curl -fsS --max-time 10 https://www.cloudflare.com/ips-v4)" &&
  cf6="$(curl -fsS --max-time 10 https://www.cloudflare.com/ips-v6)"; then
  {
    echo "# Managed by server/bootstrap.sh; refreshed on every run."
    for range in $cf4 $cf6; do echo "set_real_ip_from $range;"; done
    echo "real_ip_header CF-Connecting-IP;"
  } >/etc/nginx/conf.d/01-cloudflare-realip.conf
else
  warn "could not fetch Cloudflare IP ranges; visitor IPs will show as Cloudflare's if it is in front."
fi
nginx -t

log "Configuring Certbot renewals"
mkdir -p /etc/letsencrypt/renewal-hooks/deploy
cat >/etc/letsencrypt/renewal-hooks/deploy/reload-nginx.sh <<'EOF'
#!/bin/sh
systemctl reload nginx
EOF
chmod 755 /etc/letsencrypt/renewal-hooks/deploy/reload-nginx.sh

log "Turning on automatic security updates"
cat >/etc/apt/apt.conf.d/20auto-upgrades <<'EOF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
EOF

log "Configuring swap"
if [ -z "$(swapon --show --noheadings 2>/dev/null)" ] && [ ! -f /swapfile ]; then
  if fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile >/dev/null && swapon /swapfile; then
    echo '/swapfile none swap sw 0 0' >>/etc/fstab
  else
    rm -f /swapfile
    warn "could not create a swap file (normal inside containers)."
  fi
fi

log "Starting services"
for service in nginx "php$PHP_VERSION-fpm" mariadb; do
  systemctl enable --now "$service" >/dev/null
done
for service in redis-server fail2ban cron; do
  systemctl enable --now "$service" >/dev/null || warn "$service did not start; check: journalctl -u $service"
done
systemctl reload nginx

log "Configuring the firewall"
ssh_ports="$({ sshd -T 2>/dev/null || true; } | awk '$1 == "port" { print $2 }')"
for port in ${ssh_ports:-22}; do ufw allow "$port/tcp" >/dev/null; done
ufw allow 'Nginx Full' >/dev/null
ufw --force enable >/dev/null || warn "ufw could not be enabled (normal inside containers)."

if [ "$HARDEN_SSH" -eq 1 ]; then
  log "Turning off SSH password logins"
  if [ ! -s /root/.ssh/authorized_keys ]; then
    warn "root has no authorized_keys; leaving password logins on so you are not locked out."
  else
    cat >/etc/ssh/sshd_config.d/50-cigarholic.conf <<'EOF'
# Managed by server/bootstrap.sh --harden-ssh
PasswordAuthentication no
KbdInteractiveAuthentication no
PermitRootLogin prohibit-password
EOF
    sshd -t && systemctl reload ssh
  fi
fi

log "Done. Next: bash server/add-site.sh --help"

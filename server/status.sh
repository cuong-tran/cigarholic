#!/usr/bin/env bash
# Read-only health report for the server. Run as root; used by the server workflow's "status" action.
set -uo pipefail

PHP_VERSION="$(php -r 'echo PHP_MAJOR_VERSION, ".", PHP_MINOR_VERSION;' 2>/dev/null)"

section() { printf '\n== %s\n' "$*"; }

section "Host"
hostname
grep PRETTY_NAME /etc/os-release | cut -d= -f2 | tr -d '"'
php -v | head -n1
nginx -v 2>&1
mariadb --version 2>/dev/null || mysql --version
uptime
section "Disk"
df -h /
section "Memory"
free -h
section "Services"
for service in nginx "php$PHP_VERSION-fpm" mariadb redis-server fail2ban cron; do
  printf '%-16s %s\n' "$service" "$(systemctl is-active "$service" 2>/dev/null)"
done
section "Firewall"
ufw status 2>/dev/null | head -20
section "Nginx config"
nginx -t 2>&1
section "Certificates"
certbot certificates 2>/dev/null | grep -E 'Certificate Name|Domains|Expiry' || echo "none"
section "Sites"
for dir in /srv/www/*/; do
  [ -d "$dir" ] || continue
  printf '%s: current -> %s\n' "$(basename "$dir")" "$(readlink "$dir/current" || echo '(not deployed yet)')"
done
section "Upgradable packages"
apt list --upgradable 2>/dev/null | tail -n +2 | wc -l

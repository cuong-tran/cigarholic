#!/usr/bin/env bash
# Prints the state of the Cigarholic VPS: OS, package versions, services, firewall, disk, memory,
# certificates and the last deploy and backup of each site (OPS-01). Read-only; runs as root or
# as the deploy user (items that need root print n/a).
set -uo pipefail

section() { printf '\n== %s\n' "$*"; }
try() { "$@" 2>/dev/null || echo "n/a"; }

HAS_SYSTEMD=0
[ -d /run/systemd/system ] && command -v systemctl >/dev/null 2>&1 && HAS_SYSTEMD=1

section "Host"
grep -E '^PRETTY_NAME=' /etc/os-release | cut -d= -f2 | tr -d '"'
echo "kernel  $(uname -r)"
echo "uptime  $(uptime -p 2>/dev/null || true)"
echo "time    $(date -u +%Y-%m-%dT%H:%M:%SZ) (local zone $(date +%Z))"

section "Versions"
printf '  %-10s %s\n' nginx "$(nginx -v 2>&1 | sed 's#.*/##')"
printf '  %-10s %s\n' php "$(php -v 2>/dev/null | head -n1 | awk '{print $2}')"
printf '  %-10s %s\n' mariadb "$(mariadb --version 2>/dev/null | grep -oE '[0-9]+\.[0-9]+\.[0-9]+-MariaDB' | sed -n '1s/-MariaDB//p')"
printf '  %-10s %s\n' valkey "$(valkey-server --version 2>/dev/null | awk '{print $3}' | sed 's/^v=//')"
printf '  %-10s %s\n' certbot "$(certbot --version 2>&1 | awk '{print $2}')"
printf '  %-10s %s\n' fail2ban "$(fail2ban-client --version 2>/dev/null | head -n1 | awk '{print $2}')"
printf '  %-10s %s\n' rclone "$(rclone --version 2>/dev/null | head -n1 | awk '{print $2}')"
printf '  %-10s %s\n' wp-cli "$(wp --version --allow-root 2>/dev/null | awk '{print $2}')"
printf '  %-10s %s\n' composer "$(composer --version 2>/dev/null | awk '{print $3}')"

section "Services"
if [ "$HAS_SYSTEMD" = 1 ]; then
  for s in nginx php8.5-fpm mariadb valkey-server fail2ban ufw ssh unattended-upgrades certbot.timer cigarholic-backup.timer; do
    printf '  %-26s %s\n' "$s" "$(systemctl is-active "$s" 2>/dev/null) / $(systemctl is-enabled "$s" 2>/dev/null)"
  done
  echo "  failed units: $(systemctl --failed --no-legend --plain 2>/dev/null | awk '{print $1}' | paste -sd' ' || true)"
else
  echo "  no systemd"
fi

section "Firewall"
if [ "$(id -u)" -eq 0 ]; then try ufw status | sed 's/^/  /'; else echo "  n/a (needs root)"; fi

section "fail2ban"
if [ "$(id -u)" -eq 0 ] && command -v fail2ban-client >/dev/null 2>&1; then
  for j in $(fail2ban-client status 2>/dev/null | sed -n 's/.*Jail list:\s*//p' | tr -d ',' ); do
    printf '  %-18s banned now %s, total %s\n' "$j" \
      "$(fail2ban-client status "$j" 2>/dev/null | sed -n 's/.*Currently banned:\s*//p')" \
      "$(fail2ban-client status "$j" 2>/dev/null | sed -n 's/.*Total banned:\s*//p')"
  done
else
  echo "  n/a (needs root)"
fi

section "Disk and memory"
df -h / /var/www 2>/dev/null | sed 's/^/  /'
free -h | sed 's/^/  /'
echo "  nginx cache: $(du -sh /var/cache/nginx 2>/dev/null | awk '{print $1}')"

section "Certificates"
for d in /etc/nginx/cigarholic/*/; do
  [ -d "$d" ] || continue
  site=$(basename "$d")
  cert="$d/fullchain.pem"
  [ -e "$cert" ] || continue
  target=$(readlink -f "$cert")
  kind="self-signed placeholder"; case "$target" in /etc/letsencrypt/*) kind="Let's Encrypt" ;; esac
  printf '  %-12s %-24s expires %s  SAN: %s\n' "$site" "$kind" \
    "$(openssl x509 -in "$cert" -noout -enddate 2>/dev/null | cut -d= -f2)" \
    "$(openssl x509 -in "$cert" -noout -ext subjectAltName 2>/dev/null | tail -n1 | sed 's/DNS://g; s/^ *//')"
done
[ "$(id -u)" -eq 0 ] && certbot certificates 2>/dev/null | grep -E 'Certificate Name|Expiry' | sed 's/^/  /'

section "Sites"
for root in /var/www/staging /var/www/production; do
  [ -d "$root" ] || continue
  site=$(basename "$root")
  echo "  $site ($root)"
  if [ -L "$root/current" ]; then
    rel=$(readlink "$root/current")
    echo "    current    $rel  (revision $(cat "$root/current/REVISION" 2>/dev/null || echo '?'))"
  else
    echo "    current    not deployed yet"
  fi
  echo "    releases   $(ls -1 "$root/releases" 2>/dev/null | wc -l) kept"
  if [ -f "$root/.dep/releases_log" ]; then
    echo "    last deploy $(tail -n1 "$root/.dep/releases_log" | tr -d '{}' | tr ',' ' ' | sed 's/"//g')"
  fi
  echo "    .env       $(test -f "$root/shared/.env" && stat -c '%U:%G %a, %y' "$root/shared/.env" || echo missing)"
  last_backup=$(ls -1t "$root"/shared/backups/*.sql.gz 2>/dev/null | head -n1)
  echo "    last dump  ${last_backup:-none}"
  [ -f "$root/shared/backups/last-backup.txt" ] && echo "    last B2    $(cat "$root/shared/backups/last-backup.txt")"
  echo "    uploads    $(du -sh "$root/shared/web/app/uploads" 2>/dev/null | awk '{print $1}')"
  echo "    php errors $(wc -l <"$root/shared/logs/php-error.log" 2>/dev/null || echo 0) lines"
done

section "Database"
if mariadb-admin ping --silent >/dev/null 2>&1; then
  try mariadb --batch --skip-column-names -e "SELECT table_schema, ROUND(SUM(data_length+index_length)/1024/1024,1) AS mb FROM information_schema.tables WHERE table_schema LIKE 'cigarholic_%' GROUP BY table_schema" | sed 's/^/  /'
else
  echo "  not reachable from $(id -un)"
fi

section "Valkey"
# No `try ... | grep` here: under pipefail the "n/a" fallback makes grep exit 1 and a read-only
# report must not fail the workflow when the cache is down.
if valkey-cli ping >/dev/null 2>&1; then
  valkey-cli info memory 2>/dev/null | grep -E '^(used_memory_human|maxmemory_human)' | sed 's/^/  /' || true
else
  echo "  not reachable from $(id -un)"
fi

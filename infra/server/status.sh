#!/usr/bin/env bash
# Prints the state of the Cigarholic VPS: OS, package versions, services, firewall, disk, memory,
# certificates and the last deploy and backup of each site (OPS-01). Read-only; runs as root or
# as the deploy user (items that need root print n/a).
set -uo pipefail

section() { printf '\n== %s\n' "$*"; }
try() { "$@" 2>/dev/null || echo "n/a"; }
# ver <command> <args...>: the command's output (stderr included: nginx and certbot print there), or "-" when it is not installed.
ver() { if command -v "$1" >/dev/null 2>&1; then "$@" 2>&1; else echo "-"; fi; }

HAS_SYSTEMD=0
[ -d /run/systemd/system ] && command -v systemctl >/dev/null 2>&1 && HAS_SYSTEMD=1

section "Host"
grep -E '^PRETTY_NAME=' /etc/os-release | cut -d= -f2 | tr -d '"'
echo "kernel  $(uname -r)"
echo "uptime  $(uptime -p 2>/dev/null || true)"
echo "time    $(date -u +%Y-%m-%dT%H:%M:%SZ) (local zone $(date +%Z))"

section "Versions"
printf '  %-10s %s\n' nginx "$(ver nginx -v | sed 's#.*/##')"
printf '  %-10s %s\n' php "$(php -v 2>/dev/null | head -n1 | awk '{print $2}')"
printf '  %-10s %s\n' mariadb "$(mariadb --version 2>/dev/null | grep -oE '[0-9]+\.[0-9]+\.[0-9]+-MariaDB' | sed -n '1s/-MariaDB//p')"
printf '  %-10s %s\n' valkey "$(valkey-server --version 2>/dev/null | awk '{print $3}' | sed 's/^v=//')"
printf '  %-10s %s\n' certbot "$(ver certbot --version | awk '{print $2}')"
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
  # Size, table count and (up to 12) table names per site database: an installed WordPress has its
  # core tables (wp_options, wp_users, ...); a database with only wp_cigarholic_signups was never
  # installed (cigarholic-core creates that table on `init`, which the installer page also runs).
  try mariadb --batch --skip-column-names -e "SELECT table_schema, ROUND(SUM(data_length+index_length)/1024/1024,1), COUNT(*), SUBSTRING_INDEX(GROUP_CONCAT(table_name ORDER BY table_name SEPARATOR ' '), ' ', 12) FROM information_schema.tables WHERE table_schema LIKE 'cigarholic_%' GROUP BY table_schema" \
    | awk -F'\t' 'NF >= 4 { printf "  %-22s %5s MB  %3s tables  %s%s\n", $1, $2, $3, $4, ($3 > 12 ? " ..." : "") } NF < 4 { print "  " $0 }'
else
  echo "  not reachable from $(id -un)"
fi

section "Web installer requests (denied by Nginx; WordPress is installed with WP-CLI only)"
# Counts by method and status from every access log, rotated ones included. A POST answered with
# 200 would mean someone ran the installer. Counts only: Actions logs of a public repository are
# public, so client addresses are not printed.
if [ "$(id -u)" -eq 0 ]; then
  for site in staging production; do
    shopt -s nullglob
    logs=(/var/log/nginx/cigarholic-"$site".access.log*)
    shopt -u nullglob
    if [ "${#logs[@]}" -eq 0 ]; then
      printf '  %-11s no access log\n' "$site"
      continue
    fi
    summary=$(zcat -f "${logs[@]}" 2>/dev/null | awk '
      index($0, "wp-admin/install.php") || index($0, "wp-admin/setup-config.php") {
        n = split($0, q, "\"")
        if (n < 3) next
        split(q[2], r, " "); split(q[3], t, " ")
        path = r[2]; sub(/\?.*/, "", path)
        if (path !~ /wp-admin\/(install|setup-config)\.php$/) next
        count[r[1] " " t[1]]++; ip[$1] = 1; total++
      }
      END {
        if (!total) { print "none"; exit }
        for (k in count) out = out sprintf("%s:%d  ", k, count[k])
        clients = 0; for (a in ip) clients++
        printf "%s(%d requests from %d clients)", out, total, clients
      }' || true)
    printf '  %-11s %s\n' "$site" "${summary:-none}"
  done
else
  echo "  n/a (needs root)"
fi

section "Valkey"
# No `try ... | grep` here: under pipefail the "n/a" fallback makes grep exit 1 and a read-only
# report must not fail the workflow when the cache is down.
if valkey-cli ping >/dev/null 2>&1; then
  valkey-cli info memory 2>/dev/null | grep -E '^(used_memory_human|maxmemory_human)' | sed 's/^/  /' || true
else
  echo "  not reachable from $(id -un)"
fi

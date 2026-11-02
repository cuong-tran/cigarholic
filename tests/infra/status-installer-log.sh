#!/usr/bin/env bash
# Checks the "Web installer requests" summary of infra/server/status.sh (OPS-07) against sample
# Nginx access-log lines in the `cigarholic` log format, plain and gzip-rotated. The awk program
# is taken verbatim from status.sh. Run: tests/infra/status-installer-log.sh
set -euo pipefail
REPO=$(cd "$(dirname "$0")/../.." && pwd)
fail() { echo "FAIL: $*"; exit 1; }

prog=$(sed -n "/summary=\$(zcat -f/,/}' || true)/p" "$REPO/infra/server/status.sh" | sed -e '1s/.*awk '"'"'//' -e '$s/'"'"' || true)$//')
[ -n "$prog" ] || fail "awk program not found in status.sh"

work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT
line() { printf '%s - - [02/Nov/2026:16:4%s:00 +0000] "%s HTTP/2.0" %s 512 "-" "test-agent" cache=- rt=0.010\n' "$1" "$2" "$3" "$4"; }
{
  line 203.0.113.7 1 "GET /wp/wp-admin/install.php?wc-api=x" 200
  line 203.0.113.7 2 "GET /wc-api/../wp/wp-admin/install.php" 200
  line 198.51.100.9 3 "GET /wp/wp-admin/install.php" 401
  line 198.51.100.9 4 "GET /" 302
  line 198.51.100.9 5 "GET /shop/?ref=wp-admin/install.php" 200
} >"$work/cigarholic-staging.access.log"
{
  line 192.0.2.1 6 "POST /wp/wp-admin/install.php?step=2" 403
  line 192.0.2.1 7 "GET /wp/wp-admin/setup-config.php" 403
} | gzip >"$work/cigarholic-staging.access.log.2.gz"
line 192.0.2.5 8 "GET /" 404 >"$work/cigarholic-production.access.log"

run() { zcat -f "$@" 2>/dev/null | awk "$prog"; }
staging=$(run "$work"/cigarholic-staging.access.log*)
production=$(run "$work/cigarholic-production.access.log")
echo "staging:    $staging"
echo "production: $production"

for want in "GET 200:2" "GET 401:1" "POST 403:1" "GET 403:1" "(5 requests from 3 clients)"; do
  [[ "$staging" == *"$want"* ]] || fail "staging summary lacks '$want'"
done
[[ "$staging" != *203.0.113.7* && "$staging" != *198.51.100.9* ]] || fail "client addresses printed"
[ "$production" = none ] || fail "production should be 'none', got '$production'"
echo "ALL PASSED"

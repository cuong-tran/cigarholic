#!/usr/bin/env bash
# Tests the changed parts of infra/server/site.sh under the same strict mode the script uses:
# random_secret (no SIGPIPE, exact length, alphanumeric) and the DB_PASSWORD rotation block
# (bad password rotated through env-upsert.sh, good password kept, other lines untouched).
# FND-08. Run from anywhere: tests/infra/site-secret.sh (CI's shell job runs it).
set -euo pipefail
REPO=$(cd "$(dirname "$0")/../.." && pwd)
SERVER_DIR="$REPO/infra/server"
fail() { echo "FAIL: $*"; exit 1; }

# Pull the function out of site.sh so the test runs exactly the shipped code.
eval "$(sed -n '/^random_secret() {/,/^}/p' "$SERVER_DIR/site.sh")"

for n in 32 64 1 4096; do
  v=$(random_secret "$n")
  [ "${#v}" -eq "$n" ] || fail "length $n gave ${#v}"
  [[ "$v" =~ ^[A-Za-z0-9]+$ ]] || fail "non-alphanumeric output for $n"
done
for _ in $(seq 1 200); do
  v=$(random_secret 32)
  [[ "$v" =~ ^[A-Za-z0-9]{32}$ ]] || fail "bad value in loop: $v"
done
echo "ok: random_secret, 204 calls under set -euo pipefail"

# The rotation block, extracted from the else-branch of step 2.
work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT
log() { echo "[log] $*"; }
block=$(sed -n '/^  DB_PASSWORD=\$(sed -n /,/^  fi$/p' "$SERVER_DIR/site.sh")
[ -n "$block" ] || fail "rotation block not found in site.sh"

# shellcheck disable=SC2034 # read by the site.sh block run through eval below
SITE=staging
ENV_FILE="$work/.env"
printf '%s\n' "# header" "DB_NAME='cigarholic_staging'" "DB_PASSWORD='ab\\cd}ef'" "AUTH_KEY='keep:me'" > "$ENV_FILE"
chmod 0640 "$ENV_FILE"
eval "$block"
new=$(sed -n "s/^DB_PASSWORD='\(.*\)'$/\1/p" "$ENV_FILE")
[[ "$new" =~ ^[A-Za-z0-9]{32}$ ]] || fail "bad password was not rotated (got '$new')"
[ "$new" = "$DB_PASSWORD" ] || fail "DB_PASSWORD variable and file disagree"
grep -qx "AUTH_KEY='keep:me'" "$ENV_FILE" || fail "other keys changed"
grep -qx "# header" "$ENV_FILE" || fail "comments changed"
[ "$(stat -c %a "$ENV_FILE")" = 640 ] || fail "mode changed"
echo "ok: non-alphanumeric DB_PASSWORD rotated, other lines and mode kept"

before=$(cat "$ENV_FILE")
eval "$block"
[ "$(cat "$ENV_FILE")" = "$before" ] || fail "good password was rewritten"
echo "ok: alphanumeric DB_PASSWORD kept on the next run"
echo "ALL PASSED"

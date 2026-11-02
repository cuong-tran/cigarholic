#!/usr/bin/env bash
# Checks the staging basic-auth exemptions and the installer deny against a real Nginx (OPS-07,
# FND-08). The map block is taken verbatim from infra/server/nginx/conf.d/cigarholic-common.conf and
# the deny location from infra/server/nginx/site.conf.tpl, so the test runs the shipped rules.
# Needs Docker (GitHub-hosted runners and the sandbox have it). Run: tests/infra/nginx-staging-auth.sh
set -euo pipefail
REPO=$(cd "$(dirname "$0")/../.." && pwd)
IMAGE=nginx:1.28.3-alpine   # the Compose image (docker-compose.yml); same 1.28 line as the VPS
PORT=${NGINX_TEST_PORT:-18089}
fail=0

command -v docker >/dev/null 2>&1 || { echo "FAIL: docker is required for this test"; exit 1; }

work=$(mktemp -d)
name="cigarholic-nginx-test-$$"
cleanup() { docker rm -f "$name" >/dev/null 2>&1 || true; rm -rf "$work"; }
trap cleanup EXIT

map_block=$(sed -n '/^map \$request_uri \$cigarholic_staging_auth {/,/^}/p' "$REPO/infra/server/nginx/conf.d/cigarholic-common.conf")
deny_line=$(grep -E '^\s*location ~ \^/\(wp/\)\?wp-admin/\(install\|setup-config\)' "$REPO/infra/server/nginx/site.conf.tpl")
[ -n "$map_block" ] || { echo "FAIL: staging auth map not found"; exit 1; }
[ -n "$deny_line" ] || { echo "FAIL: installer deny location not found"; exit 1; }

# user "u", password "p" (apr1 hash generated here, never a real credential)
printf 'u:%s\n' "$(openssl passwd -apr1 p)" >"$work/htpasswd"
mkdir -p "$work/www"
echo ok >"$work/www/ok.txt"
# Content comes from try_files + a static file, never from `return`: `return` runs in the rewrite
# phase, before auth_basic, and would hide exactly the bypasses this test looks for. The real vhost
# hands PHP to FastCGI in the content phase, after the access phase, as try_files does here.
cat >"$work/default.conf" <<EOF
$map_block

server {
    listen 80;
    server_name _;
    root /srv/www;
    default_type text/plain;
    auth_basic \$cigarholic_staging_auth;
    auth_basic_user_file /etc/nginx/test-htpasswd;

    location ^~ /.well-known/acme-challenge/ { auth_basic off; try_files /ok.txt =404; }
$deny_line
    location ~ \.php\$ { try_files /ok.txt =404; }
    location / { try_files /nonexistent /index.php\$is_args\$args; }
}
EOF
docker run -d --name "$name" -p "127.0.0.1:$PORT:80" \
  -v "$work/default.conf:/etc/nginx/conf.d/default.conf:ro" \
  -v "$work/www:/srv/www:ro" \
  -v "$work/htpasswd:/etc/nginx/test-htpasswd:ro" "$IMAGE" >/dev/null
for _ in $(seq 1 50); do
  curl -s -o /dev/null "http://127.0.0.1:$PORT/" && break
  sleep 0.2
done
docker exec "$name" nginx -t >/dev/null 2>&1 || { echo "FAIL: nginx -t"; docker logs "$name"; exit 1; }

# expect <code> <path> [user:pass]
expect() {
  local want=$1 path=$2 auth=${3:-} got
  if [ -n "$auth" ]; then
    got=$(curl -s --path-as-is -o /dev/null -w '%{http_code}' -u "$auth" "http://127.0.0.1:$PORT$path")
  else
    got=$(curl -s --path-as-is -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT$path")
  fi
  if [ "$got" = "$want" ]; then
    printf 'ok   %s %s%s\n' "$got" "$path" "${auth:+ (with credentials)}"
  else
    printf 'FAIL %s %s%s (expected %s)\n' "$got" "$path" "${auth:+ (with credentials)}" "$want"
    fail=1
  fi
}

echo "-- webhook URLs stay open without credentials"
expect 200 '/?wc-api=wc_stripe'
expect 200 '/wc-api/wc_stripe'
expect 200 '/wc-api/WC_Gateway_Paypal/'
expect 200 '/wp-json/paypal/v1/incoming'
expect 200 '/wp-json/wc-stripe/v1/webhook'
expect 200 '/wp-json/wc/v3/webhooks'
expect 200 '/wp-json/wc/v3/webhooks/12'
expect 200 '/.well-known/acme-challenge/token123'

echo "-- everything else needs credentials, including attempts to borrow an exemption"
expect 401 '/'
expect 401 '/wp/wp-login.php'
expect 403 '/wp/wp-admin/install.php?wc-api=x'   # the deny wins before auth is even asked
expect 401 '/wp/wp-login.php?wc-api=x'
expect 401 '/?wc-api=wc_stripe&rest_route=/wp/v2/users'
expect 401 '/?rest_route=/wp/v2/users&wc-api=wc_stripe'
expect 401 '/wc-api/../wp/wp-login.php'
expect 401 '/wc-api/%2e%2e/wp/wp-login.php'
expect 401 '/wc-api/x?rest_route=/wp/v2/users'
expect 401 '/wp-json/paypal/v1/../../wp/v2/users'
expect 401 '/wp-json/paypal/v1/%2e%2e/%2e%2e/wp/v2/users'
expect 401 '/wp-json/paypal/v1/incoming?rest_route=/wp/v2/users'
expect 401 '/wp-json/wc/v3/webhooksX'
expect 401 '/.well-known/acme-challenge/../../wp/wp-login.php'
expect 401 '/wp-json/wp/v2/users'

echo "-- the web installer is denied even with credentials, however it is spelled"
expect 403 '/wp/wp-admin/install.php' 'u:p'
expect 403 '/wp-admin/install.php' 'u:p'
expect 403 '/wp/wp-admin/setup-config.php' 'u:p'
expect 403 '/wp/wp-admin/./install.php' 'u:p'
expect 403 '/wp/wp-includes/../wp-admin/install.php' 'u:p'
expect 403 '/wp/wp-admin/%69nstall.php' 'u:p'
expect 200 '/wp/wp-login.php' 'u:p'

if [ "$fail" = 0 ]; then echo "ALL PASSED"; else echo "SOME CHECKS FAILED"; exit 1; fi

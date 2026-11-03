#!/usr/bin/env bash
# Checks the staging basic-auth exemptions, the hard denies (installer included), the short admin
# URL redirects and the routing of robots.txt and the XML sitemaps to PHP against a real Nginx
# (OPS-07, FND-08, SEO-01). The map block is taken verbatim from
# infra/server/nginx/conf.d/cigarholic-common.conf; the hard denies, the redirect block, and the
# virtual-file locations together with the static-asset location that follows them from
# infra/server/nginx/site.conf.tpl, in the template's order, so the test runs the shipped rules.
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

TPL="$REPO/infra/server/nginx/site.conf.tpl"
map_block=$(sed -n '/^map \$request_uri \$cigarholic_staging_auth {/,/^}/p' "$REPO/infra/server/nginx/conf.d/cigarholic-common.conf")
[ -n "$map_block" ] || { echo "FAIL: staging auth map not found"; exit 1; }
hard_denies=$(sed -n '/^    # --- hard denies/,/^$/p' "$TPL" | grep -E '^\s*location ')
printf '%s\n' "$hard_denies" | grep -qE '^\s*location ~ \^/\(wp/\)\?wp-admin/\(install\|setup-config\)' \
  || { echo "FAIL: installer deny location not found among the hard denies"; exit 1; }
redirects=$(sed -n '/^    # --- short admin URLs/,/^$/p' "$TPL" | grep -E '^\s*location ')
[ "$(printf '%s\n' "$redirects" | grep -c .)" -eq 3 ] || { echo "FAIL: short admin URL redirects not found"; exit 1; }
# From the virtual-file comment up to (not including) `location / {`: robots.txt, the sitemaps and the
# static-asset regex, which must come after them (regex locations are tried in order).
virtual_and_static=$(sed -n '/^    # --- virtual files/,/^    location \/ {/p' "$TPL" | sed '$d')
mapfile -t vlocs < <(printf '%s\n' "$virtual_and_static" | grep -E '^\s*location ')
if [ "${#vlocs[@]}" -ne 3 ] || [[ ${vlocs[0]} != *'location = /robots.txt '* ]] \
  || [[ ${vlocs[1]} != *sitemap* ]] || [[ ${vlocs[2]} != *'|txt|xml|'* ]]; then
  echo "FAIL: expected robots.txt, then the sitemap location, then the static-asset regex in site.conf.tpl"
  printf '  found: %s\n' "${vlocs[@]}"
  exit 1
fi

# user "u", password "p" (apr1 hash generated here, never a real credential)
printf 'u:%s\n' "$(openssl passwd -apr1 p)" >"$work/htpasswd"
mkdir -p "$work/www"
echo ok >"$work/www/ok.txt"
echo php >"$work/www/php.txt"                 # body of the stand-in PHP location
echo static >"$work/www/static.txt"           # a real .txt file
echo static >"$work/www/static-sitemap.xml"   # a real file with a sitemap-shaped name
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
$hard_denies
$redirects
$virtual_and_static
    location ~ \.php\$ { try_files /php.txt =404; }
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

# expect_redirect <path> <expected Location path>: a 301 whose Location (minus scheme and host) matches.
expect_redirect() {
  local path=$1 want=$2 head code location
  head=$(curl -s --path-as-is -o /dev/null -D - "http://127.0.0.1:$PORT$path")
  code=$(printf '%s\n' "$head" | awk 'NR == 1 { print $2 }')
  location=$(printf '%s\n' "$head" | tr -d '\r' | sed -n 's/^[Ll]ocation: //p')
  location=${location#http://127.0.0.1:$PORT}
  location=${location#http://127.0.0.1}
  if [ "$code" = 301 ] && [ "$location" = "$want" ]; then
    printf 'ok   301 %s -> %s\n' "$path" "$location"
  else
    printf 'FAIL %s %s -> %s (expected 301 -> %s)\n' "$code" "$path" "$location" "$want"
    fail=1
  fi
}

# expect_body <body> <path>: HTTP 200 (with the test credentials) and the first line of the body,
# which tells the stand-in PHP location ("php") from a file Nginx served directly ("static").
expect_body() {
  local want=$1 path=$2 out code body
  out=$(curl -s --path-as-is -u u:p -w '\n%{http_code}' "http://127.0.0.1:$PORT$path")
  code=${out##*$'\n'}
  body=$(printf '%s\n' "${out%$'\n'*}" | head -n 1)
  if [ "$code" = 200 ] && [ "$body" = "$want" ]; then
    printf 'ok   200 %s from %s\n' "$path" "$want"
  else
    printf 'FAIL %s %s from "%s" (expected 200 from %s)\n' "$code" "$path" "$body" "$want"
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

echo "-- short admin URLs redirect into /wp/ (before basic auth: the target still asks for it)"
expect_redirect '/wp-login.php' '/wp/wp-login.php'
expect_redirect '/wp-login.php?action=lostpassword' '/wp/wp-login.php?action=lostpassword'
expect_redirect '/wp-admin' '/wp/wp-admin/'
expect_redirect '/wp-admin?x=1' '/wp/wp-admin/?x=1'
expect_redirect '/wp-admin/' '/wp/wp-admin/'
expect_redirect '/wp-admin/edit.php?post_type=product' '/wp/wp-admin/edit.php?post_type=product'
expect 403 '/wp-admin/install.php'                 # the installer deny still wins
expect 401 '/wp-adminx'                            # not an admin path: no redirect, auth as usual
expect 200 '/wp/wp-login.php' 'u:p'                # the real login page is not redirected again
expect 200 '/wp/wp-admin/index.php' 'u:p'

echo "-- robots.txt and the sitemaps reach PHP (WordPress builds them), never Nginx's 404"
expect_body php '/robots.txt'
expect_body php '/robots.txt?x=1'
expect_body php '/sitemap.xml'                               # WordPress 7.1: the core index
expect_body php '/wp-sitemap.xml'
expect_body php '/wp-sitemap-posts-page-1.xml'
expect_body php '/wp-sitemap-taxonomies-product_cat-1.xml'
expect_body php '/sitemaps.xml'                              # SEOPress index
expect_body php '/page-sitemap1.xml'
expect_body php '/product_cat-sitemap1.xml'
expect_body php '/sitemap_index.xml'                         # SEOPress redirects it to sitemaps.xml
expect_body php '/wp-sitemap.xsl'                            # stylesheets: no static extension
expect_body php '/wp-sitemap-index.xsl'
expect_body php '/sitemaps_xsl.xsl'

echo "-- real files are still served directly, and other .txt/.xml stay static"
expect_body static '/static.txt'
expect_body static '/static-sitemap.xml'
expect 404 '/missing.txt' 'u:p'
expect 404 '/missing.xml' 'u:p'
expect 404 '/app/robots.txt' 'u:p'                         # only the root robots.txt is virtual
expect 404 '/app/uploads/page-sitemap1.xml' 'u:p'          # only root-level sitemap names

echo "-- staging basic auth still applies, and the hard denies still win"
expect 401 '/robots.txt'
expect 401 '/wp-sitemap.xml'
expect 401 '/sitemaps.xml'
expect 403 '/license.txt' 'u:p'
expect 403 '/wp/license.txt' 'u:p'
expect 403 '/sitemaps.xml/../license.txt' 'u:p'
expect 403 '/robots.txt/../wp/readme.html' 'u:p'
expect 403 '/.git/sitemap.xml' 'u:p'
expect 403 '/.robots.txt' 'u:p'
expect 403 '/wp/xmlrpc.php' 'u:p'

if [ "$fail" = 0 ]; then echo "ALL PASSED"; else echo "SOME CHECKS FAILED"; exit 1; fi

#!/usr/bin/env bash
# Brings up the Cigarholic runtime (docker-compose.yml), installs WordPress, provisions and seeds it.
# Idempotent: re-running updates the stack and re-applies provisioning without touching content.
#
#   scripts/runtime-up.sh [--no-seed] [--no-build] [--build-only]
#
# Environment (all optional): HTTP_PORT (8080), MAILPIT_PORT (8025), WP_ADMIN_USER (admin),
# WP_ADMIN_PASSWORD (admin), WP_ADMIN_EMAIL (dev@cigarholic.test), CA_BUNDLE_FILE (sandbox proxy CA,
# auto-detected), HTTPS_PROXY / NO_PROXY (passed into the build and the php container).
set -euo pipefail

cd "$(dirname "$0")/.."

export COMPOSER_ALLOW_SUPERUSER=1
HTTP_PORT=${HTTP_PORT:-8080}
MAILPIT_PORT=${MAILPIT_PORT:-8025}
SITE_URL=${WP_HOME:-http://localhost:${HTTP_PORT}}
ADMIN_USER=${WP_ADMIN_USER:-admin}
ADMIN_PASSWORD=${WP_ADMIN_PASSWORD:-admin}
ADMIN_EMAIL=${WP_ADMIN_EMAIL:-dev@cigarholic.test}
SEED=1
BUILD=1
BUILD_ONLY=0
for arg in "$@"; do
	case "$arg" in
		--no-seed) SEED=0 ;;
		--no-build) BUILD=0 ;;
		--build-only) BUILD_ONLY=1 ;;
		*) echo "unknown option: $arg" >&2; exit 2 ;;
	esac
done

log() { printf '\n==> %s\n' "$*"; }

# 1. Docker. In the Claude Code sandbox the daemon is not running at start; dockerd itself works.
if ! docker info >/dev/null 2>&1; then
	if command -v dockerd >/dev/null 2>&1; then
		log "starting dockerd"
		nohup dockerd >/tmp/dockerd.log 2>&1 &
		for _ in $(seq 1 60); do docker info >/dev/null 2>&1 && break; sleep 1; done
	fi
	docker info >/dev/null 2>&1 || { echo "Docker is not available; see docs/runbooks/sandbox.md" >&2; exit 1; }
fi

# 2. Sandbox: TLS egress is intercepted by a gateway. Its CA goes to the image build (secret) and is
# mounted into the php container; the proxy variables are only needed by the build.
if [ -z "${CA_BUNDLE_FILE:-}" ] && [ -s /root/.ccr/ca-bundle.crt ]; then
	cp /root/.ccr/ca-bundle.crt docker/ca/proxy.crt
	export CA_BUNDLE_FILE=./docker/ca/proxy.crt
fi
# Persist the CA path for Compose's variable substitution (it reads .env too), so a plain
# `docker compose up` after a daemon restart keeps the CA mounted. Written after .env exists (step 3).
persist_ca_setting() {
	[ -n "${CA_BUNDLE_FILE:-}" ] && [ -f .env ] || return 0
	if grep -q '^CA_BUNDLE_FILE=' .env; then
		sed -i "s|^CA_BUNDLE_FILE=.*|CA_BUNDLE_FILE='${CA_BUNDLE_FILE}'|" .env
	else
		printf "\n# Compose only (sandbox): CA of the TLS-intercepting gateway, mounted into the php container\nCA_BUNDLE_FILE='%s'\n" "${CA_BUNDLE_FILE}" >> .env
	fi
}
export HTTPS_PROXY="${HTTPS_PROXY:-${https_proxy:-}}" NO_PROXY="${NO_PROXY:-${no_proxy:-}}" HTTP_PORT MAILPIT_PORT
PHP_VERSION=${PHP_VERSION:-8.5.12}
export PHP_VERSION

# The PHP image is built with plain `docker build`: it needs the host network to reach the sandbox proxy
# and the proxy CA as a build secret, which Compose's bake builder refuses without entitlements.
build_php_image() {
	local args=(--network host --build-arg "PHP_VERSION=${PHP_VERSION}" --build-arg "HTTPS_PROXY=${HTTPS_PROXY}" --build-arg "NO_PROXY=${NO_PROXY}" -t "cigarholic/php:${PHP_VERSION}")
	if [ -n "${CA_BUNDLE_FILE:-}" ]; then
		args+=(--secret "id=ca_bundle,src=${CA_BUNDLE_FILE}")
	fi
	docker build "${args[@]}" docker/php
}

# 3. .env with random salts (never committed).
if [ ! -f .env ]; then
	log "creating .env from .env.example"
	cp .env.example .env
	for key in AUTH_KEY SECURE_AUTH_KEY LOGGED_IN_KEY NONCE_KEY AUTH_SALT SECURE_AUTH_SALT LOGGED_IN_SALT NONCE_SALT; do
		# `|| true`: head closes the pipe early and tr exits 141, which pipefail would turn into a failure.
		salt=$(LC_ALL=C tr -dc 'A-Za-z0-9' </dev/urandom | head -c 64 || true)
		sed -i "s|^${key}=.*|${key}='${salt}'|" .env
	done
	sed -i "s|^WP_HOME=.*|WP_HOME='${SITE_URL}'|" .env
fi
persist_ca_setting

# 4. Dependencies on the host (bind-mounted into the containers).
if [ ! -f vendor/autoload.php ] || [ ! -f web/wp/wp-settings.php ]; then
	log "composer install"
	composer install --no-interaction --no-progress --prefer-dist
fi
[ -x tools/phpunit.phar ] || scripts/tools.sh
mkdir -p web/app/uploads
chmod -R a+rwX web/app/uploads

# 5. The stack.
if [ "$BUILD" = 1 ]; then
	log "building the PHP image"
	build_php_image
	docker compose pull --ignore-buildable --quiet
fi
if [ "$BUILD_ONLY" = 1 ]; then
	echo "images built and pulled"
	exit 0
fi
log "docker compose up"
docker compose up -d --wait --wait-timeout 300

wp() { docker compose exec -T php wp "$@"; }

log "waiting for the database"
for _ in $(seq 1 60); do wp db check >/dev/null 2>&1 && break; sleep 2; done
wp db check >/dev/null

# 6. WordPress.
if ! wp core is-installed >/dev/null 2>&1; then
	log "installing WordPress at ${SITE_URL}"
	wp core install --url="${SITE_URL}" --title="Cigarholic" --admin_user="${ADMIN_USER}" \
		--admin_password="${ADMIN_PASSWORD}" --admin_email="${ADMIN_EMAIL}" --skip-email
fi

log "activating plugins"
# WooCommerce first: its extensions refuse to activate without it.
wp plugin activate woocommerce >/dev/null
wp plugin activate --all >/dev/null
wp redis enable >/dev/null 2>&1 || true

# Test database for PHPUnit (tests/phpunit/wp-tests-config.php).
docker compose exec -T db mariadb -uroot -p"${DB_ROOT_PASSWORD:-root}" \
	-e "CREATE DATABASE IF NOT EXISTS cigarholic_test; GRANT ALL ON cigarholic_test.* TO '${DB_USER:-cigarholic}'@'%'; FLUSH PRIVILEGES;"
if [ -d web/app/themes/cigarholic ]; then
	wp theme activate cigarholic >/dev/null 2>&1 || true
else
	echo "note: theme web/app/themes/cigarholic is not in this checkout yet; the front end has no theme"
fi

# 7. Development only: WooCommerce's built-in Cash on delivery gateway, so cart -> order -> email can be
# exercised without payment keys (ADR 0004). Provisioning never enables it on staging or production.
wp option patch insert woocommerce_cod_settings enabled yes >/dev/null 2>&1 || wp option update woocommerce_cod_settings '{"enabled":"yes","title":"Cash on delivery (development)","instructions":"Development gateway, no payment is taken.","enable_for_methods":[],"enable_for_virtual":"yes"}' --format=json >/dev/null

# 8. Provisioning and seed data from cigarholic-core, once they exist.
if wp cli has-command "cigarholic provision" >/dev/null 2>&1; then
	log "wp cigarholic provision"
	wp cigarholic provision
else
	echo "note: 'wp cigarholic provision' is not available yet (FND-03)"
fi
if [ "$SEED" = 1 ]; then
	if wp cli has-command "cigarholic seed" >/dev/null 2>&1; then
		log "wp cigarholic seed"
		wp cigarholic seed
	else
		echo "note: 'wp cigarholic seed' is not available yet"
	fi
fi

# 9. Smoke check.
log "smoke check"
code=$(curl -sS -o /dev/null -w '%{http_code}' "${SITE_URL}/wp/wp-login.php" || true)
if [ "$code" != "200" ]; then
	echo "wp-login.php returned ${code}; docker compose logs web php" >&2
	exit 1
fi
wp core version
wp plugin list --status=active --field=name | tr '\n' ' '; echo

cat <<MSG

Store:   ${SITE_URL}
Admin:   ${SITE_URL}/wp/wp-admin/  (${ADMIN_USER} / ${ADMIN_PASSWORD})
Mail:    http://localhost:${MAILPIT_PORT}
WP-CLI:  make wp ARGS="option get blogname"
MSG

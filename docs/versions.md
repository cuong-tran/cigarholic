# Versions (pinned)

What runs where, and when it was last tested together. Same PHP minor on CI, staging and production, always. No `x.0` release of WordPress or WooCommerce on production; wait for the first point release. Upgrades arrive as PRs tested on staging; no major version jump without a documented compatibility check.

| Component | Version | Where pinned | Tested | Notes |
|---|---|---|---|---|
| WordPress | 7.1.4 | `composer.lock` (`roots/wordpress`) | 2026-10-30 (install) | 7.2 is in beta; wait for 7.2.1 |
| WooCommerce | 11.2.1 | `composer.lock` | 2026-10-30 (install) | |
| PHP (VPS, Compose, CI) | 8.5 (image `php:8.5.12-fpm-trixie`; Ubuntu 26.04 archive 8.5.4) | `docker-compose.yml`, `docker/php/Dockerfile`, `infra/` | 2026-10-30 (runtime) | Same minor everywhere; gate before M2, fallback 8.4 via ondrej PPA (ADR 0001). WP-CLI 2.12's own libraries emit 8.5 deprecations |
| PHP (sandbox host, lint only) | 8.3.6 | `composer.json` platform | 2026-10-30 | Code stays 8.3-compatible |
| MariaDB | 11.8 (image 11.8.9; Ubuntu 26.04 archive 11.8.6) | `docker-compose.yml`, `infra/` | 2026-10-30 (runtime) | |
| Valkey | 9.0 (image `valkey/valkey:9.0.7-alpine`; Ubuntu 26.04 archive 9.0.4) | `docker-compose.yml`, `infra/` | 2026-10-30 (runtime) | Redis protocol; phpredis 6.3.0 client |
| Nginx | 1.28 (image 1.28.3-alpine; Ubuntu 26.04 archive 1.28.3) | `docker-compose.yml`, `docker/nginx`, `infra/` | 2026-10-30 (runtime) | |
| Node (tooling) | 22 | `.nvmrc` | 2026-10-30 | eslint 9.39, stylelint 16.26 in `package-lock.json` |
| Playwright | 1.63.0 | `package-lock.json` | 2026-10-31 (smoke suite green in the sandbox) | Pinned to the sandbox image's Chromium 1243; CI installs Chromium with `playwright install` |
| Composer | 2.8.12 | sandbox image, `docker/php/Dockerfile` | 2026-10-30 | |
| Mailpit | v1.32.0 | `docker-compose.yml`, `docker/php/Dockerfile` (sendmail shim) | 2026-10-30 (runtime) | Development only |
| WP-CLI | 2.12.0 | `docker/php/Dockerfile` (from `wordpress:cli-2.12.0-php8.5`), sandbox | 2026-10-30 | |
| PHPStan | 2.3.4 | `scripts/tools.sh` | 2026-10-30 | phar; `szepeviktor/phpstan-wordpress` 2.0.x, `php-stubs/woocommerce-stubs` 11.2.x |
| PHPUnit | 9.6.38 | `scripts/tools.sh` | 2026-10-30 (6 tests green in the container) | phar; the WordPress test suite (`wp-phpunit/wp-phpunit` 7.1.3) still uses PHPUnit 9 APIs; `yoast/phpunit-polyfills` 4.0 |
| Deployer | 8.0.5 | `scripts/tools.sh` | 2026-10-30 | phar |
| PHP_CodeSniffer + WPCS | 3.x + 3.4.1 | `composer.lock` | 2026-10-30 | PHPCompatibilityWP 2.1.x |

Plugin versions: see `docs/plugins.md` (all pinned exactly in `composer.lock`).

## Upgrade routine

1. `composer update <package>` (or edit `scripts/tools.sh` for a phar) on a `chore/deps-<date>` branch.
2. `composer check`, `make up`, `make test`, `make e2e`; read the plugin changelog for HPOS or checkout changes.
3. PR, CI green, merge, staging deploys; run the Playwright suite against staging.
4. Update this table (version + tested date) and `CHANGELOG.md`.

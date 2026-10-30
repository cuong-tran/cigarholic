# Versions (pinned)

What runs where, and when it was last tested together. Same PHP minor on CI, staging and production, always. No `x.0` release of WordPress or WooCommerce on production; wait for the first point release. Upgrades arrive as PRs tested on staging; no major version jump without a documented compatibility check.

| Component | Version | Where pinned | Tested | Notes |
|---|---|---|---|---|
| WordPress | 7.1.4 | `composer.lock` (`roots/wordpress`) | 2026-10-30 (install) | 7.2 is in beta; wait for 7.2.1 |
| WooCommerce | 11.2.1 | `composer.lock` | 2026-10-30 (install) | |
| PHP (VPS, Compose, CI) | 8.5 | `docker/php/Dockerfile`, `infra/` | pending (FND-02) | Ubuntu 26.04 archive version; gate before M2, fallback 8.4 via ondrej PPA (brief section 4) |
| PHP (sandbox host, lint only) | 8.3.6 | `composer.json` platform | 2026-10-30 | Code stays 8.3-compatible |
| MariaDB | 11.8 | `docker-compose.yml`, `infra/` | pending (FND-02) | Ubuntu 26.04 archive version |
| Valkey | to pin in FND-02 | `docker-compose.yml`, `infra/` | pending | Same minor as the Ubuntu 26.04 package |
| Nginx | to pin in FND-02 | `docker/nginx`, `infra/` | pending | Same minor as the Ubuntu 26.04 package |
| Node (tooling) | 22 | `.nvmrc` (FND-06) | 2026-10-30 | Sandbox image |
| Playwright | to pin in FND-06 | `package-lock.json` | pending | Sandbox image has 1.63.0 with Chromium 1243 |
| Composer | 2.8 | sandbox image | 2026-10-30 | |
| WP-CLI | 2.12 | Docker image, sandbox | 2026-10-30 | |
| PHPStan | 2.3.4 | `scripts/tools.sh` | 2026-10-30 | phar; `szepeviktor/phpstan-wordpress` 2.0.x, `php-stubs/woocommerce-stubs` 11.2.x |
| PHPUnit | 11.5.57 | `scripts/tools.sh` | 2026-10-30 | phar; `wp-phpunit/wp-phpunit` 7.1.3, `yoast/phpunit-polyfills` |
| Deployer | 8.0.5 | `scripts/tools.sh` | 2026-10-30 | phar |
| PHP_CodeSniffer + WPCS | 3.x + 3.4.1 | `composer.lock` | 2026-10-30 | PHPCompatibilityWP 2.1.x |

Plugin versions: see `docs/plugins.md` (all pinned exactly in `composer.lock`).

## Upgrade routine

1. `composer update <package>` (or edit `scripts/tools.sh` for a phar) on a `chore/deps-<date>` branch.
2. `composer check`, `make up`, `make test`, `make e2e`; read the plugin changelog for HPOS or checkout changes.
3. PR, CI green, merge, staging deploys; run the Playwright suite against staging.
4. Update this table (version + tested date) and `CHANGELOG.md`.

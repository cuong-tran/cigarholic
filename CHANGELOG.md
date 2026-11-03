# Changelog

All notable changes to Cigarholic. Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); entries name their feature IDs from `docs/FEATURES.md`. Releases are Git tags deployed to production after the owner's approval.

## [Unreleased]

### Fixed

- OPS-07: no login name reaches a visitor after a fresh install. `?author=N` no longer redirects to `/author/<login>/`, author archives answer 404, author links on the front end point at the home page, the core users sitemap is gone, and feeds (`dc:creator`) and oEmbed cards show the site name while a user's display name is still the login. Found on production right after the `v0.1.0` install: `/?author=1`, `/feed/` and `/wp-json/oembed/1.0/embed` named the administrator.
- FND-03: provisioning (`20-pages`) trashes the installer's untouched "Hello world!" post, which was public in the feed, the REST API and oEmbed during coming-soon, together with its sample comment.
- CNT-04: `cigarholic-core` no longer touches the signups table while WordPress is being installed (`init` runs during `wp core install`, before `wp_options` exists; seen as a database error in the production install log and in the 2026-11-02 staging review).

## [0.1.0] - 2026-11-03

First release, for the coming-soon site on `cigarholic.com` (milestone M1): the WooCommerce store built and provisioned as code, running whole-site coming-soon with email capture; the store itself stays hidden from visitors until the owner switches it to Live. Verified on staging first: WordPress installed with WP-CLI, 11 launch plugins active, provisioning applied and idempotent, seven `SAMPLE-` products for review (never on production). Tagged by the owner on 2026-11-03 at `854a25a` (#43) and deployed to production the same day: release 1, `wp core install`, release 2 (11 plugins active, 77 provisioning changes, health HTTP 200).

### Added

- OPS-07 (#43): `/wp-login.php` and `/wp-admin[/...]` redirect (301) to the same path under `/wp/`, where WordPress lives, on both sites and in the Compose runtime; query strings kept, `/wp-admin/install.php` still denied. Covered by `tests/infra/nginx-staging-auth.sh` (41 cases) and `tests/e2e/admin-urls.spec.js`.
- OPS-01 (#37): `infra-provision.yml` → `status` lists the tables of each site database and counts web-installer requests by method and status from the access logs (counts only, no client addresses); `tests/infra/status-installer-log.sh`.
- OPS-06 (#33): monitoring runbook `docs/runbooks/monitoring.md` (what is monitored and why, the keyword that survives the coming-soon phase, alert routing to the owner, what to do per alert, the server-side signals, UI steps for the owner), `infra/monitoring/uptimerobot.sh` (idempotent UptimeRobot v2 script with `--dry-run` and `--list`, secrets never on a command line or in the log) and the `monitoring.yml` workflow (`environment`, `dry_run`, `list`); optional `UPTIMEROBOT_ALERT_CONTACTS` variable. The API calls wait for the owner's key (OWNER-ACTIONS B3).
- OPS-04 (#32): staging deploys switched on with the marker file `infra/deploy/STAGING_ENABLED`; every push to `main` that touches the application deploys `staging.cigarholic.com`.
- CAT-03 / CAT-02 / CAT-04 / CAT-09 / CAT-10 / CMP-06 / CMP-11 / INV-01 (#27): catalog data model (categories with fixed slugs, `occasion` taxonomy, eight global attributes, Specifications tab with `_ch_*` meta, private compliance fields, Prop 65 toggle), SKU scheme validation, `cigarholic/spec-table` block (in + cm, lb + kg), CSV import template and `docs/catalog.md`, `wp cigarholic catalog check` and `catalog ensure`, `wp cigarholic seed` (seven `SAMPLE-` products). Storefront smoke test now covers browse → cart → checkout → order email.
- FND-03: provisioning steps 10–80 (#14): WooCommerce general settings with HPOS and block pages, checkout rules, Shopify-compatible permalinks, nine draft policy/content pages plus home and journal, Primary navigation, Shop Manager capabilities, shipping classes and zones (contiguous US free humidors, placeholder accessory rate; Alaska/Hawaii/territories unshippable), tax settings for Stripe Tax, branded emails, whole-site coming-soon. Context helpers `page()`, `term()`, `navigation()`; RolesTest and ProvisionTest.
- FND-13 (security subset, #15): Dependabot opens PRs for WordPress core and plugin releases daily (grouped), npm tooling and GitHub Actions weekly.
- FND-01: Bedrock scaffold with WordPress 7.1.4, WooCommerce 11.2.1 and the launch plugin set pinned in `composer.lock`; environment configs; `.env.example`; `scripts/tools.sh` for the pinned PHPStan, PHPUnit and Deployer phars (#4).
- FND-04: `AGENTS.md`, `CLAUDE.md`, issue and PR templates, this changelog, `docs/plugins.md`, `docs/versions.md`, ADRs 0001 to 0008.
- FND-06: CI on every PR: PHP 8.3 + 8.5 lint, PHPCS, PHPStan, Composer audit, eslint, stylelint, shellcheck, actionlint (#6).
- FND-02: Compose runtime pinned to the VPS minors (PHP 8.5-FPM image with phpredis, nginx 1.28, MariaDB 11.8, Valkey 9.0, Mailpit), `scripts/runtime-up.sh`, `Makefile`, `wp-cli.yml`, runbook `docs/runbooks/runtime.md`.
- FND-12: Playwright smoke suite (`tests/e2e/`: coming-soon page and signup for visitors, store for admins, storefront browse → cart → Cash-on-delivery checkout → order email once the catalog is seeded) on desktop and mobile projects; CI `runtime` job builds the PHP image, brings the store up, runs PHPUnit in the container and the Playwright suite (PHP 8.5 on PRs, 8.4 too on main).
- CNT-04 / MKT-01: coming-soon email capture: `cigarholic/signup-form` block (Turnstile, honeypot, rate limit, REST endpoint plus no-JavaScript form fallback), deduplicated storage in `wp_cigarholic_signups`, WooCommerce > Signups page with CSV download, `wp cigarholic signups count|list|export|delete`.
- NAV-02: announcement bar: WooCommerce > Announcement bar settings (text, link, start and end in the site timezone) rendered by the `cigarholic/announcement-bar` block in the theme header.
- FND-05: block theme `cigarholic` (#8) and the `cigarholic-core` mu-plugin skeleton: loader, feature registry with `CIGARHOLIC_FEATURES` toggles, logger, `wp cigarholic version|features|provision`, provisioning runner with the first step (`00-core`), development feature (valid mail sender, system CA for HTTPS in the sandbox), hardening feature (OPS-07: XML-RPC off, no generator tags, generic login errors, no anonymous user listing), PHPUnit harness on the WordPress test suite.
- Docs: `docs/OWNER-ACTIONS.md`, `docs/STATUS.md`, runbooks for the sandbox and the secrets inventory; `docs/FEATURES.md` reconciled with the kickoff brief (#3).

### Fixed

- Docs (#43): the manager guide said two-factor is required; the Two Factor plugin is active but enrolment is not enforced yet (OPS-07, next actions).
- FND-08 / OPS-01 (#31), from the first real runs on the VPS: `infra-provision.yml` reads `LETSENCRYPT_EMAIL` from the repository secret or the variable; the deploy sudoers file no longer sets `requiretty` (sudo on Ubuntu 26.04 rejects it, which stopped `provision.sh` at the visudo check); `status.sh` prints `-` for tools that are not installed yet.
- FND-08 (#34): the first staging deploy failed its health check with HTTP 500 because MariaDB rejected the password in `shared/.env`: `random_secret` in `site.sh` had a `}` inside its `${2:-...}` default, so `:,.<>?-}` was appended to every charset and the `?-}` range added the backslash, which MariaDB reads as an escape and PHP literally. Secrets are now alphanumeric; `site.sh` rotates a non-alphanumeric `DB_PASSWORD` and re-asserts the database user (both sites were affected and rotated). `wp` on the server is a wrapper that silences WP-CLI 2.12's PHP 8.5 deprecations for the CLI only.
- FND-03 (#41): staging reported `blog_public = 1`: `DISALLOW_INDEXING` was defined for staging and development but nothing read it (the Bedrock plugin that does is not installed). Provisioning now sets Settings > Reading > search engine visibility from the constant; production stays visible. Staging was never indexable in practice (basic auth and `X-Robots-Tag: noindex`).
- FND-08 (#40): the deploy activates the plugins (WooCommerce first, then every plugin the `--no-dev` release installed) before `wp wc update`; the first deploy after `wp core install` on staging failed with "'wc' is not a registered wp command" because a fresh install activates none.
- FND-08 (#35): the #34 generator (`tr </dev/urandom | head -c`) exited 141 under `pipefail`; it now reads a finite 4 KiB and loops. `tests/infra/site-secret.sh` runs the shipped code under strict mode in CI.

### Security

- FND-08 (#39): `wp-cli.yml` loads the owner email and initial password from secrets only, and only for a command that references them; `infra-provision.yml` reads `LETSENCRYPT_EMAIL` from the secret only. A repository variable's value is printed in the logs of this public repository.
- OPS-07 / FND-08 (#36, 2026-11-02): the staging basic-auth exemptions for payment webhooks matched URI prefixes, so `/wp/wp-admin/install.php?wc-api=x` and `/wc-api/../wp/wp-admin/install.php` reached the WordPress installer of the freshly deployed, not yet installed staging site without credentials (verified from outside, HTTP 200). Staging was taken offline within minutes (the `current` symlink removed; no install had happened, the database was still empty). The exemptions now match whole URLs with no `.`, `%` or extra query string, `install.php` and `setup-config.php` are denied on both sites, and `tests/infra/nginx-staging-auth.sh` checks 31 cases against a real Nginx in CI. Evidence afterwards (`status`, #37): the staging database held only `wp_cigarholic_signups` (WordPress never installed) and the access logs show five installer requests, all GET, all from the verification probes; no POST.

### Not yet implemented

Payments and tax (PAY-01 to PAY-06, CMP-01; wait for the Stripe and PayPal test credentials), transactional mail through Postmark (B1), off-site backups (B2), monitoring (OPS-06), SEOPress, Complianz and GA4 provisioning, ship-time messaging, the manager handbook sections, accessibility checks. The ordered list is in `docs/STATUS.md`.

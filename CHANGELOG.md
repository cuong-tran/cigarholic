# Changelog

All notable changes to Cigarholic. Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); entries name their feature IDs from `docs/FEATURES.md`. Releases are Git tags deployed to production after the owner's approval.

## [Unreleased]

### Added

- FND-01: Bedrock scaffold with WordPress 7.1.4, WooCommerce 11.2.1 and the launch plugin set pinned in `composer.lock`; environment configs; `.env.example`; `scripts/tools.sh` for the pinned PHPStan, PHPUnit and Deployer phars (#4).
- FND-04: `AGENTS.md`, `CLAUDE.md`, issue and PR templates, this changelog, `docs/plugins.md`, `docs/versions.md`, ADRs 0001 to 0008.
- FND-06: CI on every PR: PHP 8.3 + 8.5 lint, PHPCS, PHPStan, Composer audit, eslint, stylelint, shellcheck, actionlint (#6).
- FND-02: Compose runtime pinned to the VPS minors (PHP 8.5-FPM image with phpredis, nginx 1.28, MariaDB 11.8, Valkey 9.0, Mailpit), `scripts/runtime-up.sh`, `Makefile`, `wp-cli.yml`, runbook `docs/runbooks/runtime.md`.
- FND-12: Playwright smoke suite (`tests/e2e/`: coming-soon page and signup for visitors, store for admins, storefront browse → cart → Cash-on-delivery checkout → order email once the catalog is seeded) on desktop and mobile projects; CI `runtime` job builds the PHP image, brings the store up, runs PHPUnit in the container and the Playwright suite (PHP 8.5 on PRs, 8.4 too on main).
- CNT-04 / MKT-01: coming-soon email capture: `cigarholic/signup-form` block (Turnstile, honeypot, rate limit, REST endpoint plus no-JavaScript form fallback), deduplicated storage in `wp_cigarholic_signups`, WooCommerce > Signups page with CSV download, `wp cigarholic signups count|list|export|delete`.
- NAV-02: announcement bar: WooCommerce > Announcement bar settings (text, link, start and end in the site timezone) rendered by the `cigarholic/announcement-bar` block in the theme header.
- FND-05: block theme `cigarholic` (#8) and the `cigarholic-core` mu-plugin skeleton: loader, feature registry with `CIGARHOLIC_FEATURES` toggles, logger, `wp cigarholic version|features|provision`, provisioning runner with the first step (`00-core`), development feature (valid mail sender, system CA for HTTPS in the sandbox), hardening feature (OPS-07: XML-RPC off, no generator tags, generic login errors, no anonymous user listing), PHPUnit harness on the WordPress test suite.
- Docs: `docs/OWNER-ACTIONS.md`, `docs/STATUS.md`, runbooks for the sandbox and the secrets inventory; `docs/FEATURES.md` reconciled with the kickoff brief (#3).

### Not yet implemented

Everything that serves a page: runtime (FND-02), CI (FND-06), the `cigarholic-core` mu-plugin (FND-05), the theme, provisioning, catalog model, infrastructure and tests. See `docs/STATUS.md`.

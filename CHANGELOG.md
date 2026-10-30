# Changelog

All notable changes to Cigarholic. Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); entries name their feature IDs from `docs/FEATURES.md`. Releases are Git tags deployed to production after the owner's approval.

## [Unreleased]

### Added

- FND-01: Bedrock scaffold with WordPress 7.1.4, WooCommerce 11.2.1 and the launch plugin set pinned in `composer.lock`; environment configs; `.env.example`; `scripts/tools.sh` for the pinned PHPStan, PHPUnit and Deployer phars (#4).
- FND-04: `AGENTS.md`, `CLAUDE.md`, issue and PR templates, this changelog, `docs/plugins.md`, `docs/versions.md`, ADRs 0001 to 0008.
- FND-06: CI on every PR: PHP 8.3 + 8.5 lint, PHPCS, PHPStan, Composer audit, eslint, stylelint, shellcheck, actionlint (#6).
- FND-02: Compose runtime pinned to the VPS minors (PHP 8.5-FPM image with phpredis, nginx 1.28, MariaDB 11.8, Valkey 9.0, Mailpit), `scripts/runtime-up.sh`, `Makefile`, `wp-cli.yml`, runbook `docs/runbooks/runtime.md`.
- Docs: `docs/OWNER-ACTIONS.md`, `docs/STATUS.md`, runbooks for the sandbox and the secrets inventory; `docs/FEATURES.md` reconciled with the kickoff brief (#3).

### Not yet implemented

Everything that serves a page: runtime (FND-02), CI (FND-06), the `cigarholic-core` mu-plugin (FND-05), the theme, provisioning, catalog model, infrastructure and tests. See `docs/STATUS.md`.

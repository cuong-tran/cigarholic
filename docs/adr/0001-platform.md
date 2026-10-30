# ADR 0001: Platform

Status: accepted, 2026-10-30. Feature IDs: FND-01, FND-02, FND-08, OPS-01, OPS-08.

## Context

The owner decided the stack in `docs/KICKOFF-BRIEF.md` section 4: WordPress + WooCommerce, latest stable (7.1.4 / 11.2.1 on 2026-10-30), Roots Bedrock layout, PHP 8.5 on an Ubuntu 26.04 VPS, MariaDB 11.8, Valkey, Nginx, Deployer atomic releases, Docker Compose as the only runtime for sandbox, CI and laptops, no server panel, no DDEV. A non-technical manager runs the store; AI agents do all development, so everything must be reproducible from the repository.

## Decision

- **Bedrock layout.** `composer.json` pins WordPress core (`roots/wordpress`), every plugin (wpackagist) and the dev tooling exactly; `composer.lock` is committed. Custom PHP lives in the mu-plugin `cigarholic-core`, presentation in the block theme `cigarholic`, settings in `provision/` (applied by `wp cigarholic provision` after every deploy).
- **PHP 8.5 at runtime, 8.3-compatible syntax.** The VPS runs Ubuntu 26.04's PHP 8.5; the Compose image and CI use the same minor; the sandbox host has 8.3 and runs only lint, PHPCS, PHPStan and Composer, so the code uses no 8.4+ syntax. Gate before M2: if any active plugin fails or logs deprecations on the checkout path under 8.5, pin 8.4 from the ondrej PPA and record it here.
- **Compose runtime** (`docker-compose.yml`, `docker/`, `scripts/runtime-up.sh`, `Makefile`) pinned to the VPS versions is the one way to run the store outside the servers.
- **Raw VPS provisioned by idempotent scripts** in `infra/`, run from GitHub Actions `workflow_dispatch` jobs, because the sandbox has no SSH. Staging and production are two sites on one VPS with separate DB, DB user, PHP-FPM pool and `.env`.
- **Composer-managed updates, in-place auto-updates off.** `AUTOMATIC_UPDATER_DISABLED` and `DISALLOW_FILE_MODS` are set. Core, plugin and theme updates arrive as Composer PRs tested on staging and deployed as atomic releases.

## Consequences

- The brief asks for "WordPress minor releases auto-update". With Composer-managed core and atomic releases, an in-place core update would be reverted by the next deploy and would not reach `composer.lock`, so in-place updates are disabled. Instead, a scheduled workflow (FND-13, brought forward for core security releases) checks `api.wordpress.org` daily and opens a Composer PR for new WordPress point releases and plugin security releases; CI runs; an agent merges; staging deploys; the owner tags production. Target: security releases on production within 48 hours. The owner can overrule this (then core would leave Composer management); recorded as an open item in `docs/STATUS.md`.
- No `x.0` release of WordPress or WooCommerce on production; wait for the first point release.
- Everything an agent needs to continue is in the repository (`AGENTS.md`, `docs/STATUS.md`, runbooks).

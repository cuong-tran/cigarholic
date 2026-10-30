# Cigarholic

WooCommerce store for handcrafted wooden humidors, cigar boxes and humidor accessories, made in Vietnam and sold to US customers. No cigars, tobacco or nicotine products are sold.

Roots Bedrock layout: WordPress core, plugins and the theme are Composer dependencies; all custom PHP lives in the mu-plugin `web/app/mu-plugins/cigarholic-core`, presentation in the block theme `web/app/themes/cigarholic`, settings as code in `provision/`.

## Start here

| Document | Purpose |
|---|---|
| `CLAUDE.md` / `AGENTS.md` | Rules and commands for agents (condensed brief, coding standards) |
| `docs/STATUS.md` | Where the build is, what to do next, how to reproduce the current state |
| `docs/FEATURES.md` | Scope of record: every feature with a stable ID and priority |
| `docs/KICKOFF-BRIEF.md` | The full brief: stack, architecture, build order, approval gates |
| `docs/OWNER-ACTIONS.md` | What the project needs from the owner |
| `docs/runbooks/` | Sandbox, deploy, rollback, backup and restore, secrets inventory |

## Run it

Requires Docker with Compose, PHP 8.3+ and Composer on the host (lint and dependency resolution only; the store runs in containers).

```bash
make up
```

brings up the Compose stack (PHP 8.5-FPM, Nginx, MariaDB 11.8, Valkey, Mailpit), installs WordPress, runs provisioning and seeds sample data. The store is at http://localhost:8080 and trapped mail at http://localhost:8025. `make check` runs the same lint, PHPCS and PHPStan checks as CI.

## Layout

```text
composer.json / composer.lock      WordPress, WooCommerce, plugins, dev tools
config/                             Bedrock configuration per environment (.env is never committed)
web/app/mu-plugins/cigarholic-core  custom PHP, one class per feature ID
web/app/themes/cigarholic           block theme
provision/                          idempotent settings applied by `wp cigarholic provision`
scripts/                            runtime-up.sh, seed data, helpers
docker/ docker-compose.yml Makefile the local and CI runtime
infra/                              cloud setup, server provisioning, Deployer, Cloudflare, backups
.github/workflows/                  CI, deploys, server and DNS jobs
tests/                              PHPUnit (cigarholic-core) and Playwright
docs/                               scope, status, runbooks, ADRs, manager guide
```

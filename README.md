# Cigarholic

An online store for handcrafted wooden humidors, cigar boxes and humidor accessories, made in Vietnam and sold by HuyLinh LLC to customers in the United States. **No cigars, tobacco or nicotine products are sold.**

It is a WordPress + WooCommerce site. A non-technical store manager runs products, orders and content in WP admin; the owner holds the accounts and approves every production release; AI coding agents do the development and maintenance through pull requests.

## State (2026-11-13)

- **Live:** `https://cigarholic.com` shows a coming-soon page that collects email signups (release `v0.4.0`).
- **Working behind it**, with sample products: catalog with specification tables, cart, block checkout, order emails, tracking numbers and the shipping email.
- **Not yet:** real payments, tax, the real catalog, off-site backups, monitoring, delivered email.
- **Target:** open the store in January 2027.

Today's detail is in [`docs/STATUS.md`](docs/STATUS.md).

## How it is built

Roots Bedrock layout: WordPress core and every plugin are Composer dependencies pinned in `composer.lock`. All custom PHP lives in one mu-plugin, `cigarholic-core`, with one class per feature and a toggle for each. Presentation is a small block theme, `cigarholic`; there is no page builder. Settings are code: `wp cigarholic provision` applies them after every deploy and changes nothing on a second run. The server is one Ubuntu 26.04 VPS (Nginx, PHP-FPM 8.5, MariaDB 11.8, Valkey) hosting staging and production, provisioned and deployed only by GitHub Actions workflows. A push to `main` deploys staging; a version tag approved by the owner deploys production.

More in [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md).

## Run it

Requires Docker with Compose, plus PHP 8.3 or newer, Composer and Node 22 on the host (for linting and dependency resolution; the store itself runs in containers).

```bash
scripts/tools.sh && composer install && npm ci
```

```bash
make up
```

`make up` starts the stack, installs WordPress, applies the settings and seeds seven sample products. Store: http://localhost:8080 (admin / admin). Trapped mail: http://localhost:8025.

```bash
make check && make test && make e2e
```

runs what CI runs: lint, coding standard and static analysis; PHPUnit inside the PHP container; the Playwright suite.

## Documents

| Read this | To find out |
|---|---|
| [`AGENTS.md`](AGENTS.md), [`CLAUDE.md`](CLAUDE.md) | The rules every agent follows, the commands, the coding standards |
| [`docs/STATUS.md`](docs/STATUS.md) | Where the build is today, what is blocked, the open risks |
| [`docs/HANDOVER.md`](docs/HANDOVER.md) | How to start and end a session, how to check production, what trips people up |
| [`docs/PLAN.md`](docs/PLAN.md) | The next work packages, in order, with what "done" means |
| [`docs/ROADMAP.md`](docs/ROADMAP.md) | Milestones, dates and what each depends on |
| [`docs/FEATURES.md`](docs/FEATURES.md) | The scope of record: 219 features with stable IDs and priorities |
| [`docs/OWNER-ACTIONS.md`](docs/OWNER-ACTIONS.md) | What the project needs from the owner |
| [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) | How the store works and is put together; the review findings |
| [`docs/DECISIONS.md`](docs/DECISIONS.md), [`docs/adr/`](docs/adr/) | Every decision and its reason |
| [`docs/TESTING.md`](docs/TESTING.md) | How it is tested, what is covered and what is not |
| [`docs/CHECKLIST.md`](docs/CHECKLIST.md) | What must pass before a merge, a release and go-live |
| [`docs/runbooks/`](docs/runbooks/) | Operating the servers: deploy, rollback, backup, secrets, monitoring |
| [`docs/manager-guide.md`](docs/manager-guide.md), [`docs/catalog.md`](docs/catalog.md) | Running the store from WP admin; the product data model |
| [`docs/plugins.md`](docs/plugins.md), [`docs/versions.md`](docs/versions.md) | Every plugin and why; pinned versions |
| [`CHANGELOG.md`](CHANGELOG.md), [`docs/history/`](docs/history/) | What each release contains; merged PRs, server runs, test results |
| [`docs/KICKOFF-BRIEF.md`](docs/KICKOFF-BRIEF.md), [`docs/LAUNCH-PLAN.md`](docs/LAUNCH-PLAN.md) | The owner's original brief and launch plan |

## Layout

```text
composer.json / composer.lock       WordPress, WooCommerce, plugins, dev tools (pinned)
config/                             Bedrock configuration per environment (.env is never committed)
web/app/mu-plugins/cigarholic-core  all custom PHP: features, blocks, email templates
web/app/themes/cigarholic           block theme: tokens, templates, parts, patterns
provision/                          settings as code, applied by `wp cigarholic provision`
scripts/                            runtime-up.sh, tools.sh, sample photo renderer
docker/ docker-compose.yml Makefile the runtime for development and CI
infra/                              server provisioning, Deployer recipe, backups, Cloudflare, monitoring
.github/workflows/                  CI, deploys, server jobs
tests/                              PHPUnit, Playwright, infrastructure shell tests
docs/                               everything in the table above
```

## Rules in one paragraph

Never commit a secret (the repository is public). Never edit production by hand or deploy it without the owner's tag and approval. No page builders, no plugin without a row in `docs/plugins.md`, 15 active plugins at most. WooCommerce is the system of record. Never weaken a test to get green. Never imply that tobacco is sold or that anything is made in the USA. One pull request per feature ID.

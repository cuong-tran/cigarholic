# AGENTS.md — rules for every agent working on Cigarholic

Cigarholic is a WooCommerce store selling handcrafted wooden humidors, cigar boxes and humidor accessories, made in Vietnam, sold to US consumers by HuyLinh LLC. **No cigars, tobacco or nicotine products are sold, ever.** Never add age gates, tobacco categories or wording that implies tobacco is sold or that anything is made in the USA ("Handcrafted in Vietnam").

This file condenses `docs/KICKOFF-BRIEF.md` (the full brief, which wins on *how to build*) and `docs/FEATURES.md` (the scope of record, which wins on *what and in which order*). Read `docs/STATUS.md` first in every session: it says where the build is and what to do next.

## Non-negotiables

- Never commit secrets, `.env` files, dumps or uploads. Secret names live in `docs/runbooks/secrets.md`; values only in GitHub. Never ask for a secret in chat or write one to a doc. The repository is public.
- Never edit production directly, never run live payment keys anywhere, never deploy to production without the owner's tag approval, never run destructive database operations on staging or production, never delete backups.
- No page builders. Block theme + patterns only. Block cart and checkout + HPOS; never the classic shortcode cart/checkout.
- No plugin without a row in `docs/plugins.md` and a justification in the PR. Budget: 15 active plugins or fewer.
- WooCommerce is the system of record. No runtime dependency on Shopify or any external system; every sync is a module behind a toggle (SHO-01).
- Never weaken a test, skip a check or disable a security control to get green. Never bypass an environment restriction (proxy, permissions) to force something through.
- Never rewrite or force-push history; never renumber or reuse feature IDs; keep `docs/FEATURES.md` history.
- Honesty: say "implemented", "tested" or "deployed" only when you did it and verified it. Every report separates **executed and passed / executed and failed / could not run here (why) / not implemented**.

## How work flows

1. **One PR per feature ID.** Branch `feat/<ID>-<slug>` (`feat/CAT-04-spec-table`), Conventional Commits with the ID as scope (`feat(CAT-04): ...`, `fix(PAY-05): ...`, `docs(FND-04): ...`). A scaffold PR may carry several IDs: name it by the primary one and list the rest. Fill in `.github/PULL_REQUEST_TEMPLATE.md` fully, with test notes and screenshots for anything visible.
2. **CI must be green.** While there is no production site, merge your own PRs once green (squash, Conventional Commit title). Once staging exists, merging to `main` deploys staging. Production deploys only from a tag the owner approves.
3. **`main` is always deployable.** Ship finished, safe parts; put unfinished parts behind a feature toggle in `cigarholic-core` and list them as pending in the PR, `CHANGELOG.md` and `docs/STATUS.md`.
4. **Do not wait on the owner for anything you can default.** Defaults D-01 to D-10 are in `docs/OWNER-ACTIONS.md`. Batch questions there (group A/B/C), document assumptions in `docs/STATUS.md`, keep building.
5. **Commit and push at least hourly and before ending a session.** Only what is pushed survives the sandbox.
6. **Definition of done:** code + provisioning (if settings changed) + tests + docs (`docs/plugins.md`, `docs/versions.md`, `docs/manager-guide.md` where relevant) + `CHANGELOG.md` + `docs/STATUS.md`.
7. **End of every session:** update `docs/STATUS.md` (milestone, live/staging/in progress/blocked, tests and results, decisions and why, open owner items, next actions, reproduce commands) and `docs/OWNER-ACTIONS.md` if anything is needed from the owner.

## Commands

```bash
scripts/tools.sh            # pinned phars: PHPStan, PHPUnit, Deployer (tools/, gitignored)
composer install            # WordPress, plugins, dev tooling (COMPOSER_ALLOW_SUPERUSER=1 as root)
composer check              # validate + lint + PHPCS + PHPStan, exactly what CI runs
composer lint | phpcs | phpcbf | phpstan | test
make up                     # Compose stack up, WordPress installed, provisioned, seeded (scripts/runtime-up.sh)
make seed | make provision | make down | make wp ARGS="option get blogname"
make test                   # PHPUnit inside the PHP container
make e2e                    # Playwright smoke suite against the Compose stack
```

The store runtime is the Docker Compose stack (PHP 8.5-FPM, Nginx, MariaDB 11.8, Valkey, Mailpit) at http://localhost:8080 with trapped mail at http://localhost:8025. Host PHP is for lint, PHPCS, PHPStan and Composer only. WP-CLI runs inside the `php` container.

## Layout

```text
composer.json / composer.lock             WordPress core, plugins, dev tooling (pinned)
config/                                    Bedrock environments; .env never committed
web/app/mu-plugins/cigarholic-core/        all custom PHP: PSR-4 `Cigarholic\Core\`, one class per feature ID
web/app/mu-plugins/cigarholic-core.php     loader for the mu-plugin (committed)
web/app/themes/cigarholic/                 block theme: theme.json, templates, parts, patterns; presentation only
provision/                                 idempotent settings steps run by `wp cigarholic provision`
scripts/                                   runtime-up.sh, tools.sh, seed data, helpers
docker/ docker-compose.yml Makefile        the runtime for sandbox, CI and laptops
infra/                                     cloud-setup.sh, server provisioning (Ubuntu 26.04), Deployer, Cloudflare, backups
.github/workflows/                         ci.yml, deploy-staging.yml, deploy-production.yml, infra-provision.yml, cloudflare.yml
tests/phpunit/ tests/e2e/                  PHPUnit for cigarholic-core; Playwright smoke tests
docs/                                      FEATURES, KICKOFF-BRIEF, STATUS, OWNER-ACTIONS, plugins, versions, adr/, runbooks/
```

## Coding standards

- **PHP:** WordPress Coding Standards (`phpcs.xml.dist`, tabs, Yoda conditions, snake_case functions, `array()`), PHPStan level 6+ (`phpstan.neon.dist`), **8.3-compatible syntax** (the sandbox host runs 8.3; the VPS runs 8.5). Namespaces `Cigarholic\Core\<Area>\<Feature>`; every class docblock names its feature ID. Text domain `cigarholic`, every user-facing string wrapped for i18n, English UI.
- **Security:** sanitize input, escape output, nonce + capability check on every admin, AJAX and REST action. Least privilege. Never log card data, API keys, tokens or PII beyond what an order needs.
- **Commerce:** WooCommerce CRUD APIs only, no raw SQL on commerce tables, no `WP_Query` for orders (HPOS). Declare HPOS compatibility. Respect stock reservations and status transitions. Payment truth comes from gateway webhooks, never from the browser redirect.
- **Side effects are idempotent:** assume duplicate webhooks, retries, concurrent checkouts and partial failures. Idempotency keys + Action Scheduler for anything with side effects.
- **No empty abstractions:** no gateway-independent payment layer, no interface with one implementation. Smallest code that works with supported APIs. Prefer, in order: Core → Config → Plugin → Custom.
- **Feature toggles:** `Cigarholic\Core\Features` reads defaults from code and overrides from `CIGARHOLIC_FEATURES` (`.env`). Unfinished work ships off.
- **Logging:** `wc_get_logger()` with source `cigarholic`; never `error_log` in production paths.
- **Theme:** `theme.json` tokens, templates and patterns; no PHP logic beyond `functions.php` setup. WCAG 2.2 AA from the first template, Core Web Vitals "good" on mobile is a budget.
- **Seed data** is clearly sample data: SKU prefix `SAMPLE-`, "Sample" in titles; `wp cigarholic catalog:check` fails on production while any exists.
- **Catalog:** URLs `/products/<slug>/`, `/collections/<slug>/`, `/journal/<slug>/`; SKU scheme `CH-{type}-{capacity}-{wood}-{finish}`; only SKU-changing attributes become variations (ADR 0002, 0003).
- **Shell:** `set -euo pipefail`, shellcheck clean; workflows actionlint clean. Every script takes an explicit environment (`staging` / `production`) and refuses to run without one; no hard-coded hosts or paths outside documented config.

## Approval gates

Proceed without asking: repo setup, code, tests, config as code, staging deploys, server provisioning and Cloudflare changes once access exists (report what changed), docs, refactoring.

Stop and ask: spending money or creating paid accounts; live payment keys; deploying to production; destructive DB operations on staging or production; deleting backups or anything irreversible; legal, tax or financial assumptions (document them as open items instead); switching nameservers or changing existing MX/TXT records.

## Portability

Everything lives in the repo. A fresh agent in a new session, on a laptop or on the VPS must be able to clone, read this file and `docs/STATUS.md`, and continue. If the sandbox VM expired: `git status`, read `docs/STATUS.md`, run `scripts/runtime-up.sh`, continue from the next action listed there.

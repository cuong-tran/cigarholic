# Status

Progress tracker for the Cigarholic build (`docs/KICKOFF-BRIEF.md` rule 6). Updated at the end of every session. A fresh agent, in a new cloud session or on a laptop, starts here after reading `CLAUDE.md` (which imports `AGENTS.md`).

Last updated: 2026-10-31, Turn 2 (Phase 0 nearly complete; see "Where we are").

## Where we are

| | |
|---|---|
| Current milestone | **Phase 0, Foundation**, one PR from done: FND-01/02/03/04/05/06/08/12/13, CNT-03, CNT-04, NAV-02 merged; CAT-03 (catalog model, spec table, CSV template, seed, catalog check) in review. **M0 "Repo green"** is reached when it lands and the storefront smoke test runs against seeded data |
| Live on production | Nothing. No server provisioned yet, domains on registrar hold (`docs/OWNER-ACTIONS.md` A5) |
| Live on staging | Nothing. Provisioning and deploy workflows exist (#10) and have been dry-run in a container; the first real run needs the owner's go after M0 |
| In progress | CAT-03 catalog data model (subagent, branch `feat/CAT-03-catalog-data-model`) |
| Blocked (Phase 1) | `infra-provision.yml` → `status` (run 41157126002) reached the VPS but the pinned host key in `SSH_KNOWN_HOSTS` does not match the server after its OS reinstall; waits for the owner to refresh the secret (OWNER-ACTIONS A1). Everything DNS/TLS also waits for A5 |
| Blocked | Phase 1 (M1) waits for A5 (domain hold) for anything DNS or TLS related; the VPS itself can be provisioned by IP as soon as the owner confirms Phase 1 may start |

### Merged so far (all squash-merged to `main`, CI green)

| PR | Feature IDs | What |
|---|---|---|
| #3 | docs | FEATURES reconciled with the brief, OWNER-ACTIONS, this file, cloud setup script |
| #4 | FND-01, FND-09 | Bedrock scaffold: WordPress 7.1.4, WooCommerce 11.2.1, 11 launch plugins pinned, phars via `scripts/tools.sh` |
| #5 | FND-04, FND-10, FND-11 | AGENTS.md / CLAUDE.md, templates, changelog, plugin register, versions, ADRs 0001–0008 |
| #6 | FND-06 | CI: PHP 8.3 + 8.5 lint, PHPCS, PHPStan, audit, eslint, stylelint, shellcheck, actionlint |
| #7 | FND-02 | Compose runtime pinned to the VPS minors, `scripts/runtime-up.sh`, Makefile |
| #8 | FND-05, CNT-01, NAV-01..04, CAT-06, CHK-06 | Block theme `cigarholic` (13 templates, 10 patterns, coming-soon template) |
| #9 | FND-05, FND-03, FND-12, OPS-07 | `cigarholic-core` skeleton: features and toggles, CLI, provisioning runner, hardening, PHPUnit harness |
| #10 | FND-08, FND-07, OPS-01..09 | Server provisioning (Ubuntu 26.04), Deployer recipe, Cloudflare and backup scripts, deploy/provision workflows, runbooks |
| #11 | CNT-04, MKT-01, NAV-02 | Coming-soon email capture (Turnstile, REST, CSV), announcement bar, manager guide started |
| #12 | FND-12 | Playwright smoke suite and the CI runtime job (install, provision, PHPUnit, Playwright) |
| #13 | CNT-03 | Contact form block emailed to the support inbox, Turnstile, REST + no-JS fallback, settings page |
| #14 | FND-03, CHK-01/03/04/05, SEO-03, CNT-02, NAV-01, ORD-02/03/04/07, SHP-01/02, CMP-01, CNT-04 | Provisioning steps 10–80: WooCommerce settings incl. HPOS, checkout, permalinks, draft pages, navigation, Shop Manager role, shipping zones, tax, emails, whole-site coming-soon |
| #15 | FND-13 | Dependabot for Composer (daily, grouped), npm and GitHub Actions |
| #13 | CNT-03 | Contact form block emailed to the support inbox, Turnstile, REST + no-JS fallback, settings page |
| #14 | FND-03, CHK-01/03/04/05, SEO-03, CNT-02, NAV-01, ORD-02/03/04/07, SHP-01/02, CMP-01, CNT-04 | Provisioning steps 10–80: WooCommerce settings incl. HPOS, checkout, permalinks, draft pages, navigation, Shop Manager role, shipping zones, tax, emails, whole-site coming-soon |
| #15 | FND-13 | Dependabot for Composer (daily, grouped), npm and GitHub Actions |

## Environment report (sandbox, measured 2026-10-30 and 2026-10-31; details in `docs/runbooks/sandbox.md`)

**Works**

| Item | Finding |
|---|---|
| VM | Ubuntu 24.04.5, x86_64, 4 vCPU, 15 GB RAM, about 30 GB writable disk, root |
| Host PHP | 8.3.6 with mysqli, pdo_mysql, gd, intl, mbstring, xml, zip, curl, bcmath, redis, igbinary, sodium, OPcache (no imagick in this image). Used for lint, PHPCS, PHPStan and dependency resolution only |
| Composer | 2.8.12. `COMPOSER_ALLOW_SUPERUSER` is not set in the environment, so scripts export it |
| WP-CLI | present at `/usr/local/bin/wp`; needs `--allow-root` or `WP_CLI_ALLOW_ROOT=1` |
| Node | 22.22.0, npm 10.9.4 |
| Docker | 29.9.2, Compose v5.6.1. Daemon not running at start; `nohup dockerd &` works, ready in about 3 s. `/etc/docker/daemon.json` already points at `mirror.gcr.io`; `alpine:3.20` pulled in 2.8 s and ran. Manifests exist for `php:8.5-fpm`, `mariadb:11.8`, `valkey/valkey:8` and `:9`, `nginx:1.28`, `axllent/mailpit`, `ubuntu:26.04` |
| Playwright | 1.63.0 with Chromium 1243 under `/opt/pw-browsers`; `require('playwright')` works |
| Linters | shellcheck 0.9 and actionlint 1.7.12 present (installed by the previous setup script), Go 1.24.7 |
| Other | gh 2.89 (REST only), jq, ripgrep, Python 3.13, Redis 7.0.15 server binary (not running) |
| Timeouts | `BASH_DEFAULT_TIMEOUT_MS=600000`, `BASH_MAX_TIMEOUT_MS=1800000` set |
| Network | Effectively **Full**: `downloads.wordpress.org`, `api.wordpress.org` (reports WP 7.1.4 and WooCommerce 11.2.1 as latest), `wordpress.org`, `wpackagist.org`, `repo.packagist.org` (Bedrock 1.31.8 latest), `registry.npmjs.org`, `cdn.playwright.dev`, `getcomposer.org`, `deployer.org`, `raw.githubusercontent.com`, Docker Hub, `mirror.gcr.io`, `public.ecr.aws`, `api.cloudflare.com`, `api.postmarkapp.com`, `api.stripe.com`, `archive.ubuntu.com` (HTTP) all answer; so do unrelated hosts (`example.org`, `httpbin.org`, `pypi.org`). **Nothing needs allowlisting** |
| GitHub | Clone, push, PR creation (GitHub MCP), reading runs and job logs (`gh api`), `workflow_dispatch` through MCP (per the earlier session) work. PR merge through the API: see "Tests executed" below |

**Does not work, or absent, by design**

| Item | Finding | Consequence |
|---|---|---|
| SSH | No `ssh` binary and no raw TCP egress (port 22 to 1.1.1.1 refused) | Every server operation is a GitHub Actions `workflow_dispatch` job; the sandbox never talks to the VPS |
| MariaDB on the host | Not installed in this image (the earlier session's image had 10.11) | The store runtime is the Docker Compose stack, as the brief wants; host fallback would be `apt install mariadb-server` |
| `gh pr ...`, GraphQL | Blocked by the proxy; `gh auth status` even reports the token as invalid, yet `gh api repos/...` works | Use the GitHub MCP tools and REST |
| Environments and Actions-settings API | 403 "not permitted through this proxy" | The owner answers OWNER-ACTIONS A4 |
| `cigarholic.com`, `max2lab.com` | NXDOMAIN: both domains are on registrar `clientHold` (RDAP, iNET Software, registered 2026-10-03) | OWNER-ACTIONS A5; M1 impossible until lifted |

## Tests executed this session (2026-10-30 / 31)

- **Executed and passed:**
  - Static checks on every PR, locally and in CI: `composer check` (parallel-lint, PHPCS WordPress-Extra + Docs + PHPCompatibilityWP, PHPStan level 6), `npm run lint` (eslint, stylelint), shellcheck, actionlint. CI green on PRs 6 to 12 (PHP 8.3 and 8.5 legs).
  - Runtime in the sandbox: image build through the proxy, `make up` on `main` (WordPress 7.1.4 installed, 12 plugins active, theme active, provisioning applied 66 changes then 0, HPOS enabled, 9 draft pages + home/journal, Primary navigation, Cash on delivery in development), Valkey object cache connected, mail trapped by Mailpit, WordPress HTTP API reaching Turnstile and wordpress.org through the gateway CA; anonymous `/` is the coming-soon page with one signup form and no navigation.
  - PHPUnit in the php container (`make test`): 33 tests on `main` (features/toggles, hardening, signups, announcement bar, contact form, roles, provisioning idempotency); also green in the CI runtime job on every PR.
  - Playwright (`make e2e`): 6 passed on desktop and mobile (coming-soon page for visitors with a real signup through the Turnstile test widget, duplicate accepted, invalid refused, administrator sees the store); 4 storefront tests skip until the catalog is seeded.
  - Theme render on the stack: `/`, `/shop/`, `/cart/`, `/my-account/` 200 with the right h1, `/nonexistent/` 404, empty `/checkout/` 302, coming-soon template for anonymous visitors.
  - Infrastructure dry runs: full `provision.sh` + `site.sh staging|production` in an `ubuntu:26.04` container (idempotent second run; nginx 1.28.3, PHP 8.5.4, MariaDB 11.8.6, Valkey 9.0.4), Deployer recipe parsing (`list`, `tree deploy|rollback`, `config`), Cloudflare scripts `--dry-run`.
  - FEATURES priority recount after the reconciliation; RDAP and DNS checks of both domains.
- **Executed and failed, then fixed:** PHPUnit 11 against the WordPress test suite (moved to 9.6); Composer source clones of PHPStan/PHPUnit too large for the sandbox (phars instead, ADR 0007); PEAR's HTTP/1.0 download of phpredis (curl + `docker-php-ext-install`); Compose bake entitlements (plain `docker build`); container TLS without the gateway CA (CA mounted, `https_ssl_verify` in development); `woocommerce_store_pages_only` defaulting to `yes` (provisioning pins `no`).
- **Executed and failed (expected, owner action):** `infra-provision.yml` → `status` on 2026-10-31: SSH host key mismatch against the pinned `SSH_KNOWN_HOSTS` (server reinstalled after the scan); the workflow refused to connect, which is the intended behaviour of host-key pinning.
- **Could not run here:** anything on the VPS beyond that check (no SSH from the sandbox by design): provisioning, Deployer end to end, Let's Encrypt, backups to B2; Stripe/PayPal test checkout (staging only, test keys not yet provided); the storefront smoke test end to end (needs seeded products from CAT-03); axe/Lighthouse budgets.
- **Not implemented yet (Phase 0 leftovers):** CAT-03 catalog model in review; ORD-05 full manager guide (grows per feature), OPS-06 monitoring runbook.

## Decisions made, and why

1. **Branches:** one `feat/<ID>-<slug>` branch and PR per feature ID, cut from `origin/main` after the previous merge (a branch cut from an unmerged branch shows duplicate commits in its PR after a squash merge; never force-push to fix that, cut a new branch instead).
2. **Dev tools as phars** (ADR 0007): the sandbox can fetch GitHub packages only by cloning; PHPStan and PHPUnit repositories are gigabytes. PHPUnit stays on 9.6 because the WordPress test suite (`wp-phpunit` 7.1.3) still calls PHPUnit 9 APIs.
3. **Composer-managed updates only** (ADR 0001): in-place WordPress auto-updates are off; a scheduled update-PR workflow replaces them (FND-13 brought forward for security releases, to do). The owner can overrule.
4. **Containers trust the sandbox gateway CA** instead of using proxy variables (TLS from containers is intercepted transparently); WordPress uses the system store in development through `https_ssl_verify`.
5. **Staging deploys are gated** on the marker file `infra/deploy/STAGING_ENABLED` so pushes to `main` do not fail until the server exists; production deploys run from a tag with the `production` Environment approval (public repository), the tag-input manual run is the fallback.
6. **Cloudflare is additive** (ADR 0008): the origin alone serves TLS (Let's Encrypt), the `www`/`max2lab` redirects and the rate limits; Cloudflare scripts exist for later.
7. **Cash on delivery in development only** exercises cart → order → email without payment keys (ADR 0004); Stripe/PayPal test mode is staging-only.
8. **Signups are stored by `cigarholic-core`** (own table, CSV export) until the email platform is decided (D-04).
9. **Runtime pins** match the Ubuntu 26.04 archive minors (PHP 8.5, MariaDB 11.8, Valkey 9.0, nginx 1.28), verified in a container.

## Open owner decisions and inputs

All in `docs/OWNER-ACTIONS.md`: A1 (confirmed), A2 (Cloudflare deferred), A3 (done), A4 (answered: GitHub Free, repository secrets, repository public), A5 (domain hold, pending), A6 (`LETSENCRYPT_EMAIL`, new); B1 to B12 within two weeks; C1 to C5 before go-live. Defaults D-01 to D-10 are in force. Server provisioning by IP is pre-approved by the brief ("server provisioning once I have given access") and starts as soon as Phase 0 closes; the owner is told what changed.

## Next actions

1. Merge `feat/CAT-03-catalog-data-model` (review, CI, the storefront smoke test then runs against seeded data); then record **M0** here.
2. Phase 1 (server access exists, so it starts without waiting): `infra-provision.yml` → `status` (verifies the SSH secrets), `provision`, then `site-staging` and `site-production` (TLS stays self-signed until A5 is lifted and the owner enters the A records from `docs/runbooks/server.md`); commit `infra/deploy/STAGING_ENABLED` to switch on staging deploys; first deploy; coming-soon page live on `cigarholic.com` once DNS exists (M1). Postmark (B1), backups (B2), monitoring (B3) as the accounts arrive.
3. Phase 0 leftovers in parallel: CNT-03 contact form, scheduled WordPress/plugin update PRs (FND-13 security subset), monitoring runbook (OPS-06), manager guide sections as features land.
4. Phase 2 (P0 store on staging): payments and checkout (PAY, CHK) once B4/B5 test credentials exist, then catalog, storefront, shipping and tax, operations, content/SEO/compliance, in the order of brief section 8.

## Reproduce and validate the current state

```bash
git clone https://github.com/cuong-tran/cigarholic.git && cd cigarholic && scripts/tools.sh && COMPOSER_ALLOW_SUPERUSER=1 composer install && npm ci
```

```bash
make up          # Compose stack, WordPress installed and provisioned; http://localhost:8080 (admin/admin), mail at :8025
```

```bash
make check && make test && make e2e
```

```bash
make wp ARGS="cigarholic features" && make wp ARGS="cigarholic provision --dry-run"
```

## If the session is reopened after the VM expired

`git status`, read this file, `scripts/tools.sh && composer install`, `make up`, then continue from "Next actions". If the Docker daemon is down after a restart, `make up` starts it and recreates the containers with the gateway CA (do not use a bare `docker compose up` in the sandbox).

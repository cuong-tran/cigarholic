# Status

Progress tracker for the Cigarholic build (`docs/KICKOFF-BRIEF.md` rule 6). Updated at the end of every session. A fresh agent, in a new cloud session or on a laptop, starts here after reading `CLAUDE.md` (which imports `AGENTS.md`).

Last updated: 2026-11-02, Turn 3: **Phase 1 in progress.** M0 reached 2026-10-31. The VPS is provisioned and both sites have certificates. Staging is installed, provisioned and seeded for review. Release `v0.1.0` (the coming-soon site on `cigarholic.com`, M1) waits for the owner's tag and approvals (OWNER-ACTIONS A7).

## Where we are

| | |
|---|---|
| Current milestone | **M0 "Repo green" reached** (2026-10-31). **M1 "Coming-soon live" in progress** (2026-11-02): VPS provisioned by `infra-provision.yml` (nginx 1.28.3, PHP 8.5.4, MariaDB 11.8.6, Valkey 9.0.4, certbot 4.0.0), `site-staging` and `site-production` done, Let's Encrypt certificates issued for `staging.cigarholic.com` and for `cigarholic.com` + `www` + both `max2lab.com` hosts (valid to 2027-01-31), staging deploys switched on (#32) |
| Live on production | **Edge only.** `https://cigarholic.com` answers through Nginx with a valid certificate (HTTP 404 until the first release is deployed and WordPress installed); `http://` → `https://`, `www.cigarholic.com` and both `max2lab.com` hosts 301 to `https://cigarholic.com` in one hop keeping the path; `xmlrpc.php` 403; HSTS on. No application deployed: production deploys only from an owner-approved tag |
| Live on staging | **Installed and provisioned** (release 6 of `main`, run 41493330262). WordPress installed with WP-CLI (admin user `owner`, the B8 secrets); 11 launch plugins active; provisioning applied (76 changes on the first run, dry run 0 afterwards, then 1 for `blog_public`); whole-site coming-soon on, HPOS on, Valkey object cache on, search engine visibility off; 7 `SAMPLE-` products (2 variations) for review; health check HTTP 200. Basic auth on everything except the exact webhook URLs, `X-Robots-Tag: noindex`, web installer denied |
| In progress | **M1, waiting for the owner (A7):** review staging, then tag `v0.1.0` → production deploy (approval) → `wp core install` on production (approval) → second deploy (approval) → coming-soon live on `cigarholic.com`. Phase 2 (payments, checkout) waits for Stripe/PayPal test credentials (B4, B5) |
| Blocked (owner) | **A7** release `v0.1.0`: the tag and three approvals. Then B6 Turnstile keys (forms run without a bot challenge until then), B1 Postmark, B2 backups, B3 monitoring, B4/B5 payments |

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
| #16–#20, #24–#26 | FND-13 | Dependabot updates merged after green CI (GitHub Actions majors, eslint 10, stylelint 17); #23 keeps `@playwright/test` pinned to the sandbox browser |
| #22 | docs | STATUS and OWNER-ACTIONS after the first Phase 1 check (host-key mismatch) |
| #27 | CAT-03, CAT-02, CAT-04, CAT-09, CAT-10, CMP-06, CMP-11, INV-01 | Catalog data model, spec table, CSV template, `wp cigarholic catalog check`, seed data; storefront smoke test active |
| #30 | docs | M0 status report |
| #31 | FND-08, OPS-01 | First real VPS runs: `LETSENCRYPT_EMAIL` as a secret, sudoers `requiretty` removed (Ubuntu 26.04 sudo rejects it), `status.sh` output on a clean server |
| #32 | OPS-04 | Staging deploys switched on (`infra/deploy/STAGING_ENABLED`) |
| #33 | OPS-06 | Monitoring runbook, `infra/monitoring/uptimerobot.sh` and `monitoring.yml` (UptimeRobot v2 API, dry run by default; waits for B3) |
| #34 | FND-08 | Server secrets alphanumeric (the old generator put backslashes in DB passwords, which broke the first deploy); bad `DB_PASSWORD` rotated by `site.sh`; WP-CLI wrapper on the server |
| #35 | FND-08 | `random_secret` without SIGPIPE (#34's version exited 141 under `pipefail`); `tests/infra/site-secret.sh` in CI |
| #36 | OPS-07, FND-08 | Security: staging basic-auth bypass closed, web installer denied on both sites; `tests/infra/nginx-staging-auth.sh` (31 cases) in CI |
| #37 | OPS-01 | `status` lists tables per database and counts web-installer requests from the access logs |
| #38 | docs | Phase 1 status, the staging incident write-up |
| #39 | FND-08 | Owner credentials and the Let's Encrypt contact from secrets only, never printed in the public logs |
| #40 | FND-08 | The deploy activates the plugins (WooCommerce first, then every plugin of the `--no-dev` release) before `wp wc update` |
| #41 | FND-03 | Search engine visibility follows `DISALLOW_INDEXING` (staging off, production on) |
| #42 | docs | Release notes `v0.1.0`, this status |

## Server (Phase 1, 2026-11-02)

All runs of `infra-provision.yml` on the Hostinger VPS (`srv2109040`, Ubuntu 26.04.1, 4 vCPU class, 15 GB RAM, 193 GB disk), as root over the pinned host key:

| Run | Action | Result |
|---|---|---|
| 41157126002 (2026-10-31) | `status` | Failed as designed: host key changed after the OS reinstall; owner refreshed `SSH_KNOWN_HOSTS` |
| 41476850934 | `status` | Logged in; clean server, nothing installed |
| 41477334914 | `provision` | Installed every package and wrote every config, then stopped at `visudo -cf`: sudo on Ubuntu 26.04 rejects `Defaults:deploy !requiretty` (fixed in #31) |
| 41478868463 | `provision` | Success and idempotent ("all packages already installed"); versions nginx 1.28.3, PHP 8.5.4, MariaDB 11.8.6, Valkey 9.0.4, certbot 4.0.0, WP-CLI 2.12.0, Composer 2.8.12; sshd keys only, `PermitRootLogin prohibit-password`; ufw allows SSH, 80, 443 |
| 41479080934 | `site-staging` | System user, `/var/www/staging`, `shared/.env`, database, PHP-FPM pool, Nginx vhost with basic auth, Let's Encrypt certificate (expires 2027-01-31) |
| 41479292394 | `site-production` | Same for `/var/www/production`; Nginx vhost plus redirect vhost; one Let's Encrypt certificate for `cigarholic.com`, `www.cigarholic.com`, `max2lab.com`, `www.max2lab.com` |
| 41480577576 | deploy staging (merge of #32) | Release 1 built and switched; health check HTTP 500: MariaDB rejected the password from `shared/.env` (it contained a backslash, #34). Rollback impossible on a first release; the site stayed broken, which also kept the installer from working |
| 41481019603, 41481133571, 41481474895 | `wp-cli.yml` diagnostics | `db check` access denied; `php-error.log` the same; the `.env` password was 32 characters but not alphanumeric |
| 41482666960 | `site-staging` | Exit 141 before any change: the #34 generator died of SIGPIPE under `pipefail` (fixed in #35) |
| 41483927161, 41484047638 | `site-staging`, `site-production` | `DB_PASSWORD` rotated on both sites and the database users re-asserted; `wp db check` on staging: "Success: Database checked" (41484042421) |
| 41484236104 | `provision` | `wp` is now a wrapper around `wp-cli.phar` (PHP 8.5 deprecations silenced for the CLI only) |
| 41484381436 | deploy staging | Release 2: health check HTTP 302 (installer redirect, WordPress not installed), success |
| 41484875252 | `wp-cli.yml` | **Containment:** `/var/www/staging/current` removed after the installer was found reachable without basic auth (see below) |
| 41486044724, 41486154545, 41486290227 | `site-staging`, `site-production`, `provision` | #36 applied: installer denied in both vhosts, exact webhook exemptions in the shared config |
| 41486571888 | `status` | Staging database: 1 table (`wp_cigarholic_signups`), WordPress never installed. Installer requests in the access logs: 5, all GET, all from the verification probes, no POST; production none |
| 41486663859 | deploy staging | Release 3: staging back with the fixed Nginx rules. Probes from outside: `/` 401; the installer 403 plain, with `?wc-api=x` and via `/wc-api/../`; `/wc-api/%2e%2e/wp/wp-login.php` and `/wp/wp-login.php?wc-api=x` 401; `/?wc-api=wc_stripe` and `/wp-json/paypal/v1/incoming` reach WordPress without credentials (302 to the denied installer until it is installed); production installer 403 |
| 41489863286 | `wp-cli.yml` (branch of #39) | Owner secrets present (values masked); on the server the email is a valid address and the password 12+ characters |
| 41490772614 | `wp-cli.yml` `core install` (staging) | "Success: WordPress installed successfully." |
| 41490867026 | deploy staging | Release 4 stopped before the switch: `wp wc update` found no WooCommerce, because a fresh install activates no plugins (#40). Staging kept release 3 |
| 41491965149 | deploy staging (merge of #40) | Release 5: WooCommerce plus 10 plugins activated, "Provisioning complete: 76 change(s) applied", object cache enabled, health HTTP 200 |
| 41492338926, 41492761834 | `wp-cli.yml` checks | Coming-soon on, store pages only off, HPOS on, USD, theme `cigarholic`, 11 active plugins, external object cache; provisioning dry run 0 changes |
| 41493330262 | deploy staging (merge of #41) | Release 6: activation no-op, provisioning 1 change (`blog_public`), health HTTP 200 |
| 41493526902 | `wp-cli.yml` `cigarholic seed` (staging) | 7 `SAMPLE-` products created (one variable with 2 variations); the command refuses production |

Verified from the sandbox afterwards: `https://staging.cigarholic.com/` 401 + HSTS + `noindex`; `https://cigarholic.com/` 404 (no release yet) with the Let's Encrypt chain; `www`, `max2lab.com`, `www.max2lab.com` and `http://` each 301 to `https://cigarholic.com/...` in one hop; `xmlrpc.php` 403. The server `.env` files hold random database credentials and salts; application keys arrive through `env-upsert.sh` on each deploy and are empty until the owner sets the secrets. `SSH_USER` stays `root` until the first deploys are verified, then `disable-root-login` (OWNER-ACTIONS A1).

### Incident 2026-11-02: staging installer reachable without basic auth (closed)

- **What:** the staging basic-auth exemptions for payment webhooks matched URI prefixes, so `/wp/wp-admin/install.php?wc-api=x` and `/wc-api/../wp/wp-admin/install.php` reached the WordPress installer without credentials. That matters only while WordPress is not installed, and staging was in exactly that state: deployed, installer live.
- **Window:** from 16:41 UTC, when the database password rotation made WordPress able to connect, to 16:47 UTC, when `current` was removed. About six minutes. Before 16:41 the installer could not reach the database.
- **Found by:** my own probe after the first successful deploy; reproduced with HTTP 200 "WordPress › Installation".
- **Impact:** none found. The staging database held only `wp_cigarholic_signups`, created by `cigarholic-core` on `init` when the installer page loaded, and never `wp_options` or `wp_users`. The access logs show five installer requests, all GET, matching my probes, and no POST. There were no secrets or customer data on staging.
- **Fix (#36):** exact-URL exemptions with no `.`, `%` or extra query string; `install.php` and `setup-config.php` denied on both sites; 31-case Nginx test in CI; the `status` report (#37) now lists tables and installer requests.
- **Lesson:** an uninstalled WordPress must never be reachable. Production is deployed and installed back-to-back, and the web installer stays denied for good.

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
  - PHPUnit in the php container (`make test`): 71 tests / 839 assertions on `main` (features/toggles, hardening, signups, announcement bar, contact form, roles, provisioning idempotency, catalog data model, SKU rules, spec table, CSV import, catalog check, seed); also green in the CI runtime job on every PR.
  - Playwright (`make e2e`): 10/10 on desktop and mobile, twice in a row: coming-soon page for visitors with a real signup through the Turnstile test widget, duplicate accepted, invalid refused, administrator sees the store; shop → product page with spec table → cart → Cash-on-delivery checkout → order received → order emails in Mailpit (the thin P0 vertical slice of brief section 2, with the development gateway).
  - Theme render on the stack: `/`, `/shop/`, `/cart/`, `/my-account/` 200 with the right h1, `/nonexistent/` 404, empty `/checkout/` 302, coming-soon template for anonymous visitors.
  - Infrastructure dry runs: full `provision.sh` + `site.sh staging|production` in an `ubuntu:26.04` container (idempotent second run; nginx 1.28.3, PHP 8.5.4, MariaDB 11.8.6, Valkey 9.0.4), Deployer recipe parsing (`list`, `tree deploy|rollback`, `config`), Cloudflare scripts `--dry-run`.
  - FEATURES priority recount after the reconciliation; RDAP and DNS checks of both domains.
- **Executed and failed, then fixed:** PHPUnit 11 against the WordPress test suite (moved to 9.6); Composer source clones of PHPStan/PHPUnit too large for the sandbox (phars instead, ADR 0007); PEAR's HTTP/1.0 download of phpredis (curl + `docker-php-ext-install`); Compose bake entitlements (plain `docker build`); container TLS without the gateway CA (CA mounted, `https_ssl_verify` in development); `woocommerce_store_pages_only` defaulting to `yes` (provisioning pins `no`).
- **Executed and failed (expected, owner action):** `infra-provision.yml` → `status` on 2026-10-31: SSH host key mismatch against the pinned `SSH_KNOWN_HOSTS` (server reinstalled after the scan); the workflow refused to connect, which is the intended behaviour of host-key pinning.
- **Could not run here:** anything on the VPS beyond that check (no SSH from the sandbox by design): provisioning, Deployer end to end, Let's Encrypt, backups to B2; Stripe/PayPal test checkout (staging only, test keys not yet provided); the storefront smoke test end to end (needs seeded products from CAT-03); axe/Lighthouse budgets.
- **Not implemented yet:** ORD-05 full manager guide (grows per feature), OPS-06 monitoring runbook, the PHP 8.5 gate on staging (needs the server).

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
10. **WordPress is installed with WP-CLI only**; the web installer is denied on both sites for good (#36). Production is deployed and installed back-to-back.
11. **Server secrets are alphanumeric** (32 characters for passwords, 64 for salts): the same value is read by bash, MariaDB and PHP's dotenv, and only alphanumerics mean the same thing to all three (#34).
12. **Infra shell code gets tests** in `tests/infra/`, run by CI's shell job under `set -euo pipefail` (#35, #36, #37): two of today's three server failures were shell bugs that lint could not see.
13. **The deploy activates plugins** (WooCommerce first, then every plugin of the `--no-dev` release): the plugin set is `composer.json` plus `docs/plugins.md`, never a manual activation on a server (#40).

## Open owner decisions and inputs

All in `docs/OWNER-ACTIONS.md`: A1, A3, A5, A6 done; A2 (Cloudflare) deferred by the owner; A4 answered (GitHub Free, repository secrets, repository public). **B8 is the critical path** (WordPress install on both sites); B1 to B12 within two weeks; C1 to C5 before go-live. Defaults D-01 to D-10 are in force. The VPS was provisioned on 2026-11-02 under the brief's pre-approval; this file and OWNER-ACTIONS say what changed.

## Next actions

1. **M1 (owner, A7):** review staging; tag `v0.1.0` on `main`; approve the production deploy. Then I dispatch `wp core install` on production and a second production deploy (`deploy-production.yml`, tag `v0.1.0`); the owner approves each; I verify the coming-soon page, the redirects and the headers from outside and report M1. Until the install, production answers 403 (the redirect target is the denied installer); between the install and the second deploy, the theme's home page without WooCommerce. Then `disable-root-login` and `SSH_USER=deploy`.
2. **OPS-07 two-factor enforcement (P0, before launch):** the Two Factor plugin is active but nothing requires enrolment; require a second factor for administrators and shop managers (grace period at first login), with a test. The manager guide said it was required already; corrected.
3. **Small follow-up:** `cigarholic-core` creates `wp_cigarholic_signups` on `init` even before WordPress is installed (seen during the incident review); guard it with `is_blog_installed()`.
4. **P0 work that needs no credentials, next in this order:** CHK-06 order-received next steps, SHP-03 ship-time messaging on product and cart, SEO-01/SEO-02 SEOPress provisioning (titles, sitemaps, Product/Organization schema), CMP-03 Complianz provisioning (cookie notice, Consent Mode), MKT-02 GA4 plugin provisioning from `GA4_MEASUREMENT_ID`, OPS-09 image settings (WebP/AVIF, sizes), ORD-05 manager guide sections (products, prices, sales, orders, refunds, tracking), WCAG/axe checks in the Playwright suite (CMP-04), a Playwright smoke run against staging from a workflow (basic auth from the secrets).
5. **Phase 2 (P0 on staging) once B4/B5 arrive:** Stripe and PayPal test mode, wallets, refunds, webhooks (PAY-01..06), tax addresses (CMP-01), then the rest of brief section 8.

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

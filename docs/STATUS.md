# Status

Progress tracker for the Cigarholic build (`docs/KICKOFF-BRIEF.md` rule 6). Updated at the end of every session. A fresh agent, in a new cloud session or on a laptop, starts here after reading `CLAUDE.md` (which imports `AGENTS.md`).

Last updated: 2026-11-02, Turn 3: **Phase 1 in progress** (M0 reached 2026-10-31; VPS provisioned, sites and certificates live, staging deploys on; M1 waits for the owner's B8).

## Where we are

| | |
|---|---|
| Current milestone | **M0 "Repo green" reached** (2026-10-31). **M1 "Coming-soon live" in progress** (2026-11-02): VPS provisioned by `infra-provision.yml` (nginx 1.28.3, PHP 8.5.4, MariaDB 11.8.6, Valkey 9.0.4, certbot 4.0.0), `site-staging` and `site-production` done, Let's Encrypt certificates issued for `staging.cigarholic.com` and for `cigarholic.com` + `www` + both `max2lab.com` hosts (valid to 2027-01-31), staging deploys switched on (#32) |
| Live on production | **Edge only.** `https://cigarholic.com` answers through Nginx with a valid certificate (HTTP 404 until the first release is deployed and WordPress installed); `http://` → `https://`, `www.cigarholic.com` and both `max2lab.com` hosts 301 to `https://cigarholic.com` in one hop keeping the path; `xmlrpc.php` 403; HSTS on. No application deployed: production deploys only from an owner-approved tag |
| Live on staging | **Edge only.** `https://staging.cigarholic.com` answers HTTP 401 (basic auth) with `X-Robots-Tag: noindex` over a Let's Encrypt certificate. First Deployer release follows the merge of #32; WordPress install waits for OWNER-ACTIONS B8 |
| In progress | Phase 1: first staging deploy, then `wp core install` on staging and production (needs B8), then the coming-soon page on `cigarholic.com` from an approved tag (M1). OPS-06 monitoring runbook and UptimeRobot script (PR open). Phase 2 (payments, checkout) waits for Stripe/PayPal test credentials (B4, B5) |
| Blocked (owner) | **B8** `WP_OWNER_EMAIL` + `WP_OWNER_INITIAL_PASSWORD` (WordPress cannot be installed on either site without them; M1 depends on it). Then B6 Turnstile keys (forms run without a bot challenge until then), B1 Postmark (domain verification can start now), B2 backups, B3 monitoring, B4/B5 payments |

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

Verified from the sandbox afterwards: `https://staging.cigarholic.com/` 401 + HSTS + `noindex`; `https://cigarholic.com/` 404 (no release yet) with the Let's Encrypt chain; `www`, `max2lab.com`, `www.max2lab.com` and `http://` each 301 to `https://cigarholic.com/...` in one hop; `xmlrpc.php` 403. The server `.env` files hold random database credentials and salts; application keys arrive through `env-upsert.sh` on each deploy and are empty until the owner sets the secrets. `SSH_USER` stays `root` until the first deploys are verified, then `disable-root-login` (OWNER-ACTIONS A1).

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

## Open owner decisions and inputs

All in `docs/OWNER-ACTIONS.md`: A1, A3, A5, A6 done; A2 (Cloudflare) deferred by the owner; A4 answered (GitHub Free, repository secrets, repository public). **B8 is the critical path** (WordPress install on both sites); B1 to B12 within two weeks; C1 to C5 before go-live. Defaults D-01 to D-10 are in force. The VPS was provisioned on 2026-11-02 under the brief's pre-approval; this file and OWNER-ACTIONS say what changed.

## Next actions

1. **Phase 1 (M1), in order:** watch the first staging deploy (merge of #32); owner sets B8 → `wp-cli.yml` `core install` on staging (command in `docs/runbooks/deploy.md`), deploy again so `update-db`, `wc update`, `provision` and the object cache run, verify the coming-soon page behind basic auth; same on production; tag `v0.1.0` for the owner's approval → coming-soon live on `cigarholic.com` = M1. Then `disable-root-login` and `SSH_USER=deploy`. Postmark (B1), backups (B2) and monitoring (B3, OPS-06 PR) as the accounts arrive.
2. **P0 work that needs no credentials, next in this order:** CHK-06 order-received next steps (theme template exists; wire ship-time text to a setting), SHP-03 ship-time messaging on product and cart, SEO-01/SEO-02 SEOPress provisioning (titles, sitemaps, Product/Organization schema), CMP-03 Complianz provisioning (cookie notice, Consent Mode), MKT-02 GA4 plugin provisioning from `GA4_MEASUREMENT_ID`, OPS-09 image settings (WebP/AVIF, sizes), ORD-05 manager guide sections (products, prices, sales, orders, refunds, tracking), WCAG/axe checks in the Playwright suite (CMP-04).
3. **Phase 2 (P0 on staging) once B4/B5 arrive:** Stripe and PayPal test mode, wallets, refunds, webhooks (PAY-01..06), tax addresses (CMP-01), then the rest of brief section 8.

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

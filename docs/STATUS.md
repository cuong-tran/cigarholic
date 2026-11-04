# Status

Progress tracker for the Cigarholic build (`docs/KICKOFF-BRIEF.md` rule 6). Updated at the end of every session. A fresh agent, in a new cloud session or on a laptop, starts here after reading `CLAUDE.md` (which imports `AGENTS.md`).

Last updated: 2026-11-04, end of Turn 7 (session handoff): **`v0.2.0` (brand style) live on production (release 4, run 41760596394), but visitors still see the old white coming-soon page: it was saved in the Site Editor on production (post 28, with an edited lead sentence), and a saved copy wins over the theme file (owner decision A9). This turn merged #58 (FND-03 first-admin defaults), #59 (SHP-03 ship time) and #60 (CHK-06 order-received next steps); staging has all three.**

## Where we are

| | |
|---|---|
| Current milestone | **M1 "Coming-soon live" reached 2026-11-03** (M0 2026-10-31). Next: M2 "Payments on staging" (Phase 2, waits for B4/B5); meanwhile the P0 work that needs no credentials |
| Live on production | **`v0.2.0` (release 4, `167fd98`, run 41760596394, approved by the owner, 2026-11-04 02:25 UTC)**: the brand style; provisioning applied 15 changes (logo, site icon and email logo imported as attachments 29 to 31, `site_logo`, `site_icon`, email header image, the five brand email colours). Checked from outside: theme fonts (`young-serif`, `hanken-grotesk`) and the PNG site icon served with 200, no Google Fonts request, the logo above the H1. **But step 85 warned: "the coming-soon template was saved in the Site Editor (post 28)"**, so `/` shows that saved copy: the pre-brand white layout (`has-base-background-color`, `neutral-2` footer text), WooCommerce's own Inter and Cardo fonts also loaded, and an edited lead sentence ("…humidor accessories, the only one in the world. The store opens soon."). The Forest page reaches visitors only after the copy is reset (OWNER-ACTIONS A9). Everything from `v0.1.1`:  WordPress 7.1.4 installed with WP-CLI (admin `owner`), 11 launch plugins active, provisioning applied, HPOS on, Valkey object cache on, whole-site coming-soon on, search engines allowed, two-factor required for admins and shop managers (Authenticator App, Recovery Codes). Checked from outside after the deploy: `/`, `/shop/`, `/cart/`, `/my-account/`, `/journal/` show the coming-soon page with the signup form; `/checkout/` 302 to the cart; `/hello-world/`, `/?author=1`, `/?author_name=owner`, `/author/owner/`, the users sitemap and REST users 404; feed without items or `owner`; REST posts and comments `[]`; oEmbed author "Cigarholic"; signup REST refuses an invalid email (400, nothing stored); installer, `setup-config.php`, `xmlrpc.php` 403; `/wp-admin` and `/wp-login.php` 301 into `/wp/`; `http://`, `www`, `max2lab.com`, `www.max2lab.com` 301 to `https://cigarholic.com` keeping path and query; HSTS, `X-Frame-Options`, `nosniff`, referrer and permissions policies on cached and uncached pages; Let's Encrypt certificate to 2027-01-31. **Since #49** (vhost by `site-production` 41599367782): `/robots.txt` 200 `text/plain` from WordPress (WooCommerce's rules, `Sitemap: https://cigarholic.com/sitemaps.xml`), FastCGI cache MISS then HIT; SEOPress `/sitemaps.xml` 200 XML (post, page, product, category sitemaps); `/sitemap.xml`, `/wp-sitemap.xml`, `/sitemap_index.xml` 301 to it; `/license.txt`, `/wp/license.txt`, `xmlrpc.php`, the installer still 403; a missing `.txt` still Nginx's 404 |
| Live on staging | **Release 17, `main` at #60 (deploy run 41765262621)** (releases 15 and 16 for #58 and #59, runs 41762830797 and 41764165043): everything above plus the whole brand style (Forest header, footer, homepage and checkout header; shop, product, cart, checkout and account pages in the brand; logo, site icon and email logo; self-hosted fonts; brand email colours), the ship-time setting and messages (SHP-03), the order-received next steps (CHK-06) and the seven `SAMPLE-` products. Release 15's provisioning switched `woocommerce_coming_soon` back on: someone had turned coming soon off on staging (OWNER-ACTIONS A9 asks whether staging should stay without it); "Hello world!" trashed by provisioning; basic auth, `noindex` (also on `/robots.txt` and the sitemaps, 401 from outside); robots.txt `Disallow: /` (`blog_public` 0, `Seo\RobotsTxt`) |
| In progress | **Me:** nothing in flight (no open PR). Next: next action 2 onward. **Owner:** A9 (the saved coming-soon copy on production), then tag `v0.3.0` when ready (ship time and order-received steps; nothing visitors see while coming soon is on) |
| Blocked (owner) | B6 Turnstile keys (forms run with honeypot and rate limit only), B1 Postmark (no transactional mail on the servers yet), B2 backups, B3 monitoring, B4/B5 payments |

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
| #42 | docs | Release notes `v0.1.0`, staging installed and seeded |
| #43 | OPS-07 | `/wp-login.php` and `/wp-admin[/...]` 301 into `/wp/` (both vhosts, Compose runtime with relative redirects); `tests/e2e/admin-urls.spec.js`; manager guide no longer claims two-factor is enforced |
| #44 | docs | Turn 3 handoff |
| #45 | OPS-07, FND-03, CNT-04 | After the production install: author archives 404 and no `?author=N` redirect, front-end author links to home, no users sitemap, site name instead of a login in feeds and oEmbed; provisioning trashes the untouched "Hello world!" post; the signups table waits for an installed WordPress |
| #46 | OPS-07, FND-03 | Two-factor required for administrators and shop managers (`Security\TwoFactorRequired`, 3-day grace, then profile-only); provisioning offers Authenticator App and Recovery Codes only; runbook `two-factor.md` |
| #47 | docs | M1 status update |
| #48 | docs | `v0.1.1` live, A4 and A8 done |
| #49 | SEO-01, OPS-07 | `/robots.txt` and the core and SEOPress sitemap names go to PHP ahead of the vhost's static regex (it answered 404 for every `.txt`/`.xml` without a file); `Seo\RobotsTxt` disallows everything while `blog_public` is 0; `nginx-staging-auth.sh` 69 cases (every hard deny, routing, template order), `robots.spec.js`, `RobotsTxtTest` |
| #50 | docs | `/robots.txt` and the sitemaps live on both sites |
| #51 | docs | CNT-01 brand plan (`docs/plans/CNT-01-brand-style.md`) |
| #52 | CNT-01, CNT-04, CMP-04, ORD-03 | Brand tokens, self-hosted fonts, the Forest section style, coming-soon page and email colours in the brand; ADR 0009; `ThemeContrastTest`, `brand.spec.js` |
| #53 | CNT-01, ORD-03 | Logo, site icon and email logo from the owner's artwork (provisioning step 25, `brand/`) |
| #54 | NAV-01, NAV-03 | Forest header and footer; provisioning step 85 reports theme files hidden by Site Editor copies |
| #55 | NAV-04 | Homepage in the brand layout (Forest hero, category cards, best sellers); step 85 also watches the front page |
| #56 | CNT-01, CHK-01, CMP-04, NAV-03 | WooCommerce pages in the brand (fields, quantity, tabs, gallery, shop cards, cart title, Forest checkout header, order-received card, brand error colour); the footer signup no longer pushes tablet pages sideways (Turnstile); step 85 watches `page-checkout` and `checkout-header`; decision 18 |
| #57 | docs | Turn 6 handoff (tagged `v0.2.0` by the owner) |
| #58 | FND-03 | Provisioning step 10 runs WooCommerce's new-store defaults (`WC_Install::newly_installed`) once, before its settings, so the first admin page view no longer turns email theme sync on and recolours the emails; `[0.2.0]` changelog section |
| #59 | SHP-03, CMP-05 | One ship-time setting (WooCommerce → Settings → Shipping, default 1–2 business days): `cigarholic/ship-time` block on the product page, "Ships in …" under in-stock cart items (cart, checkout summary, mini cart), trust bar and FAQ; nothing promised for backorders, out of stock or a quantity above the stock; manager guide delay procedure |
| #60 | CHK-06, SHP-03, CMP-05 | Order-received "What happens next" written from the order (ship time, backorders with the right to cancel, tracking email, account link, support with the order number, seasoning tip for humidors, payment check when on hold, nothing for unpaid orders; order key required); rendered as a real Card group |

## Server (Phase 1, 2026-11-02 / 03)

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
| 41502548317, 41502652155 | `site-staging`, `site-production` | #43 vhosts applied. From outside on both hosts: `/wp-admin` → 301 `/wp/wp-admin/`, `/wp-login.php` → 301 `/wp/wp-login.php` (query kept), `/wp-admin/install.php` 403 |
| 41580693167 (2026-11-03 04:53) | deploy production, tag `v0.1.0` (owner's tag push) | Started 14 s after the tag: **no approval asked** (A4). Release 1 built and switched; WordPress not installed, so the WP tasks were skipped; health HTTP 302 (to the denied installer). Pre-deploy snapshot taken. From outside: `/` 302, installer 403 |
| 41582839987 (05:15) | `wp-cli.yml` production `core install` | "Success: WordPress installed successfully." (owner secrets loaded, values masked). Also a database error from `cigarholic-core` writing an option before `wp_options` existed (fixed in #45) |
| 41582927297 (05:16) | deploy production, tag `v0.1.0` (dispatch) | Release 2: snapshot (20K), `update-db` current, WooCommerce then 10 plugins activated, `wc update` none needed, "Provisioning complete: 77 change(s) applied", object cache enabled, cache flushed, health HTTP 200 |
| 41584244003, 41584247205 | `wp-cli.yml` `user meta get owner _two_factor_enabled_providers` | Staging `[]`, production no value (exit 1): nobody had a two-factor method stored, so switching emailed codes off (#46) locks no one out |
| 41584712880 | deploy staging (merge of #45) | Release 7: "Hello world!" trashed; three WooCommerce email colour options re-applied (see next actions); health HTTP 200 |
| 41589281187, 41589277636 (cancelled) | `wp-cli.yml` `user check-password owner` | Diagnosis of the owner's production login: staging password no longer equals the secret (changed by the owner, as A7 asked) |
| 41590472526 (approved) | `wp-cli.yml` production `user update owner --user_pass="$WP_OWNER_INITIAL_PASSWORD" --skip-email` | "Success: Updated user 1." The owner had typed a different initial password; logged in afterwards and changed it. A second, trimming reset (41594714606) was cancelled before it ran |
| 41595790747 (2026-11-03 07:21, approved) | deploy production, tag `v0.1.1` (`813d144`) | Release 3: snapshot (44K), provisioning 5 changes ("Hello world!" trashed, `two_factor_enabled_providers`, the three WooCommerce email options of next action 2 after the owner's first admin visit), object cache, health HTTP 200; outside checks in "Where we are" |
| 41599116820 (2026-11-03 07:51) | deploy staging (merge of #49) | Release 9: provisioning 0 changes, health HTTP 200 |
| 41599252862 | `site-staging` | #49 vhost written, `nginx -t` passed, reloaded. From outside: `/robots.txt`, `/wp-sitemap.xml`, `/sitemaps.xml`, `/page-sitemap1.xml` 401 with the basic-auth realm and `X-Robots-Tag: noindex, nofollow, noarchive` |
| 41599367782 | `site-production` | #49 vhost written (`.env`, database, certificate untouched). From outside: the "Since #49" checks in "Where we are" |
| 41599472269 | `wp-cli.yml` staging `eval` (read only) | `blog_public=0`; `do_robots()` prints `User-agent: *` / `Disallow: /` |
| 41585778385 | deploy staging (merge of #46) | Release 8: provisioning 1 change (`two_factor_enabled_providers`), health HTTP 200. Two-factor is now required on staging; the owner's grace period starts at the next admin page view |
| 41651031774 (2026-11-03 14:10) | deploy staging (merge of #52) | Release 10: provisioning 5 changes (the WooCommerce email colours to Forest, Ash, Ash tint, Ink, Stone), health HTTP 200 |
| 41656501840 (14:43) | deploy staging (merge of #53) | Release 11: provisioning 10 changes (logo imported as attachment 41 and set as `site_logo`, site icon attachment 42, email logo 43), health HTTP 200 |
| 41672622729 (16:17) | deploy staging (merge of #54) | Release 12: provisioning 0 changes; step 85 `ok` for coming-soon, header, footer; health HTTP 200 |
| 41690402881 (18:05) | deploy staging (merge of #55) | Release 13: provisioning 0 changes; step 85 also `ok` for the front page; health HTTP 200 |
| 41699313149 (19:01) | deploy staging (merge of #56) | Release 14: provisioning 0 changes; step 85 `ok` for coming-soon, front-page, page-checkout, header, footer, checkout-header (no Site Editor copies); health HTTP 200 |
| 41760596394 (2026-11-04 02:25, approved) | deploy production, tag `v0.2.0` (`167fd98`) | Release 4: snapshot (44K), provisioning 15 changes (brand attachments 29 to 31, logo, icon, email header image, five email colours); **step 85 warning: coming-soon template saved in the Site Editor (post 28)**; front-page, page-checkout, header, footer, checkout-header from the theme; object cache, health HTTP 200 |
| 41762830797 (02:45) | deploy staging (merge of #58) | Release 15: provisioning 1 change (`woocommerce_coming_soon` back to on), health HTTP 200 |
| 41764165043 (02:58) | deploy staging (merge of #59) | Release 16: provisioning 0 changes, health HTTP 200 |
| 41765262621 (03:08) | deploy staging (merge of #60) | Release 17: provisioning 0 changes, health HTTP 200 |

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

## Tests executed in Turn 7 (2026-11-04: #58, #59, #60)

- **Executed and passed (production from outside, after `v0.2.0`):** `/`, `/shop/`, `/cart/`, `/my-account/` 200 (coming-soon page); `/checkout/` 302 to the cart; `/?author=1` 404; `/robots.txt` 200 `text/plain`; `/sitemaps.xml` 200 XML; `/wp-admin` 301 into `/wp/`; installer and `xmlrpc.php` 403; theme fonts, the PNG site icon and the WebP logo 200 with the right types; HSTS and the security headers; no `googleapis` in the page.
- **Executed and passed (sandbox, Compose stack):**
  - Static checks: `composer check` and `npm run lint` on every PR.
  - PHPUnit: 94, then 99, then 106 tests (1227 assertions).
  - Playwright: `make e2e` 28/28 on #59 and #60.
  - #58: `make up` from scratch ran the new-store defaults in provisioning. A real admin login and page view followed, then `provision --dry-run` showed 0 changes.
  - #59: saving 3/5, 4/4 and 1/2 in the WooCommerce settings form changed the product page each time.
  - #60: a Cash-on-delivery order shows the card with the Card style (Ash tint, 1 px border) and its order number.
  - CI green on every PR head (5 jobs, including the runtime job).
- **Executed and failed, then fixed:**
  - #58's test against the old step 10: after provisioning, the first admin visit set theme sync to `yes`, the email base colour to `#9c5034` and the body background to `#eee9e0`.
  - #59's first mini-cart locator.
  - #60's first card wrapper rendered unstyled, because WordPress gives each Card group its own `is-style-card--N` class.
  - Lint findings on all three (PHPCS array style, a docblock type, WooCommerce's filter name, an eslint browser global).
- **Found on the way:**
  - The production coming-soon page is a Site Editor copy (A9).
  - Coming soon had been switched off on staging (A9).
  - `/favicon.ico` answers Nginx's 404 on production. Browsers use the `<link rel="icon">` PNGs, so nothing is broken, but a request log shows the 404s; WordPress would redirect it to the site icon if the vhost passed it to PHP (small follow-up with the next vhost change).
- **Could not run here:** Lighthouse (not in the sandbox image); a look at the staging pages (basic-auth credentials are not readable from the sandbox).

## Tests executed in Turn 6 (2026-11-03, CNT-01 PR 4)

- **Executed and passed (sandbox, Compose stack):** `composer check`, `npm run lint`, balanced block comments (27 files), `jq` on `theme.json`, PHPUnit 93 tests / 1117 assertions (step 85 also reports `page-checkout` and `checkout-header`), provisioning re-run (step 85 `ok` for all six watched files), Playwright `brand.spec.js` 11/11 including three new tests: WooCommerce pages follow the brand (sorting field, left-aligned cards, gallery fills its column, quantity signs and box, tabs, cart title aligned, Forest checkout header with logo, Ash tint fields with a Stone border, `#9b1c1c` validation text), no sideways scroll at 320/390/782/800/834/1024/1440 px on the shop and a product page, and AA contrast on home, shop, product, cart, checkout and account. An audit script covered 16 pages at 390 and 1440 px (shop, category, search, empty and full cart, mini cart, checkout, order received, account pages, 404): no contrast failure, no overflow.
- **Executed and failed, then fixed:** the three new tests fail on the theme from `main` (browser-grey sorting field, 24 px overflow at 782 px, the quantity `+` at 4.35:1). The overflow sweep (320 to 1300 px) traced the tablet overflow to the Turnstile widget in the footer signup column (782 to 850 px), not the mini-cart drawer.
- **Executed and passed (CI and staging):** CI green on every #56 head (b504044, f41f3c2 with the review fixes, be32cd7), including the runtime job (install, provision, PHPUnit, Playwright). After the merge, staging release 14 (run 41699313149): provisioning 0 changes, step 85 `ok` for all six watched files, health HTTP 200.
- **Could not run here:** a logged-out look at the account login form and the cart (coming-soon mode serves the launch page to visitors; checked logged in). Lighthouse is not in the sandbox image (Core Web Vitals still to be measured on staging, see next action 2).

## Tests executed in Turn 5 (2026-11-03, CNT-01 PR 1)

- **Executed and passed (sandbox, Compose stack):** `composer check` (lint, PHPCS, PHPStan level 6), `npm run lint` (eslint, stylelint), PHPUnit 89 tests / 1066 assertions (new `ThemeContrastTest`: declared pairs, palette rules, email pairs, Forest registration), provisioning re-run (email colours `#1f2b25` / `#eee9e0` / `#f7f4ef` / `#1a1815` / `#5e574e`), Playwright 18/18 (new `brand.spec.js`: fonts served, loaded and painting the H1 per Chromium's platform-font report, no Google Fonts requests, Forest ground at least one viewport tall, Gold focus ring, `#f2a08a` error on Forest, AA contrast of every visible text on coming soon, home and shop). Screenshots at 1440 and 390 px of coming soon (plain and error state), home, shop and a product.
- **Executed and failed, then fixed:** the page contrast check found Cedar links on Sand `#e3dcd0` at 4.27:1 ("Shop all humidors", the newsletter's privacy link); Sand was dropped as a token (`neutral-1` is now the Ash tint) and a palette-rules test added so a ground without rules fails in PHPUnit. PHPCS flagged `file_get_contents` in the new test (now `wp_json_file_decode`); eslint needed the browser globals used inside `page.evaluate`. After a mouse click the focused signup button turned Ash (the button `:focus` style copied hover); removed, keyboard focus shows the ring.
- **Could not run here:** Lighthouse (no Lighthouse in the sandbox image; Core Web Vitals to be measured on staging after merge).

## Tests executed in Turn 5 (2026-11-03, #49)

- **Executed and passed:**
  - Before the fix, from outside: production `/robots.txt`, `/wp-sitemap.xml`, `/sitemap.xml`, `/sitemaps.xml` 404 `text/html` (Nginx's page); `/wp-sitemap.xsl` 404 from WordPress (`xsl` is no static extension; SEOPress switches core sitemaps off).
  - `tests/infra/nginx-staging-auth.sh` 69 cases locally and in CI; the same script against three broken copies of the template (the `main` one, the static regex moved first, `=404` fallbacks) fails as it should; `nginx -t` on the complete rendered staging vhost.
  - `composer check`, `npm run lint`, `shellcheck -S warning`; on the Compose stack `make test` 84 tests / 922 assertions, `make e2e` 15/15; CI green on all 5 jobs.
  - After the merge: the server runs and outside checks in "Server" and "Where we are".
- **Executed and failed, then fixed:** PHPCS on the new class's file docblock (lower-case first word).
- **Found on the way:** WordPress has not written `Disallow: /` for a non-public site since 5.3 (it relies on the noindex meta tag), so staging's robots.txt would have invited crawlers; `Seo\RobotsTxt` closes that.
- **Could not run here:** a request for staging's robots.txt with credentials (basic-auth secrets are not readable from the sandbox); WordPress's output on staging was checked through WP-CLI instead, and the vhost is the same template as production's.

## Tests executed in Turn 4 (2026-11-03)

- **Executed and passed:**
  - Production from outside after release 2 (curl through the sandbox proxy): the table in "Where we are". Also `wp-json/wc/store/v1/products` 200 `[]`, `/robots.txt` 404 (see next actions), `/feed/` and oEmbed reachable during coming-soon (they named `owner` and served the sample post, fixed in #45 for `v0.1.1`).
  - `composer check` on both PRs; `make test` 76 tests (#45) then 82 tests / 918 assertions (#46); `make e2e` 14/14 on both PRs; CI green on #45 (5 jobs).
  - On the Compose stack: author URLs 404, users sitemap 404, feed and oEmbed show "Cigarholic" for a probe post. Two-factor in a real browser (Playwright script): grace notice with the deadline and full access; grace moved into the past → `edit.php` redirects to `profile.php#two-factor-options`, menu Dashboard and Profile only, `/wp/v2/settings` 403 with a valid nonce; TOTP enrolled from that state with a computed code → `wp two-factor status admin` `using_2fa true`, products page back at once.
- **Executed and failed, then fixed:** the new ProvisionTest first failed (earlier tests leave `$wp_rewrite` at `/%postname%/` in memory after the rollback; the test resets it); the first restricted-state browser run stopped at WordPress's "not allowed" page because `wp-admin/menu.php` refuses pages before `admin_init` (now also hooked on `admin_page_access_denied`, asserted in the test).
- **Could not run here:** a login on production (the owner's account; I never use the owner password); the `v0.1.1` production deploy (needs the owner's tag).

## Tests executed in Turns 1–2 (2026-10-30 / 31)

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
1. **Dev tools as phars** (ADR 0007): the sandbox can fetch GitHub packages only by cloning; PHPStan and PHPUnit repositories are gigabytes. PHPUnit stays on 9.6 because the WordPress test suite (`wp-phpunit` 7.1.3) still calls PHPUnit 9 APIs.
2. **Composer-managed updates only** (ADR 0001): in-place WordPress auto-updates are off; a scheduled update-PR workflow replaces them (FND-13 brought forward for security releases, to do). The owner can overrule.
3. **Containers trust the sandbox gateway CA** instead of using proxy variables (TLS from containers is intercepted transparently); WordPress uses the system store in development through `https_ssl_verify`.
4. **Staging deploys are gated** on the marker file `infra/deploy/STAGING_ENABLED` so pushes to `main` do not fail until the server exists; production deploys run from a tag with the `production` Environment approval (public repository), the tag-input manual run is the fallback.
5. **Cloudflare is additive** (ADR 0008): the origin alone serves TLS (Let's Encrypt), the `www`/`max2lab` redirects and the rate limits; Cloudflare scripts exist for later.
7. **Cash on delivery in development only** exercises cart → order → email without payment keys (ADR 0004); Stripe/PayPal test mode is staging-only.
8. **Signups are stored by `cigarholic-core`** (own table, CSV export) until the email platform is decided (D-04).
9. **Runtime pins** match the Ubuntu 26.04 archive minors (PHP 8.5, MariaDB 11.8, Valkey 9.0, nginx 1.28), verified in a container.
10. **WordPress is installed with WP-CLI only**; the web installer is denied on both sites for good (#36). Production is deployed and installed back-to-back.
11. **Server secrets are alphanumeric** (32 characters for passwords, 64 for salts): the same value is read by bash, MariaDB and PHP's dotenv, and only alphanumerics mean the same thing to all three (#34).
12. **Infra shell code gets tests** in `tests/infra/`, run by CI's shell job under `set -euo pipefail` (#35, #36, #37): two of today's three server failures were shell bugs that lint could not see.
13. **The deploy activates plugins** (WooCommerce first, then every plugin of the `--no-dev` release): the plugin set is `composer.json` plus `docs/plugins.md`, never a manual activation on a server (#40).
14. **Two-factor is enforced by withholding capabilities, not by route lists** (#46): after the grace period a privileged account without a method keeps only `read` and `view_admin_dashboard`, so WP admin, REST, admin-ajax, admin-post and application passwords all follow from WordPress's own capability checks; roles are never changed. Emailed codes are off while there is no transactional mail. Without the plugin nothing is enforced (fail open with a notice) rather than locking every admin out of a broken release.
15. **Root SSH stays on for now** (key only, `prohibit-password`). `disable-root-login` closes root, and with it every `provision` and `site-*` workflow run (they need root; afterwards only the provider console works), while Nginx changes are still coming (Cloudflare later, backups; robots.txt and sitemaps landed in #49). The switch to `SSH_USER=deploy` plus `disable-root-login` happens once the server configuration settles, before launch.
16. **Own PRs are still merged once CI is green** although production exists: merging to `main` deploys staging only; production moves only with a tag the owner creates (brief rule 3). Say so if you want to review PRs before merge from now on.
17. **Virtual files are routed by name, not by dropping `txt`/`xml` from the static regex** (SEO-01): real `.txt` and `.xml` files (plugin readmes, licences, assets) keep the 30-day static path, and a probe for any other missing `.txt` or `.xml` stays a cheap Nginx 404 instead of booting WordPress. Only `/robots.txt` and the sitemap names WordPress 7.1 and SEOPress 10.4 register (checked in their rewrite rules) go to PHP; a new sitemap plugin or name means a new pattern and test case.
18. **Checkout stays distraction-free** (owner, 2026-11-03, #56): the checkout page keeps WooCommerce's layout without the site menu, search, account link or footer; only a Forest header with the logo (to home) and the cart link. The terms and privacy links stay in the checkout block (CHK-05). Bringing either back is a one-line change to `templates/page-checkout.html`.
19. **The ship time belongs to the manager, not to provisioning** (#59): it is a WooCommerce setting with a default in code (1–2 business days), never written by `wp cigarholic provision`, because it must change the moment orders will leave later (FTC Mail Order Rule) without a deploy. Every promise reads it: the product page, the cart line, the order-received card, the trust bar and the FAQ; templates never contain a day count.
20. **Backordered lines get no ship time** (#59, #60): the product page and the cart promise nothing for them, and the order-received card says they ship when back in stock, that the buyer gets the expected date by email and can cancel. A line counts as backordered when WooCommerce marked it or its product's managed stock is below zero.
21. **Theme files saved in the Site Editor are reported, never reset by a deploy** (step 85): a saved copy is the owner's or manager's content. Resetting one on production is the owner's call (A9).

## Open owner decisions and inputs

All in `docs/OWNER-ACTIONS.md`: A1 and A3 to A8 done; A2 (Cloudflare) deferred by the owner; `v0.2.0` tagged and deployed. **Now: A9**, the coming-soon page saved in the Site Editor on production (reset it so visitors see the Forest page, or tell me which wording to keep), and whether staging should run without coming soon. **B9:** a vector master of the wordmark (SVG, AI or PDF), confirmation that the canvas is final, product photos, specs and copy. B1 to B12 within two weeks (B6 Turnstile and B1 Postmark first); C1 to C5 before go-live. Defaults D-01 to D-10 are in force.

## Next actions

1. **Owner, A9:** the production coming-soon page. Reset the saved template (Appearance → Editor → Templates → Page: Coming soon → ⋮ → Reset) to show the Forest brand page, or tell me the wording you want and I put it into the theme first. Also say whether staging should stay in coming-soon mode. **After the reset (me):** check `/` from outside (Forest ground, logo, Young Serif H1, no Inter/Cardo requests) and record it.
2. **Owner, when ready:** tag `v0.3.0` on `main` for #58 to #60. It changes nothing visitors see while coming soon is on; the next tag can wait for more work.
3. **P0 work that needs no credentials, in this order:**
   - SEO-01/SEO-02 SEOPress provisioning: titles, sitemaps, Product and Organization schema. The live page sitemap lists `/cart/`, `/checkout/` and `/my-account/`, which must go, and the post category sitemap is empty.
   - SHP-04 tracking: the manager pastes the carrier and tracking number, the customer gets a "Shipped" email with the link. It needs a plugin row in `docs/plugins.md` or a small custom module; decide by the budget (11 of 15 active).
   - ORD-03 follow-up: the ship-time line in the customer's processing email.
   - CMP-03 Complianz provisioning (cookie notice, Consent Mode).
   - MKT-02 GA4 plugin provisioning from `GA4_MEASUREMENT_ID`.
   - OPS-09 image settings (WebP/AVIF, sizes).
   - ORD-05 manager guide sections (products, prices, sales, orders, refunds, tracking).
   - WCAG/axe checks in the Playwright suite (CMP-04).
   - A Playwright smoke run against staging from a workflow (basic auth from the secrets).
   - A Playwright spec for the two-factor restricted flow.
   - Lighthouse on staging and production (accessibility 100, LCP and CLS in the good band; CNT-01 plan section 5).
   - `/favicon.ico` to WordPress in the vhost.
4. **Before launch:**
   - `SSH_USER=deploy` (owner) and `disable-root-login` once the server configuration settles (decision 15).
   - B2 backups and B3 monitoring live.
   - Publish the contact, FAQ and policy pages: the order-received card links them only once they are published.
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

```bash
for u in / /shop/ '/?author=1' /feed/ /robots.txt /sitemaps.xml /wp-admin /wp/wp-admin/install.php /xmlrpc.php; do printf '%-28s ' "$u"; curl -sS -o /dev/null -w '%{http_code} %{redirect_url}\n' "https://cigarholic.com$u"; done   # production from outside
```

## Starting a new session

Everything is merged and pushed; nothing lives only in a conversation. A new session reads `CLAUDE.md` (which imports `AGENTS.md`), this file and `docs/OWNER-ACTIONS.md`, then continues from "Next actions". Server work runs through the workflows (`infra-provision.yml`, `wp-cli.yml`, `deploy-*.yml`); `docs/runbooks/` has the commands. A production release is the owner's tag on `main`; after its deploy, check the live site from outside with the commands below and record the run IDs in "Server".

## If the session is reopened after the VM expired

`git status`, read this file, `scripts/tools.sh && composer install`, `make up`, then continue from "Next actions". If the Docker daemon is down after a restart, `make up` starts it and recreates the containers with the gateway CA (do not use a bare `docker compose up` in the sandbox).

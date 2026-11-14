# Server run log

Every workflow run that touched the VPS, in order, with its result. Moved here from `docs/STATUS.md` on 2026-11-13 (project revision). Add a row for each server run; the current state of both sites is in `docs/STATUS.md`.

## Runs since Phase 1 (from 2026-10-31)

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
| 42073737919 (2026-11-05 17:21, approved) | deploy production, tag `v0.3.0` (`80b3515`) | Release 5: snapshot (52K), activation no-op, `wc update` none needed, provisioning 0 changes, step 85 still warned about post 28 (reset 13 minutes later, next row), object cache, health HTTP 200 |
| 42075862951 (2026-11-05 17:34, approved) | `wp-cli.yml` production `eval` (A9) | Checked post 28: `wp_template` `coming-soon`, theme `cigarholic`, publish, created and last modified in the 02:00 UTC hour of 2026-11-04, before the `v0.2.0` deploy (the log's secret filter masks part of the time and the size), content printed to the log; `before: source=custom`; deleted; object cache flushed; `after: source=theme forest=yes` |
| 42129220297 (2026-11-05 23:26) | deploy staging (merge of #62) | Release 18: `wc update` "1 update functions completed. Database version is 11.2.2" (`wc_update_1122_remove_objects_from_default_attributes`), provisioning 0 changes, step 85 `ok`, health HTTP 200 |
| 42129288438 | deploy staging (merge of #63) | Release 19: no DB update, provisioning 0 changes, health HTTP 200 |
| 42130131873 | deploy staging (merge of #65) | Release 20: provisioning 0 changes (coming soon was still on), health HTTP 200 |
| 42133693853 (2026-11-06 00:01) | deploy staging (merge of #66) | Release 21: no DB update; step 75-seo 8 changes (SEOPress titles, sitemaps, social and toggle options, notices; `noindex` on the cart #6, checkout #7 and my-account #8 pages), no warnings; health HTTP 200 |
| 42135141731 (2026-11-06 00:12) | deploy staging (merge of #67) | Release 22 (the log prints `releases/***`: its secret filter masks every "22", the value of `SSH_PORT`): no DB update, provisioning 0 changes, health HTTP 200 |
| 42158788889 (2026-11-06 03:49, approved) | deploy production, tag `v0.4.0` (`19533a9`) | Release 6: snapshot (52K), `wc update` "1 update functions completed. Database version is 11.2.2", provisioning 8 changes (step 75-seo; WooCommerce pages #7, #8, #9 `noindex`), step 85 `ok`, health HTTP 200; outside checks in "Where we are" |
| 42199954917 (2026-11-06 10:05) | deploy staging (merge of #73) | Release 24: provisioning 0 changes, step 85 `ok` (front page from the theme, so the homepage shows the new photos), health HTTP 200 |
| 42200148851, 42200214991 | `wp-cli.yml` staging `cigarholic seed`, then a read-only `eval` | "Sample catalog: 7 products (0 created, 7 updated)"; every sample product's image is now the JPEG of its photo (e.g. `sample-walnut-desktop-humidor-50.jpg`, `image/jpeg`). The earlier GD placeholders stay in the media library |
| 42169654778 (2026-11-06 05:38) | deploy staging (merge of #71) | Release 23: no DB update, provisioning 4 changes (`woocommerce_feature_fulfillments_enabled`, `woocommerce_customer_fulfillment_created/updated/deleted_settings`), step 85 `ok` for all six watched files, health HTTP 200. Then `wp-cli.yml` 42169820853 (read only): feature `yes`, tables created (`wc_order_fulfillments` exists), `WC_Email_Customer_Fulfillment_Created/Updated/Deleted` enabled |

Verified from the sandbox afterwards: `https://staging.cigarholic.com/` 401 + HSTS + `noindex`; `https://cigarholic.com/` 404 (no release yet) with the Let's Encrypt chain; `www`, `max2lab.com`, `www.max2lab.com` and `http://` each 301 to `https://cigarholic.com/...` in one hop; `xmlrpc.php` 403. The server `.env` files hold random database credentials and salts; application keys arrive through `env-upsert.sh` on each deploy and are empty until the owner sets the secrets. `SSH_USER` stays `root` until the first deploys are verified, then `disable-root-login` (OWNER-ACTIONS A1).

## Incident 2026-11-02: staging installer reachable without basic auth (closed)

- **What:** the staging basic-auth exemptions for payment webhooks matched URI prefixes, so `/wp/wp-admin/install.php?wc-api=x` and `/wc-api/../wp/wp-admin/install.php` reached the WordPress installer without credentials. That matters only while WordPress is not installed, and staging was in exactly that state: deployed, installer live.
- **Window:** from 16:41 UTC, when the database password rotation made WordPress able to connect, to 16:47 UTC, when `current` was removed. About six minutes. Before 16:41 the installer could not reach the database.
- **Found by:** my own probe after the first successful deploy; reproduced with HTTP 200 "WordPress › Installation".
- **Impact:** none found. The staging database held only `wp_cigarholic_signups`, created by `cigarholic-core` on `init` when the installer page loaded, and never `wp_options` or `wp_users`. The access logs show five installer requests, all GET, matching my probes, and no POST. There were no secrets or customer data on staging.
- **Fix (#36):** exact-URL exemptions with no `.`, `%` or extra query string; `install.php` and `setup-config.php` denied on both sites; 31-case Nginx test in CI; the `status` report (#37) now lists tables and installer requests.
- **Lesson:** an uninstalled WordPress must never be reachable. Production is deployed and installed back-to-back, and the web installer stays denied for good.

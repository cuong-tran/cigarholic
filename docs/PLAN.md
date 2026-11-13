# Plan

The work, in the order it should be done, as packages small enough for one pull request each (or a few). Scope is `docs/FEATURES.md`; dates and milestones are `docs/ROADMAP.md`; the findings behind stage 1 are in `docs/ARCHITECTURE.md` section 7.

How to use it: take the first package that is not done and not blocked. One PR per feature ID, branch `feat/<ID>-<slug>`; a package without a feature ID of its own uses the ID in its "IDs" column. When a package is done, mark it here with the PR number and move its detail to `CHANGELOG.md`. New work gets the next WP number; numbers are never reused.

Last updated: 2026-11-13 (project revision; replaces "Next actions" in `docs/STATUS.md`).

Status words: **open**, **in progress**, **done (#PR)**, **blocked (on what)**.

## Stage 1: make the live site safe (milestone M1b)

No package here needs anything from the owner unless its "Needs" column says so. WP-01 comes first because until it lands the rule "production changes only with the owner's approval" is kept by discipline alone.

| WP | What | IDs | Done when | Needs | Status |
|---|---|---|---|---|---|
| WP-01 | **Close the gaps in the production approval.** (1) `wp-cli.yml`: the command never reaches a shell as text: pass it as arguments to `wp` only, refuse shell metacharacters, and derive the site directory from the approved environment. (2) Split the server workflows so every action that touches production (`provision`, `site-production`, `disable-root-login`, backup and restore, Cloudflare when not a dry run) runs in a job with `environment: production`. (3) Remove `certbot` from the `deploy` user's sudo rights, or replace it with a root-owned wrapper that takes no arguments. (4) Tests in `tests/infra/` for the command filter and the sudoers file | OPS-07, FND-08 | A staging WP-CLI run cannot name another site or chain a command (test); every production-touching job waits for approval; `sudo -l` for `deploy` lists no command that takes free arguments; runbooks `secrets.md`, `server.md` corrected | Applying the sudoers change needs one approved `provision` run | open |
| WP-02 | **Dependency updates that are waiting.** Merge #77 (WooCommerce stubs 11.2.2). Fix #76 (npm: `@wordpress/stylelint-config` 27.1 needs stylelint 17.14 or newer; update both together). Do not merge #79: it carries WooCommerce 11.3.0, an `x.0` release, and fails five `ShippingTrackingTest` cases (the fulfillment REST route answers 400, and `emails/email-fulfillment-details.php` changed). Take the Stripe 11.2.0 and PayPal 4.2.2 updates separately if their changelogs are clean. Then adapt `Shipping\Tracking` and its template copies to 11.3 on a branch, so 11.3.1 can be merged the day it appears. Add a Dependabot ignore for `x.0` of WordPress and WooCommerce if its configuration can express it | FND-13, SHP-04 | #76 and #77 merged green; #79 closed with the reason; a branch passes the suite on WooCommerce 11.3.x; `docs/versions.md` and `docs/plugins.md` updated | none | open |
| WP-03 | **Scheduled jobs from the system clock.** `DISABLE_WP_CRON` true on staging and production; a systemd timer per site runs `wp cron event run --due-now` every minute as the site's user; `infra/server/status.sh` reports the last run and the number of past-due Action Scheduler actions | OPS-15 | The timer exists after a `site-*` run; a scheduled event set one minute ahead runs on staging without any page view (WP-CLI workflow); a test in `tests/infra/` renders the unit | One approved `site-production` run | open |
| WP-04 | **Guard the repository.** gitleaks in CI over each PR's commits; third-party actions pinned to commit hashes (at least in jobs that hold secrets); the staging deploy starts only after CI succeeds on the same commit; the owner protects `main` (required checks, no force push) and switches on secret scanning with push protection | FND-17, FND-06 | A test commit containing a fake key fails CI; a red CI on `main` does not deploy staging; A12 done | OWNER-ACTIONS A12 for the repository settings | open |
| WP-05 | **Public forms that cannot be flooded or lose data.** Rate limit keyed on the address Nginx sees (trust `CF-Connecting-IP` only when the request comes from a Cloudflare range, which is never today); an Nginx `limit_req` on `/wp-json/cigarholic/` and the two public `admin-post` actions; contact messages stored (a private post type or a table, with a retention period and a WP admin list) before the mail is sent, and the mail queued; a failed signup insert answers with an error; the signup's `source` passed by the footer and newsletter forms; a consent timestamp column; WordPress privacy exporter and eraser for both; the footer shows one consent sentence, not two; unique element ids when two forms share a page | CNT-03, CNT-04, MKT-01, ACC-02 | Tests: a spoofed header does not reset the limit; a message survives a failing mailer and appears in WP admin; an insert failure is not reported as success; Playwright sees one consent sentence | none | open |
| WP-06 | **Wire outgoing mail before the token exists.** A provisioning step configures FluentSMTP for Postmark from `POSTMARK_SERVER_TOKEN` (sender `support@cigarholic.com`, message stream per environment) and leaves it alone when the token is empty; remove the unused `CIGARHOLIC_SMTP_*` constants or use them for Mailpit; a `wp cigarholic mail test` command that sends one message and reports the transport's answer | OPS-04, ORD-03 | With the test token `POSTMARK_API_TEST` the step configures the connection and the command reports Postmark's acceptance; with no token nothing changes; `.env.example` says what is true | Delivery itself: OWNER-ACTIONS B1 | open |
| WP-07 | **Backups that run today.** Without the Backblaze variables the nightly job makes a local dump of the database and an archive of uploads, keeps seven, and exits 0 with a warning; with them it uploads as designed. `status.sh` shows the age of the newest local and remote backup; the monitoring runbook gets a check for it. Correct `backup-restore.md`. Then, when B2 exists: first upload, and one restore drill into staging recorded in `docs/history/server-runs.md` | OPS-05 | The timer succeeds on the server with no credentials (status run); a restore of that dump into staging works; after B2, `backup-restore.yml list` shows a nightly folder | Off-site copy and the drill: OWNER-ACTIONS B2 | open |
| WP-08 | **PHP 8.5 gate.** Run the full plugin set on staging with deprecations logged, walk the browse, cart, checkout and order path, and read the log. Record the result in ADR 0001; if a plugin fails or logs deprecations on the checkout path, pin PHP 8.4 as the ADR says. The catalog and cart part can run now; the payment part runs with WP-20 | FND-07, OPS-01 | A dated result in ADR 0001 and `docs/versions.md` | Payment part: B4, B5 | open |
| WP-09 | **Test every deploy.** After "Deploy staging", a job runs the Playwright smoke subset against staging with the basic-auth secrets. The health check requires HTTP 200 on production (401 without credentials stays acceptable on staging only), also requests the Store API, and fails on a PHP fatal in the provisioning output (exit code, not a text match). The deploy runbook says what a rollback does not restore (database, plugin activation) | FND-12, FND-08 | A deliberately broken template on a test branch makes the staging smoke job fail; `deploy.md` and `rollback.md` match the code | none | open |
| WP-10 | **Accessibility checks in CI.** `@axe-core/playwright` on home, category, product, cart, checkout, account, order received, coming soon, 404, search and a journal post, at phone and desktop width; zero serious or critical violations | CMP-04 | The spec fails on a removed form label (mutation check) and passes on `main` | none | open |
| WP-11 | **Test debt.** TD-01 to TD-10 of `docs/TESTING.md`; a test of the redirect hosts against the shipped Nginx templates (`www`, `max2lab.com`, HTTP to HTTPS, path and query kept, one hop); the two-factor rule also applies to an account that only uses application passwords (start the grace period at the first authenticated request of any kind) and gets a Playwright test of the restricted state | FND-12, OPS-07 | CI fails when a test is skipped; each TD row closed or re-dated | none | open |
| WP-12 | **Tests for store behaviour that needs no gateway.** Matrix rows a, b, c (zones and the rejected address), d, e, h and the CLI exit code of row i: variation selection in the browser, duplicate SKU on save, stock hold and restore, coupons, guest and account checkout with refusals, a customer cannot open another customer's order | CAT-01, CAT-08, CHK-01, CHK-03, CHK-04, ACC-01 | Each row of `docs/TESTING.md` section 5 moves to Covered | none | open |
| WP-13 | **Server hygiene.** Logrotate with the `su` line and a rotation for WooCommerce logs (OPS-18); a separate Valkey database per site and a check that a staging cache flush leaves production's keys alone; `open_basedir` per pool and production logs unreadable by the staging user; page cache purged when a product, a page or the announcement bar is saved; `/favicon.ico` routed to WordPress; `.log`, `.sql`, `.bak` denied in uploads; `status.sh` reports a pending reboot; the hostname no longer printed in logs | OPS-07, OPS-09, OPS-18 | `tests/infra/` cases for each Nginx rule; a status run shows rotated logs after a day and the two cache databases | Approved `provision` and `site-production` runs | open |

When the owner hands these over, they jump the queue: **B2** (finish WP-07), **B1** (verify WP-06 with real delivery; SPF, DKIM, DMARC records), **B3** (run the Monitoring workflow), **B6** (Turnstile keys; then the forms are challenged).

## Stage 2: P0 that needs no credentials

| WP | What | IDs | Done when | Status |
|---|---|---|---|---|
| WP-14 | **One source for business and policy facts, and toggles that are boundaries.** A "Store facts" settings group (legal name, addresses, phone, support email, ship-from city; returns window, return-shipping payer, warranty length, damage-report window) read by the plugin, the theme patterns (through a block binding or a small block, not PHP in patterns), provisioning, emails and structured data. A feature that is off prints and sends nothing: callers check the toggle, and the registry can declare that one feature requires another. Move the Prop 65 output out from under the `catalog` toggle | CMP-12, FND-05 | A grep finds each fact once; a test switches `ship-time` off and finds no promise on the order page or in an email; the policy drafts show the settings' values | open |
| WP-15 | **Product structured data, the rest.** `brand`, `itemCondition`, Offer `shippingDetails` from the zones and the ship time, `hasMerchantReturnPolicy` from the store facts once the owner sets the window (L-08) | SEO-02 | A test reads the JSON-LD of a sample product; the Rich Results Test is run by hand after launch | open |
| WP-16 | **Cookie notice, Consent Mode and analytics as code.** Complianz provisioning (US opt-out banner, Consent Mode defaults); Google Analytics for WooCommerce from `GA4_MEASUREMENT_ID`, off when the variable is empty | CMP-03, MKT-02 | A Playwright test sees the notice and no analytics request before consent where consent is required; a dry run after provisioning shows no change | open (measurement needs B7) |
| WP-17 | **Images.** WebP/AVIF output, the size set, lazy loading and the largest image of each template preloaded; a budget test for the product gallery | OPS-09, CAT-05 | The brand spec's image budget covers product and category pages | open |
| WP-18 | **Launch documents and messages.** The out-of-zone checkout message (CHK-13); the transit-damage process in the returns policy draft, the FAQ, the shipping email and the manager guide (SHP-15); the photo and copy standard as `docs/photo-standard.md` (CNT-14); policy page drafts completed from the store facts (CNT-02) | CHK-13, SHP-15, CNT-14, CNT-02 | A Playwright test enters a Hawaii address and reads the message; the documents exist and the owner has them in OWNER-ACTIONS C3 | open |
| WP-19 | **Manager's handbook, the release gate, and provisioning that respects the manager.** Handbook sections for products, prices, sales, orders, refunds, pages, with screenshots from staging (ORD-05). The pre-release gate workflow (FND-16). Provisioning creates the accessory rate and the notification recipients once and never resets them; it keeps menus it did not create; coming soon becomes a setting with a provisioned default per environment, so go-live is the owner's switch plus one line, not an edit of step 80 | ORD-05, FND-16, FND-03 | The gate runs on the next tag and its report is in the release notes; a test changes the accessory rate and a second provisioning run leaves it | open |

## Stage 3: payments and tax on staging (milestone M2)

Blocked on OWNER-ACTIONS B4 and B5.

| WP | What | IDs | Done when | Status |
|---|---|---|---|---|
| WP-20 | Stripe and PayPal test mode provisioned from the staging secrets; express buttons on product, cart and checkout; webhooks registered and reaching staging through the basic-auth exemptions; refunds full and partial; failed and pending payments; a duplicate webhook delivery processed once; the manager's failed-payment notice | PAY-01 to PAY-06, ORD-02 | Matrix rows f and g covered by tests against test mode from a workflow; the PHP 8.5 gate (WP-08) passed on this path | blocked (B4, B5) |
| WP-21 | Stripe Tax on staging: tax code on every product, TX and FL test addresses, another state without tax, totals asserted. TaxJar only if this fails (D-07) | CMP-01 | Matrix row c covered | blocked (B4) |
| WP-22 | Card-testing protection: WooCommerce's Store API rate limiting on, the Nginx limits reviewed for the express-payment endpoints, a Turnstile challenge on checkout behind a toggle (off by default), an alert when declines spike | PAY-11 | A burst of checkout attempts from one address gets 429 in a test; the toggle shows the challenge | open (the limits need no keys) |
| WP-23 | Integration scaffolding, built with its first real user (the payment follow-ups or the email-platform sync): a queued job helper on Action Scheduler with an idempotency key, retry with backoff, a log line per attempt, and a secrets accessor that reads `.env` | INV-03 (first slice), SHO-01 | The first integration uses it and a test delivers the same job twice with one effect | open |

## Stage 4: production ready (milestone M3)

| WP | What | IDs | Needs | Status |
|---|---|---|---|---|
| WP-24 | The real catalog: attribute terms from the material sheet, products imported from the CSV template, `catalog check` green, sample data removed from staging, tax codes and shipping classes set | CAT-01 to CAT-10, CMP-02, CMP-10, CMP-11, INV-01, SEO-05 | B9, B12, C3 | blocked |
| WP-25 | Pages published: About, Contact, FAQ, the policies, the accessibility statement; every footer and menu link answers 200; real social links or none | CNT-02, NAV-01, NAV-03 | C3, B13 | blocked |
| WP-26 | The Shop Manager account, two-factor, and the handbook walkthrough: the manager completes a product edit, an order, a refund and a tracking update alone on staging | ORD-04, ORD-05 | C4 | blocked |
| WP-27 | Budgets measured: Lighthouse in CI for home, category, product, cart and checkout on a phone profile (LCP, CLS, accessibility), and by hand on staging | OPS-10 (brought forward for the go-live line), CMP-04 | none | open |
| WP-28 | Server access closed down: `SSH_USER` is `deploy`, root SSH login off, the root-only procedures written for the provider console | OPS-16 | Owner changes one secret | open (after WP-01, WP-03, WP-13) |
| WP-29 | Search Console and Bing verified, sitemap submitted; monitoring live; sender authentication verified in Gmail and Outlook | SEO-04, OPS-06, OPS-04 | B1, B3, B10 | blocked |

## Stage 5: go-live (milestone M4)

| WP | What | Needs |
|---|---|---|
| WP-30 | The owner connects live Stripe and PayPal; Apple Pay domain verification; the real-money matrix of `docs/CHECKLIST.md`; coming soon off; outside checks; the launch email (MKT-16) | C1, C2, C5, D-04 |

## After launch

The order of P1 is in `docs/ROADMAP.md`, stage 6. Each item becomes a WP row here when it is next.

## Small fixes without a package

Take these along with any PR that touches the file:

- `Logger::log` overwrites a context key named `source`.
- `ContactForm`: an `array_filter` whose callback always returns true; a hard-coded support address (goes with WP-14).
- `provision/steps/70-emails.php`: stale comment about the logo.
- `Plugin::url()` is unused.
- `docker/php/php.ini` says production sets `opcache.validate_timestamps=0`; it does not.
- `patterns/gift-landing.php`: "Placeholder for a photo" alt texts.
- The README of `cigarholic-core` gives `engraving:off` as a toggle example; no such toggle exists and unknown slugs are ignored silently (warn in `wp cigarholic features`).

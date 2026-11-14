# Architecture

How the Cigarholic store works, how it is built and run, and what the review of 2026-11-13 found. The decision that came out of the review is `docs/adr/0010-architecture-review-2026-11.md`: the architecture stays, the gaps in section 7 get fixed in the order of `docs/PLAN.md`.

Last updated: 2026-11-13.

## 1. What the store is

An online store for handcrafted wooden humidors, cigar boxes and humidor accessories, made in Vietnam and sold by HuyLinh LLC to consumers in the contiguous United States, in US dollars. It sells no cigars, tobacco or nicotine, has no age gate, and never implies that anything is made in the USA.

Three kinds of people use it:

| Who | Does what | Where |
|---|---|---|
| Customer | Finds a humidor, reads its specifications, pays, gets emails and tracking | The storefront |
| Store manager (non-technical) | Products, prices, sales, orders, refunds, tracking numbers, pages, the announcement bar, the ship time | WP admin, Shop Manager role, two-factor |
| Owner | Accounts, money, contracts, legal decisions, release approval | GitHub (tags, approvals, secrets), WP admin |

AI agents do all development and maintenance through pull requests; they never edit a server by hand.

## 2. How the store should work

### For a customer

1. **Arrive.** Until the owner opens the store, every visitor sees the coming-soon page and can leave an email address. After launch: a home page (hero, shop by category, best sellers, the craft story, why buy here, newsletter), category pages at `/collections/<slug>/`, search, and the journal at `/journal/<slug>/`.
2. **Choose.** A product page at `/products/<slug>/` always has the same order: summary and price, ship-time promise, specification table in inches and centimetres and pounds and kilograms, what is in the box, care and seasoning, shipping and returns, "Handcrafted in Vietnam". Variations exist only where the SKU changes (finish, size).
3. **Buy.** Block cart and checkout, guest checkout allowed, US addresses only, phone required for the carrier, terms accepted. Payment by card, Apple Pay, Google Pay or Link through Stripe, or PayPal, Venmo or Pay Later through PayPal. Sales tax is calculated for the states where the business has nexus. An address the store does not ship to gets a clear explanation (CHK-13), not an empty list.
4. **After the order.** The order-received page and the confirmation email say the same things in the same words: when it ships, what happens to a backordered item, that a tracking email follows, how to reach support. When the manager enters the tracking number, one email goes out with the carrier and a tracking button, and the order completes.
5. **If something goes wrong.** A delay: the customer is told the new date and may cancel (FTC Mail Order Rule; ORD-15 automates it later). Damage in transit: one documented process (SHP-15). A return or refund: by the published policy, refunded through the gateway from WP admin.

### For the manager

Everything the manager needs is in WP admin and in `docs/manager-guide.md`. Settings that change with the business (ship time, announcement bar, contact address, and after CMP-12 the returns window and warranty length) are fields the manager edits; every page, email and structured-data block reads them, so they cannot disagree. Layout and design are not the manager's job: a theme file edited in the Site Editor is reported by every deploy, never silently overwritten.

### For the owner

`main` deploys to staging by itself. Production changes only when the owner creates a version tag and approves the deploy. Live payment credentials are connected by the owner in production WP admin and never pass through GitHub or an agent. What the owner must provide or decide is in one file, `docs/OWNER-ACTIONS.md`.

### Truth and integrity rules

- WooCommerce is the system of record for products, stock, orders and customers. Any sync (Shopify, an inventory platform, a 3PL, the email platform) is a separate module behind a switch, and the store works with it off.
- Payment truth comes from the gateway's verified webhook, never from the browser returning.
- Anything with a side effect assumes duplicates, retries and partial failure: an idempotency key and a queued job.
- A promise shown to a customer (ship time, returns window, stock) is read from one setting and must be true.

## 3. Components

```text
Browser
  │  HTTPS (Let's Encrypt on the origin; Cloudflare may sit in front later, ADR 0008)
  ▼
Nginx on the VPS ── redirect hosts (www, max2lab.com) → https://cigarholic.com
  │   security headers, rate limits (login, checkout), hard denies (installer, xmlrpc)
  │   FastCGI page cache (10 min; bypassed for cart, checkout, account, REST, logged-in, non-GET)
  ▼
PHP-FPM 8.5 (one pool per site)
  │   WordPress 7.1 (Bedrock layout) + WooCommerce 11.2 (HPOS, block cart and checkout)
  │   ├─ plugins (11 active, pinned in composer.lock, register in docs/plugins.md)
  │   ├─ mu-plugin cigarholic-core   all custom PHP, one class per feature, toggles
  │   └─ theme cigarholic            block theme: theme.json tokens, templates, parts, patterns
  ├──► MariaDB 11.8 (one database and user per site)
  ├──► Valkey 9 (object cache)
  └──► outside services: Stripe, PayPal, Stripe Tax, Postmark, Turnstile, GA4 (none connected yet)
```

Staging (`staging.cigarholic.com`, basic auth, `noindex`) and production (`cigarholic.com`) are two sites on the same VPS (Ubuntu 26.04), each with its own system user, directory, `.env`, database and PHP-FPM pool. Development, CI and laptops run the same versions in Docker Compose (`make up`), with Mailpit trapping mail.

### Repository

| Path | Holds | Rule |
|---|---|---|
| `composer.json`, `composer.lock` | WordPress core, plugins, dev tooling, all pinned | Updates only through PRs |
| `config/` | Bedrock configuration per environment | `.env` never committed |
| `web/app/mu-plugins/cigarholic-core/` | Every line of custom PHP: `src/<Area>/<Feature>.php`, dynamic blocks in `blocks/`, WooCommerce template copies in `templates/` | PSR-4 `Cigarholic\Core\`, WordPress Coding Standards, PHPStan level 6, PHP 8.3 syntax |
| `web/app/themes/cigarholic/` | Presentation only | No PHP logic beyond setup |
| `provision/steps/NN-*.php` | Settings as code, applied by `wp cigarholic provision` after every deploy | Idempotent: a second run changes nothing |
| `infra/` | Server provisioning, site creation, Deployer recipe, backup, Cloudflare and monitoring scripts | Explicit environment argument, shellcheck clean, tested in `tests/infra/` |
| `.github/workflows/` | CI, deploys, server jobs | The only way anything reaches the server |
| `tests/` | PHPUnit, Playwright, infrastructure shell tests | `docs/TESTING.md` |
| `docs/` | Scope, plan, status, decisions, runbooks | Section 8 |

### The custom plugin

`Plugin::boot()` declares HPOS and block-checkout compatibility, then `Features::register_all()` creates and registers every enabled feature class. A toggle resolves from its default in `Features::REGISTRY`, then `CIGARHOLIC_FEATURES` in `.env`, then a filter. What it defines today:

| Kind | Items |
|---|---|
| Features (18 toggles, all on) | development, hardening, two-factor (off on staging), robots-txt, website-schema, email-next-steps, signups, announcement-bar, contact-form, catalog, sku, spec-table, csv, check, seed, ship-time, order-next-steps, tracking |
| Blocks | announcement-bar, contact-form, order-next-steps, ship-time, signup-form, spec-table (dynamic, no build step) |
| Database | One table, `cigarholic_signups`. Product meta `_ch_*` for specifications and private compliance fields. No order meta of its own |
| Taxonomies and attributes | `occasion`; six fixed product categories; eight global attributes; three shipping classes |
| REST | `POST cigarholic/v1/signups`, `POST cigarholic/v1/contact` (public) |
| CLI | `wp cigarholic version`, `features`, `provision`, `seed`, `catalog check`, `catalog ensure`, `signups count/list/export/delete` |
| Queued jobs | None yet |

### Deploy pipeline

A push to `main` deploys staging; a tag `vX.Y.Z` plus the owner's approval deploys production. The recipe (`infra/deploy/deploy.php`), in order: write the application keys from GitHub secrets into the server's `.env`; build a new release directory with `composer install --no-dev`; on production take a database snapshot; `wp core update-db`; activate WooCommerce, then every plugin of the release; `wp wc update`; `wp cigarholic provision`; switch the `current` symlink; reload PHP-FPM; purge the page cache; flush the object cache; request the home page and roll the symlink back if it does not answer; keep five releases.

## 4. Environments

| | Development, CI | Staging | Production |
|---|---|---|---|
| Where | Docker Compose | VPS, `/var/www/staging` | VPS, `/var/www/production` |
| Deployed by | `make up` | every push to `main` | owner's tag and approval |
| Visitors see | Coming soon; the store when logged in | Basic auth, then coming soon | Coming soon |
| Payments | Cash on delivery only | Stripe and PayPal test mode (not connected yet) | Live, connected by the owner (not yet) |
| Mail | Mailpit | Postmark sandbox (not wired yet) | Postmark (not wired yet) |
| Data | 7 `SAMPLE-` products | 7 `SAMPLE-` products | No products |
| Two-factor | Not required | Not required (DEC-31) | Required for administrators and shop managers |

## 5. Review verdict

**Keep.** The choices of ADR 0001 to 0009 hold up against two weeks of real use:

- The Compose stack, CI and the server run the same minors, and every server change is a script with a test or a workflow log. The two server incidents so far were shell bugs caught within minutes, and each produced a test.
- Settings as code works: a deploy re-applies them and reports drift; the one case of a manager-edited template was detected and left alone.
- The custom code is small (28 classes), typed, linted with no baseline and no ignored errors, and uses WooCommerce's own APIs. No raw SQL on commerce tables, no order query outside HPOS, nonce and capability checks on every admin action, escaped output in every render file and template. No tobacco, age-gate or "Made in USA" wording anywhere.
- Using WooCommerce's own Order Fulfillments for tracking kept the plugin count at 11 of 15.

**No reimplementation.** A different platform, a headless front end or a page builder would cost the URL structure, the data model, the tests and the manager's workflow, and solve none of the gaps below. The theme and the brand system need no redesign.

## 6. What works, verified

Reading and tests agree on these: coming-soon page with signup and CSV export; announcement bar with dates; contact form; catalog data model, SKU rules, spec table, CSV import, `catalog check`; ship-time promise on product, cart, checkout, order page and emails; tracking and the shipping email; two-factor requirement; hardening (XML-RPC, author pages, user REST); robots.txt and sitemaps; SEOPress configured as code; brand tokens with contrast tests; atomic deploys with rollback; staging basic auth with exact webhook exemptions.

## 7. Findings

Severity is for a store about to take payments. Each was checked against the code on 2026-11-13; "to confirm on the server" marks what only a server run can show. WP numbers refer to `docs/PLAN.md`.

### High

| # | Finding | Where | Fix |
|---|---|---|---|
| H1 | The production approval can be bypassed: the WP-CLI workflow places its `command` input into a shell script run on the server, so a staging run (no approval) can change directory and act on production | `.github/workflows/wp-cli.yml` (the `Run` step) | WP-01 |
| H2 | Workflows that act on production without the approval rule: server provisioning (all actions, including `site-production` and `disable-root-login`), backup and restore, Cloudflare. All SSH secrets are repository-wide and `main` is unprotected, so any workflow file on any branch pushed by a collaborator can use the key | `infra-provision.yml`, `backup-restore.yml`, `cloudflare.yml` | WP-01, OWNER-ACTIONS A12 |
| H3 | The `deploy` user may run `/usr/bin/certbot` as root with any arguments; certbot hooks run commands, so `deploy` is root in effect. No script calls certbot as `deploy` | `infra/server/sudoers/cigarholic-deploy` | WP-01 |
| H4 | No off-site backup, and the nightly timer fails every night: `backup.sh` exits before dumping when the Backblaze variables are missing. The only copies are the last ten pre-deploy database dumps on the same disk; uploads are not copied | `infra/backup/backup.sh`, `infra/server/systemd/cigarholic-backup.service` | WP-07, OWNER-ACTIONS B2 |
| H5 | Nothing drives WordPress cron or Action Scheduler: `DISABLE_WP_CRON` is not set and no timer runs due events. On staging the loopback request is behind basic auth (to confirm on the server) | `config/application.php`, `infra/server/` | WP-03 (OPS-15) |
| H6 | Outgoing mail is not wired: `POSTMARK_SERVER_TOKEN` reaches `.env` and no PHP or provisioning step reads it; FluentSMTP is active and unconfigured; the server has no mail transfer agent | `config/application.php`, `provision/steps/`, `.env.example` | WP-06 (OPS-04) |
| H7 | The public forms are effectively unthrottled: the rate limit trusts the `CF-Connecting-IP` request header, which any client can set while Cloudflare is not in front; Nginx has no limit on these routes; Turnstile verification passes when no keys are configured, which is the case on both servers | `Signups.php` (`client_ip`), `ContactForm.php`, `Turnstile.php` | WP-05, OWNER-ACTIONS B6 |

### Medium

| # | Finding | Fix |
|---|---|---|
| M1 | A contact message is stored nowhere. If mail is not delivered, it is lost, and in one case the visitor is still told it arrived | WP-05 |
| M2 | A signup whose database insert fails is reported to the visitor as a success; the `source` of a signup is always `coming-soon`; no consent timestamp; no WordPress privacy exporter or eraser for the table | WP-05 |
| M3 | Staging deploys start on push without waiting for CI, no check is required on `main`, and nothing tests a deploy beyond one request for the home page (which also accepts a redirect) | WP-04, WP-09 |
| M4 | Deploy steps that change the database run while the old release still serves, and a rollback restores neither the database nor plugin activation; a provisioning failure is detected only by matching `Error:` in its output | WP-09 |
| M5 | Staging and production share one Valkey database without authentication (prefix only), PHP pools have no `open_basedir`, production logs are readable by the staging user, and a staging deploy reloads production's PHP workers. Whether `wp cache flush` on staging empties production's cache is to confirm on the server | WP-13, OPS-19 |
| M6 | Toggles are not boundaries: `ship-time` off still prints the promise on the order page and in emails, because other features call it directly; switching `catalog` off would remove the Prop 65 warning. The toggle filter runs before any plugin can hook it | WP-14 |
| M7 | No queued work anywhere and no shared pattern for integrations (idempotency key, retry, log, secrets accessor) | WP-23 |
| M8 | Business facts (addresses, phone, support email, "Houston, Texas") are written out about forty times across plugin, theme and provisioning | WP-14 (CMP-12) |
| M9 | Provisioning resets values that belong to the manager on every deploy: the accessory shipping rate, the order and stock notification recipients; it deletes every navigation menu but its own; going live is a code change to step 80 | WP-19 |
| M10 | The page cache is purged only on deploy: a price, stock or announcement change can take up to ten minutes to show for visitors | WP-13 |
| M11 | Log rotation for the sites' logs is configured without the `su` line that logrotate needs for group-writable directories, so it probably skips them (to confirm on the server); WooCommerce logs have no rotation | WP-13 (OPS-18) |
| M12 | No Content-Security-Policy; actions in workflows are pinned to tags, not commit hashes, including in the jobs that hold the SSH key | OPS-17; WP-04 |
| M13 | The two-factor grace period starts at a login or an admin page view, so a privileged account used only through application passwords is never restricted | WP-11 |
| M14 | The shipping email depends on WooCommerce-internal classes and four template copies; WooCommerce 11.3.0 already changes both (5 failing tests in the update PR of 2026-11-12) | WP-02 |

### Low

Text in `.html` templates and two JavaScript fallbacks is not translatable; pattern files hold data arrays and call a plugin class; duplicate helper code between the signup and contact forms; a fixed element id duplicated when two signup forms share a page; SKU validation runs on admin saves only (the catalog check catches the rest); footer and menu links point at draft pages and will 404 after launch; placeholder social links; uploads allow `.log`, `.sql` and `.bak` to be served; no automatic reboot after kernel updates and no report of a pending one; the deploy log prints the server hostname. Several runbook sentences do not match the code (nightly local dump, health check wants 200, daily log rotation, "production needs approval" for WP-CLI); they are corrected as each work package lands, and `docs/HANDOVER.md` lists them until then.

## 8. Documents

| Question | Document |
|---|---|
| What is in scope, with which priority? | `docs/FEATURES.md` |
| Where are we, what is blocked? | `docs/STATUS.md` |
| What is next, with acceptance criteria? | `docs/PLAN.md` |
| When, and depending on what? | `docs/ROADMAP.md` |
| What does the owner need to do? | `docs/OWNER-ACTIONS.md` |
| Why was it done this way? | `docs/DECISIONS.md`, `docs/adr/` |
| How is it tested, what is not? | `docs/TESTING.md` |
| What must pass before a release or go-live? | `docs/CHECKLIST.md` |
| How do I start or end a session? | `docs/HANDOVER.md` |
| How do I operate the servers? | `docs/runbooks/` |
| How does the manager run the store? | `docs/manager-guide.md`, `docs/catalog.md` |
| What happened, run by run? | `docs/history/`, `CHANGELOG.md` |
| The original brief and launch plan | `docs/KICKOFF-BRIEF.md`, `docs/LAUNCH-PLAN.md` |

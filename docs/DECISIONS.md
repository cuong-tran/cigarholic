# Decisions

Every decision that shapes the build, in one place: what was decided, why, and where it is recorded. Three kinds live here.

- **Architecture decision records** (`docs/adr/`): one file per decision that is expensive to change. Never rewritten; a superseded ADR gets a new one that links back.
- **Owner decisions** (D-01 to D-11): choices only the owner can make. The default in force is used until the owner says otherwise; the owner answers in `docs/OWNER-ACTIONS.md`.
- **Working decisions** (DEC-01 onwards): how the team builds and operates, taken during the work. Each says why, so a later agent can tell whether the reason still holds.

How to add one: append it with the next number, the date and the PR. Never renumber, and never edit the meaning of an old entry; write a new entry that says which one it replaces.

Last updated: 2026-11-13 (project revision: this file created from the decision list in `docs/STATUS.md`; DEC-32 to DEC-41 added).

## 1. Architecture decision records

| ADR | Decision | Status | Reviewed 2026-11-13 |
|---|---|---|---|
| [0001](adr/0001-platform.md) | WordPress + WooCommerce on Bedrock, PHP 8.5 VPS with 8.3-compatible code, Compose runtime, raw VPS, Composer-managed updates | accepted | Holds. The PHP 8.5 gate before M2 is still open (`docs/PLAN.md`, WP-08) |
| [0002](adr/0002-urls.md) | URLs `/products/`, `/collections/`, `/journal/` | accepted | Holds; live on production |
| [0003](adr/0003-catalog-data-model.md) | Catalog data model, SKU scheme, compliance fields, sample-data rule | accepted | Holds; attribute terms are placeholders until the material sheet (B12) |
| [0004](adr/0004-payments.md) | Stripe + PayPal official plugins, webhook truth, idempotent order code, Stripe Tax | accepted | Holds; nothing built on it yet (waits for B4/B5) |
| [0005](adr/0005-shopify-independence.md) | WooCommerce is the system of record; Shopify never a runtime dependency | accepted | Holds |
| [0006](adr/0006-secrets-channel.md) | One secrets channel: GitHub secrets → workflows → server `.env` | accepted | Holds |
| [0007](adr/0007-dev-tools-as-phars.md) | PHPStan, PHPUnit and Deployer as pinned phars | accepted | Holds |
| [0008](adr/0008-edge-optional.md) | Cloudflare is additive; the origin alone serves TLS, redirects and rate limits | accepted | Holds; Cloudflare still deferred by the owner (A2) |
| [0009](adr/0009-brand-system.md) | Brand system as semantic theme tokens, Forest section style, self-hosted fonts | accepted | Holds |
| [0010](adr/0010-architecture-review-2026-11.md) | Architecture review of 2026-11-13: keep the architecture, fix the listed gaps | accepted | New |

## 2. Owner decisions (defaults in force)

The owner confirms or changes these in `docs/OWNER-ACTIONS.md` (B11). "Built on it" says how much code already assumes the default, which is what a change would cost.

| ID | Decision | Default in force | Built on it |
|---|---|---|---|
| D-01 | Theme base | Custom block theme `cigarholic` | Settled: the theme and brand system are live (ADR 0009) |
| D-02 | Fulfillment at launch | Manual hand-off (CSV or portal, tracking pasted by the manager); 3PL connector in P1 | Tracking entry and the shipping email (SHP-04) |
| D-03 | Engraving | Not in P0; CAT-13 in P1 as a module, fulfilled in the US | Nothing yet |
| D-04 | Email platform | Undecided; signups stored by `cigarholic-core` and exported later | Signups table and CSV export. **Now on the critical path for the launch email (MKT-16)** |
| D-05 | Shipping prices | Free shipping on humidors (priced in), flat rate by class for accessories, oversized class for cabinets | Provisioning step 50 (rates are placeholders until L-07) |
| D-06 | Zones | Contiguous US only | Provisioning step 50 |
| D-07 | Sales-tax tool | Stripe Tax; TaxJar is the fallback | Plugin installed, not configured (waits for B4) |
| D-08 | Prop 65 | No warning by default; per-product toggle | Toggle and warning text exist (CMP-06) |
| D-09 | SEO plugin | SEOPress | Settled: configured as code (step 75) |
| D-10 | Helpdesk | Later (P1); launch support is the contact form to `support@cigarholic.com` | Contact form (CNT-03) |
| D-11 | **New.** May product and lifestyle photos show cigars? | No cigars, tobacco or smoke in any image (DEC-30), until the owner decides | Sample photos. The launch plan's photo brief says "a hand or cigar for scale"; the two disagree, see `docs/OWNER-ACTIONS.md` B14 |

Also in force: raw VPS without a panel; apex `cigarholic.com` as the one canonical host; `docs/STATUS.md` as the progress tracker.

## 3. Working decisions

DEC-01 to DEC-31 were taken between 2026-10-30 and 2026-11-06 and are copied here unchanged from `docs/STATUS.md` (where they were "decision 1" to "decision 31"; older documents that say "decision 18" mean DEC-18).

- **DEC-01.** **Branches:** one `feat/<ID>-<slug>` branch and PR per feature ID, cut from `origin/main` after the previous merge (a branch cut from an unmerged branch shows duplicate commits in its PR after a squash merge; never force-push to fix that, cut a new branch instead).
- **DEC-02.** **Dev tools as phars** (ADR 0007): the sandbox can fetch GitHub packages only by cloning; PHPStan and PHPUnit repositories are gigabytes. PHPUnit stays on 9.6 because the WordPress test suite (`wp-phpunit` 7.1.3) still calls PHPUnit 9 APIs.
- **DEC-03.** **Composer-managed updates only** (ADR 0001): in-place WordPress auto-updates are off; a scheduled update-PR workflow replaces them (FND-13 brought forward for security releases, to do). The owner can overrule.
- **DEC-04.** **Containers trust the sandbox gateway CA** instead of using proxy variables (TLS from containers is intercepted transparently); WordPress uses the system store in development through `https_ssl_verify`.
- **DEC-05.** **Staging deploys are gated** on the marker file `infra/deploy/STAGING_ENABLED` so pushes to `main` do not fail until the server exists; production deploys run from a tag with the `production` Environment approval (public repository), the tag-input manual run is the fallback.
- **DEC-06.** **Cloudflare is additive** (ADR 0008): the origin alone serves TLS (Let's Encrypt), the `www`/`max2lab` redirects and the rate limits; Cloudflare scripts exist for later.
- **DEC-07.** **Cash on delivery in development only** exercises cart → order → email without payment keys (ADR 0004); Stripe/PayPal test mode is staging-only.
- **DEC-08.** **Signups are stored by `cigarholic-core`** (own table, CSV export) until the email platform is decided (D-04).
- **DEC-09.** **Runtime pins** match the Ubuntu 26.04 archive minors (PHP 8.5, MariaDB 11.8, Valkey 9.0, nginx 1.28), verified in a container.
- **DEC-10.** **WordPress is installed with WP-CLI only**; the web installer is denied on both sites for good (#36). Production is deployed and installed back-to-back.
- **DEC-11.** **Server secrets are alphanumeric** (32 characters for passwords, 64 for salts): the same value is read by bash, MariaDB and PHP's dotenv, and only alphanumerics mean the same thing to all three (#34).
- **DEC-12.** **Infra shell code gets tests** in `tests/infra/`, run by CI's shell job under `set -euo pipefail` (#35, #36, #37): two of today's three server failures were shell bugs that lint could not see.
- **DEC-13.** **The deploy activates plugins** (WooCommerce first, then every plugin of the `--no-dev` release): the plugin set is `composer.json` plus `docs/plugins.md`, never a manual activation on a server (#40).
- **DEC-14.** **Two-factor is enforced by withholding capabilities, not by route lists** (#46): after the grace period a privileged account without a method keeps only `read` and `view_admin_dashboard`, so WP admin, REST, admin-ajax, admin-post and application passwords all follow from WordPress's own capability checks; roles are never changed. Emailed codes are off while there is no transactional mail. Without the plugin nothing is enforced (fail open with a notice) rather than locking every admin out of a broken release.
- **DEC-15.** **Root SSH stays on for now** (key only, `prohibit-password`). `disable-root-login` closes root, and with it every `provision` and `site-*` workflow run (they need root; afterwards only the provider console works), while Nginx changes are still coming (Cloudflare later, backups; robots.txt and sitemaps landed in #49). The switch to `SSH_USER=deploy` plus `disable-root-login` happens once the server configuration settles, before launch.
- **DEC-16.** **Own PRs are still merged once CI is green** although production exists: merging to `main` deploys staging only; production moves only with a tag the owner creates (brief rule 3). Say so if you want to review PRs before merge from now on.
- **DEC-17.** **Virtual files are routed by name, not by dropping `txt`/`xml` from the static regex** (SEO-01): real `.txt` and `.xml` files (plugin readmes, licences, assets) keep the 30-day static path, and a probe for any other missing `.txt` or `.xml` stays a cheap Nginx 404 instead of booting WordPress. Only `/robots.txt` and the sitemap names WordPress 7.1 and SEOPress 10.4 register (checked in their rewrite rules) go to PHP; a new sitemap plugin or name means a new pattern and test case.
- **DEC-18.** **Checkout stays distraction-free** (owner, 2026-11-03, #56): the checkout page keeps WooCommerce's layout without the site menu, search, account link or footer; only a Forest header with the logo (to home) and the cart link. The terms and privacy links stay in the checkout block (CHK-05). Bringing either back is a one-line change to `templates/page-checkout.html`.
- **DEC-19.** **The ship time belongs to the manager, not to provisioning** (#59): it is a WooCommerce setting with a default in code (1–2 business days), never written by `wp cigarholic provision`, because it must change the moment orders will leave later (FTC Mail Order Rule) without a deploy. Every promise reads it: the product page, the cart line, the order-received card, the trust bar and the FAQ; templates never contain a day count.
- **DEC-20.** **Backordered lines get no ship time** (#59, #60): the product page and the cart promise nothing for them, and the order-received card says they ship when back in stock, that the buyer gets the expected date by email and can cancel. A line counts as backordered when WooCommerce marked it or its product's managed stock is below zero.
- **DEC-21.** **Theme files saved in the Site Editor are reported, never reset by a deploy** (step 85): a saved copy is the owner's or manager's content. Resetting one on production is the owner's call (A9).
- **DEC-22.** **Staging stays in coming soon, like production** (owner, 2026-11-05, A9 question 3): staging shows visitors the launch page and the store only to logged-in staff, behind basic auth and `noindex`. Provisioning step 80 sets it on every site and every deploy; a switch to Live in WP admin lasts until the next deploy (`ProvisionTest::test_switch_to_live_in_wp_admin_is_undone`). Going live (C5) is a change to step 80.
- **DEC-23.** **SEOPress is configured by provisioning step 75-seo, key by key** (#66): the step merges only the keys it owns into SEOPress's option arrays, so the per-item SEO box (titles, descriptions, noindex of a single product or page) stays the manager's, and any other SEOPress setting survives a deploy; the owned keys (templates, sitemaps, knowledge graph) come back with every deploy, like every provisioned setting.
- **DEC-24.** **Cart, checkout and account pages are `noindex` through SEOPress's own per-page setting** (#66), not through a filter: SEOPress deliberately empties WooCommerce's robots directives on those pages, and its per-page meta is the one setting it honours for both the robots tag and the sitemap.
- **DEC-25.** **The home `<title>` stays descriptive; the WebSite structured data says "Cigarholic"** (#66, `Seo\WebsiteSchema`): SEOPress has no setting to separate them, and a one-filter class is smaller than giving up the descriptive title.
- **DEC-26.** **Emails reuse the order-received steps** (#67): one function words the packing promise, backorders, tracking and payment check for both the page and the emails, so they cannot disagree (FTC Mail Order Rule). The emails leave out the support step and the seasoning tip; every email footer already carries the support contact.
- **DEC-27.** **Tracking uses WooCommerce's own Order Fulfillments, not a plugin** (#71): WooCommerce 11.2 ships the manager's panel, carrier detection with tracking links for 77 carriers, the shipment record on the order, the customer emails and a REST route a 3PL connector (SHP-06) can write to. Core before Plugin; Advanced Shipment Tracking is no longer needed. FEATURES.md still says "Plugin" for SHP-04; that column is a suggestion and scope is unchanged.
- **DEC-28.** **One action ships an order** (#71): a processing order whose items have all shipped is completed by `Shipping\Tracking`, and WooCommerce's "Completed order" email is skipped for such an order, because the shipping email already told the customer (like a shop's single "shipping confirmation"). An order completed by hand without a shipment still gets it; an order on hold is never completed by a shipment. Shipping with the notification off, or with the bulk "Mark as fulfilled", therefore emails nobody: the manager guide says not to.
- **DEC-29.** **WooCommerce templates the store rewrites are served from `cigarholic-core/templates/`** (#71) through the `wc_get_template` filter, only when WooCommerce would use its own (a theme override still wins), and each copy records the WooCommerce template version it follows: `ShippingTrackingTest` fails when WooCommerce changes one, so an update cannot silently ship a stale copy. Logic stays in the class; the templates only print.
- **DEC-30.** **Sample photos are rendered from code, not stock or generated by a hosted AI** (owner request, 2026-11-06, Turn 10): `scripts/sample-photos/` builds every image with three.js in headless Chromium from procedural materials, so there are no licences, no third-party uploads, and anyone can rebuild them. The homepage images ship with the theme (production too, behind coming soon) and are replaced file by file when B9 photos arrive. The product images exist only as `SAMPLE-` seed data, marked "Sample image". No image shows cigars, tobacco or smoke; humidors are shown empty.
- **DEC-31.** **Two-factor is not required on staging** (owner request, 2026-11-06): the staging deploy writes `CIGARHOLIC_FEATURES=two-factor:off` into the staging `.env` (`deploy-staging.yml`), so `Security\TwoFactorRequired` is not loaded there: no grace notice, no profile-only restriction. The Two Factor plugin stays active, so an account that has enrolled is still asked for its code at login. Staging stays behind basic auth and `noindex`. Production is unchanged: its `.env` has no override and two-factor stays required. Switching it back on staging: remove that line from the workflow and run `infra/server/env-upsert.sh staging --unset CIGARHOLIC_FEATURES` (or set `two-factor:on`) with the next deploy.

DEC-32 to DEC-41 were taken on 2026-11-13 in the project revision (ADR 0010).

- **DEC-32.** **The architecture and the design stay; no reimplementation** (ADR 0010). The review found gaps in how the system is operated and guarded, none in its shape. A rewrite would cost the URL structure, the data model, 123 integration tests and the manager's workflow, and fix none of the findings.
- **DEC-33.** **`docs/STATUS.md` is one page; history has its own files.** The status file had grown to 72 KB, more than an agent can read in one call, which defeats its purpose as the first thing a new session reads. The merged-PR table, the server run log with the incident, the test log and the environment report moved unchanged to `docs/history/`; decisions moved here; next actions became `docs/PLAN.md`; the start-up notes became `docs/HANDOVER.md`.
- **DEC-34.** **Every workflow that can change production carries the `production` Environment, and a workflow input never reaches a shell as text.** The approval rule was declared on the deploy only; three other workflows and a command-injection path went around it (findings H1 to H3). Until WP-01 lands, agents keep the rule by hand (`docs/HANDOVER.md` section 3).
- **DEC-35.** **A feature that is off prints nothing and sends nothing.** Toggles are how unfinished work ships safely, so they must be real boundaries: a caller checks the toggle, or the registry declares the dependency (finding M6, WP-14).
- **DEC-36.** **Work that leaves the request is queued with an idempotency key, starting with the first integration.** AGENTS.md already says so; no code does it yet because nothing so far needed it except the contact email. The helper is built with its first real user, not ahead of it (no empty abstractions; WP-23).
- **DEC-37.** **A fact about the business is stored once.** Addresses, phone, support email, ship-from city, returns window, warranty length and damage window become settings read by pages, emails, structured data and provisioning (CMP-12, WP-14). The ship time already works this way (DEC-19).
- **DEC-38.** **Provisioning owns configuration, not the manager's values.** A value the manager is meant to change (shipping rate, notification recipients, menus they add) is created once if missing and never reset; a value that defines the environment (HPOS, permalinks, coming soon default, SEO templates) is re-applied on every deploy. This refines DEC-19 and DEC-23 into a rule (finding M9, WP-19).
- **DEC-39.** **M1 is split.** The coming-soon page went live on 2026-11-03 and M1 was declared, while the brief's M1 also asked for mail, backups and monitoring. The remainder is milestone M1b in `docs/ROADMAP.md` and comes before any feature work, because the live site already holds visitors' email addresses.
- **DEC-40.** **WooCommerce stays on 11.2.x until 11.3.1.** The rule against `x.0` releases on production (brief section 4) applies, and 11.3.0 breaks the shipping email's tests (fulfillment REST route and a changed template). The adaptation is prepared on a branch (WP-02).
- **DEC-41.** **The working launch target is 2027-01-11 to 2027-01-22** (path B of the launch plan), with Valentine's Day as the first campaign. It is a planning assumption, not a commitment: the owner's answer on where the stock is (B14) and the payment approvals decide it.

# Roadmap

Where Cigarholic is going and in which order: the milestones, what each one needs, and the dates that matter. Scope and priorities are in `docs/FEATURES.md`; the work packages for the current stage are in `docs/PLAN.md`; today's state is in `docs/STATUS.md`.

Last updated: 2026-11-13 (project revision).

## 1. Where we are

The coming-soon page has been live on `https://cigarholic.com` since 2026-11-03 and collects email signups. The store behind it works end to end in development and on staging with sample products and a development payment method: browse, product page with a spec table, cart, checkout, order emails, tracking number and shipping email. Production runs release `v0.4.0`.

No real order can be taken yet. What stands between today and the first sale is mostly outside the code: payment accounts, the launch catalog, the fulfillment decision, tax registrations and policy sign-off.

Three things about the live site need attention before anything else, because the site is already public and already stores visitors' email addresses:

| Gap | Risk today | Unblocks with |
|---|---|---|
| No backup leaves the server | A disk failure or a mistake on the VPS loses the signups and the site content | OWNER-ACTIONS B2 (Backblaze B2), then one restore drill |
| Nothing watches the site | An outage or an expired certificate is noticed only when someone looks | OWNER-ACTIONS B3 (UptimeRobot) |
| No transactional email and no Turnstile keys on the servers | Contact-form messages and order emails cannot be delivered; the public forms rely on the honeypot and the rate limit alone | OWNER-ACTIONS B1 (Postmark) and B6 (Turnstile) |

The brief's definition of M1 included all three. M1 was declared on the day the page went live; this roadmap calls the remainder **M1b** and puts it first.

## 2. Milestones

| | Milestone | Done when | Needs from the owner | Status |
|---|---|---|---|---|
| M0 | Repo green | CI green; one command boots the store with sample data; deploy and provisioning workflows exist | Nothing | **Reached 2026-10-31** |
| M1 | Coming soon live | `https://cigarholic.com` serves the coming-soon page with email capture; redirect hosts answer; staging behind basic auth | Server, DNS | **Reached 2026-11-03** (without Cloudflare, which the owner deferred; ADR 0008) |
| M1b | Safe to keep live | Off-site backups run nightly and one restore into staging has been done; uptime and certificate alerts reach the owner; transactional email delivers with SPF, DKIM and DMARC; Turnstile protects the forms; scheduled jobs run from the system clock (OPS-15); secret scanning is on (FND-17) | B1, B2, B3, B6; one repository setting (A12) | **Open.** The code-only parts are in `docs/PLAN.md` stage 1 |
| M2 | Store works on staging | One real product through cart, Stripe test and PayPal sandbox to order, refund and emails; tax on TX and FL test addresses; the Playwright suite green against staging; the PHP 8.5 gate passed | B4, B5 (test credentials), one real product (B9, B12) | Blocked on B4 and B5 |
| M3 | Production ready | Every P0 feature on production behind coming soon; the go-live checklist passed except the real-money tests; the manager handbook complete and walked through; server access closed down (OPS-16) | Group C: catalog, policies, prices, tax registrations, manager account | Not started |
| M4 | Go-live | Real-money matrix passed with the owner; the owner says "turn coming soon off"; the launch email goes to the waitlist (MKT-16) | The owner's explicit go | Not started |
| M5 | First campaign | P1 conversion and operations features that the first campaign needs are live; the campaign landing page and shipping cut-off are published | Offer, budget | Not started |

Each milestone is published as soon as it is reached. `main` stays deployable throughout; unfinished work ships switched off.

## 3. Dates that matter

Today is 2026-11-13.

| Date | What | Consequence |
|---|---|---|
| 2026-11-27 / 11-30 | Black Friday, Cyber Monday | Not a launch date. Payment accounts, catalog and fulfillment are open; the launch plan already called Black Friday too tight. Use the waitlist: nothing to build |
| about 2026-11-29 | USTR decision due in the Section 301 case on Vietnam's IP practices (launch plan, step 5) | Could change the import duty; owner and broker watch it |
| mid-December 2026 | Last sensible day to ship for Christmas delivery | Passed over on purpose unless stock is already in the US **and** B4/B5 arrive this month (path A below) |
| 2027-01-22 | Stripe starts billing its paid Radar tiers | Decide before then whether custom Radar rules are needed (PAY-07); the free tier stays |
| **2027-01-11 to 01-22** | **Target window for go-live (M4)** | Leaves three weeks of live trading before the Valentine's shipping cut-off |
| about 2027-02-08 | Order cut-off for Valentine's Day delivery (depends on the ship time and the carrier) | Announcement bar and cut-off banner (NAV-02, SHP-09) |
| 2027-02-14 | Valentine's Day | First campaign (M5) |
| 2027-06-20 | Father's Day | The biggest campaign of the year: engraving (CAT-13), gift collections (CAT-14), gift option (CHK-07) and a load test (OPS-14) should be live by mid-May |

### Two paths, decided by where the stock is

The launch plan asked where the first stock is and has no recorded answer (OWNER-ACTIONS B14).

- **Path A, stock already in the US.** A soft launch in December is still possible only if the payment accounts are approved and test credentials arrive within about a week, and the launch catalog (photos, specs, prices, policies) follows within two. The build is not the constraint: M2 takes about a week from the credentials, M3 about two more.
- **Path B, stock still in Vietnam or not yet shipped (the working assumption).** Go-live in the January window above. November and December go to M1b, the P0 work that needs no credentials, and M2 as soon as B4 and B5 arrive.

The roadmap below follows path B. Under path A the same stages run in the same order, compressed.

## 4. Stages

Each stage lists what gets built, in priority order. The work packages with acceptance criteria are in `docs/PLAN.md`.

### Stage 1: make the live site safe (now, target 2026-11-27)

M1b. Scheduled jobs from the system clock, secret scanning, the dependency updates that are waiting, the test debt that can hide a broken release, a smoke test against staging after every deploy, accessibility checks in CI, and the small fixes from the review. The moment the owner hands over B1, B2, B3 and B6: transactional email, backups with a restore drill, monitoring, Turnstile.

### Stage 2: P0 that needs no credentials (in parallel, target 2026-12-11)

Everything in the launch scope that depends on nobody: one source for the store's policy facts (CMP-12), the rest of the product structured data (SEO-02), the cookie notice and Consent Mode (CMP-03), analytics provisioning (MKT-02), image settings (OPS-09), the out-of-zone message (CHK-13), the damage process and the photo standard as documents (SHP-15, CNT-14), the manager handbook (ORD-05), the pre-release gate (FND-16), and tests for the store behaviour that needs no gateway (variations, stock, coupons, checkout validation, access control).

### Stage 3: payments and tax on staging (starts when B4 and B5 arrive; about one week)

M2. Stripe and PayPal in test mode, wallets, webhooks with duplicate delivery, refunds, card-testing protection (PAY-11), Stripe Tax on test addresses, the PHP 8.5 gate on the checkout path.

### Stage 4: production ready (about two weeks after M2 and the launch catalog)

M3. The real catalog entered from the CSV template and checked, sample data removed from staging and never on production, policies published, the manager account and handbook walkthrough, performance and accessibility budgets measured on staging, Search Console, server access closed down, the full go-live checklist except real money.

### Stage 5: go-live (target window 2027-01-11 to 01-22)

M4. The owner connects live Stripe and PayPal in WP admin; the real-money matrix runs with the owner; coming soon goes off by a change to provisioning step 80; the launch email goes out.

### Stage 6: first weeks after launch (P1, weekly releases)

In this order, because each protects revenue already being earned before adding more:

1. **Safety net:** error monitoring (OPS-11), payment reconciliation (PAY-12), activity log (ORD-11), the doctor command and the weekly maintenance session (FND-18, FND-19), Content-Security-Policy and the card-data scope (OPS-17, CMP-13).
2. **Operations:** the mobile app for the manager (APP-01), order export (ORD-12), packing slips (SHP-08), the order-tracking page (SHP-07), the delay notice (ORD-15), 3PL automation once the 3PL is chosen (SHP-06), tax filing (CMP-08).
3. **Conversion:** attribute filters (NAV-08), reviews (MKT-05, SEO-06), the care and seasoning guide and the first journal articles (CNT-05, CNT-06, SEO-11), the gift option (CHK-07), related products (CAT-12).
4. **Revenue:** email flows (MKT-04), the welcome offer (MKT-03), engraving (CAT-13) once the owner decides where it is done (D-03), gift collections (CAT-14), the cut-off banner (SHP-09).
5. **Options kept open:** Shopify-format export (SHO-02).

### Stage 7: growth (P2, months 2 to 4) and later (P3, P4)

As in `docs/FEATURES.md`: bundles, buy now pay later, gift landing pages, corporate gifting, a Merchant Center test with one product, more email flows, a load test before Father's Day, AI-search readiness. Then the AI assistant with guardrails, the inventory platform, Shopify sync if it becomes a channel.

## 5. What can move the dates

| Risk | Effect | Mitigation |
|---|---|---|
| Stripe or PayPal approval takes weeks, or asks for documents (tobacco-adjacent category, non-US-resident owner) | M2 and everything after slip | Apply now with the description in the launch plan; PayPal can follow Stripe (PAY-03 allows launching on Stripe alone) |
| The launch catalog arrives late or incomplete | M3 slips; sample data cannot go live | The photo and copy standard (CNT-14) and the CSV template exist before the owner starts; `catalog check` tells the manager exactly what is missing |
| WooCommerce 11.3 changes the fulfillment API the shipping email uses | The shipping email breaks on upgrade (seen in the 11.3.0 update PR on 2026-11-12) | Stay on 11.2.x until 11.3.1; adapt and re-test first (`docs/PLAN.md`, WP-02) |
| One VPS hosts staging and production with no off-site backup yet | Loss of the site and the signups | M1b first; a separate staging server later (OPS-19) |
| Ad platforms reject the category | Paid acquisition unavailable | The plan never depended on it: search, content, email and gifting partnerships first |
| Import duty or customs paperwork changes | Margins and stock dates | Owner and broker; no code depends on it beyond the per-product compliance fields |

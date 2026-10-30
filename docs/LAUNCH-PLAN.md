# Cigarholic — Technical Launch Plan

Oct 30, 2026 · @Cuong Tran

## Summary

The P0 store takes about 4 weeks to build, but the go-live date is set by owner items: approved payment accounts, stock received at a US 3PL, cleared import paperwork and a sales-tax registration in the 3PL's state. Build and paperwork run in parallel from week 1, and the coming-soon page with email capture goes live this week.

Two realistic launch paths, depending on where the first stock is today:

- **Path A — stock is already in the US, or can land at a 3PL by about Nov 20:** soft launch in the first week of December and sell into late holiday gifting. Black Friday (Nov 27) itself is too tight to launch on safely.
- **Path B — stock is still in Vietnam:** run the coming-soon page and waitlist through the holidays, launch in January, and make Valentine's Day and Father's Day (June 20, 2027) the first big campaigns.

Five choices shape everything else in this plan:

1. **Payments:** the official Stripe plugin plus PayPal Payments, not WooPayments, so you own the Stripe account, Radar rules and tax data directly.
2. **Code:** a Composer/Bedrock repo, a small custom block theme and one site plugin (`cigarholic-core`); settings are applied by WP-CLI scripts, and every change ships through a PR to staging first.
3. **Data model:** global attributes for the specs customers filter on, numeric fields for dimensions, and a fixed SKU scheme. This is set once, before any product is entered.
4. **Shopify:** keep Shopify-compatible URLs and a Shopify-format CSV export now (cheap insurance). Add one-way sync only if Shopify becomes a real channel, and avoid two-way sync.
5. **Fulfillment:** one 3PL in one state at launch, which keeps sales-tax nexus to a single state while volume is low.

## Timeline

&#91;embedded content: launch timeline · 3 workstreams, 2 launch paths\]

Path A needs stock at the 3PL by about Nov 20. Path B assumes the container leaves Vietnam by Nov 20 and reaches the 3PL in early January: about 23 days door to door to Los Angeles, plus customs and receiving.

## Step 1 — Entity, payments and accounts (you, this week)

Apply for Stripe and PayPal this week through the official **WooCommerce Stripe Payment Gateway** and **WooCommerce PayPal Payments** plugins. Approval is the first thing that can delay launch.

**Why the Stripe plugin rather than WooPayments.** WooPayments creates a Stripe Express account you can't reuse or take with you, and gives you no full Stripe Dashboard ([Woo docs](https://woocommerce.com/document/woopayments/account-management/partnership-with-stripe/)). Its own policy also **prohibits** "Tobacco products" outright, which is stricter than Stripe's ([WooPayments prohibited list](https://woocommerce.com/document/woopayments/our-policies/prohibited-and-restricted-products/)). The Stripe plugin is free, published by Stripe, and uses a normal Stripe account with Radar and Stripe Tax.

**How payment providers see a humidor seller.** Stripe lists "tobacco products, including e-cigarettes, cigars" as *restricted* and drug paraphernalia as *prohibited*. Accessories aren't named ([Stripe restricted businesses](https://stripe.com/legal/restricted-businesses), updated Sep 22, 2026). PayPal requires pre-approval for cigars but doesn't mention accessories ([PayPal AUP](https://www.paypal.com/us/legalhub/paypal/acceptableuse-full)). Use the same plain description everywhere: *"Handcrafted wooden storage boxes and humidors. We do not sell tobacco, cigars or nicotine."*

**Non-US-resident owner.** If you have no SSN or ITIN, Stripe accepts your Vietnamese tax ID plus proof that you aren't a US taxpayer, and asks for a passport for ID checks ([Stripe tax-ID rules](https://support.stripe.com/questions/business-rep-owner-tax-id-requirements-for-us-companies), [verification documents](https://docs.stripe.com/acceptable-verification-documents)). It also wants a real US business address, not a PO box.

| Payment method | US fee today |
| --- | --- |
| Stripe: card, Apple Pay, Google Pay, Link | 2.9% + 30¢ |
| Stripe: international card / currency conversion | +1.5% / +1% |
| Stripe: dispute | $15, plus $15 if you counter (refunded if you win) |
| PayPal Checkout and Venmo | 3.49% + 49¢ |
| PayPal Pay Later | 4.99% + 49¢ |
| PayPal card chargeback | $20 |

Sources: [Stripe pricing](https://stripe.com/pricing), [PayPal fees](https://www.paypal.com/us/business/paypal-business-fees) (updated Oct 1, 2026). On a $299 humidor, Stripe costs $8.97 and PayPal $10.93. Stripe's basic fraud screening (Radar Lite) is free. Paid Radar tiers start billing on Jan 22, 2027, from $10/month ([Radar pricing](https://stripe.com/radar/pricing)), so decide by then whether you need custom rules.

**You do:**

- [ ] Open a Stripe account in the LLC's name with the description above. Use the same legal name, EIN, address and bank account as your LLC records.
- [ ] Open, or convert to, a PayPal Business account under the LLC and EIN.
- [ ] Set up a domain email (e.g. support@) and a US phone number for both applications.
- [ ] Add Claude's deploy secrets to GitHub (the API keys), never in chat.

**Claude does:** installs both plugins through Composer and configures them by script. That covers test keys on staging, live keys on production, webhooks, Apple Pay domain registration for both apex and www in live mode, express buttons, and a test matrix covering card, Apple Pay, Google Pay, PayPal and Venmo.

## Step 2 — Server, WordPress and the agent workflow (weeks 1–2)

Recommended: a DigitalOcean or Vultr VPS with 2 vCPU and 4 GB in a US East region, managed by **SpinupWP**, with Cloudflare Pro in front. Hetzner US is no longer the cheap option: since June 15, 2026 its CPX21 costs $37.49 a month, up from $13.99 ([Hetzner price change](https://docs.hetzner.com/general/infrastructure-and-availability/price-adjustment/)).

| Option | Monthly cost | Fit |
| --- | --- | --- |
| **DigitalOcean 2 vCPU/4 GB + SpinupWP Essentials** | $24 + $12 | Recommended. SpinupWP documents Bedrock support, includes unlimited staging sites and an API, and backs up to Backblaze B2 or S3 |
| Vultr 2 vCPU/4 GB + SpinupWP | $20–24 + $12 | Same setup, slightly cheaper |
| Hostinger KVM 2 (2 vCPU/8 GB) + panel | $8.79 on a 24-month prepay, renews at $14.99 | Cheapest, but needs a 2-year commitment and lists only Boston as a US location |
| RunCloud | $9 Essentials | Atomic deploys and the API come only with Business at $49 |
| Cloudways (managed) | from about $11 (unconfirmed) | Least server work, least control. Bedrock fits inside `public_html/web` |

Sources: [DigitalOcean](https://www.digitalocean.com/pricing/droplets), [Vultr](https://www.vultr.com/pricing/), [SpinupWP](https://spinupwp.com/pricing/), [RunCloud](https://runcloud.io/pricing), [Hostinger VPS](https://www.hostinger.com/vps-hosting). SpinupWP's own Git deploy isn't atomic, so deploys run from GitHub Actions instead (below). Resize to 4 vCPU/8 GB ($48) before big promotions.

**Current baseline:** WordPress 7.1.4, WooCommerce 11.2.1, Bedrock 1.31.4 and DDEV 1.25.4 for local development. New WooCommerce installs default to HPOS, block checkout, Brands and Order Attribution, and include a built-in coming-soon mode.

**Repository layout** (`github.com/cuong-tran/cigarholic`):

```text
composer.json / composer.lock    WordPress core, plugins and theme as dependencies
config/                          Bedrock per-environment config (.env holds secrets, not committed)
web/app/mu-plugins/cigarholic-core/   all custom PHP, one feature per class, named by feature ID
web/app/themes/cigarholic/       block theme: theme.json, templates, parts, patterns
provision/                       idempotent WP-CLI scripts: settings, pages, menus, zones, tax, emails
.ddev/                           one-command local site with seed data
.github/workflows/               CI, deploy-staging, deploy-production
docs/                            ADRs, runbooks, plugins.md, manager-guide.md
AGENTS.md, CLAUDE.md             rules for agents: no page builders, no prod edits, PRs only
```

**Release flow:**

1. Agent or human opens a PR referencing a feature ID (e.g. `feat(CAT-04)`).
2. CI runs PHP lint, PHPCS, PHPStan, `composer validate` and `composer audit`.
3. Merge to `main` deploys to staging over SSH as an atomic release (Deployer, symlink switch), then runs `provision/` and smoke tests.
4. Production deploys only from a tag with manual approval. A backup runs first, and rollback is one command.

**Security baseline (P0):**

- **Cloudflare Pro**, $20/month billed annually ([plans](https://www.cloudflare.com/plans/)). It adds the Cloudflare Managed and OWASP rulesets on top of Free's basic set, and includes APO page caching. WooCommerce works with APO, but Claude will test that cart and checkout sessions bypass the cache.
- **WAF rules:** block `xmlrpc.php`, rate-limit `wp-login.php` and checkout, and put Turnstile (free) on forms.
- **2FA** for every admin and shop-manager account, with the Two Factor plugin from WordPress.org. Also disable the file editor, use SSH keys only, and enable automatic OS security updates.
- **Updates:** WordPress minor releases auto-update. Plugin updates arrive as Composer PRs tested on staging, never as one-click updates on production.
- **Backups:** nightly database and files to Backblaze B2 ($6.95 per TB-month), 30-day retention, an extra snapshot before each deploy, and one restore drill to staging before launch.
- **Monitoring:** UptimeRobot free covers 50 monitors at 5-minute checks, for the home page, checkout and SSL expiry.
- **Transactional email:** Postmark ($15 for 10,000 emails) sent through FluentSMTP, with SPF, DKIM and DMARC on the domain. Postmark's own plugin hasn't been updated since Nov 2024, so FluentSMTP is used instead.
- **Staging:** a second site on the same server at first. It is password-protected and `noindex`, uses payment test mode, and traps outgoing email.

**You do:** create accounts and invite Claude's deploy user: the VPS provider, SpinupWP, Cloudflare (move DNS there), Postmark, Backblaze and UptimeRobot. Then add the tokens as GitHub Environment secrets.

**Claude does:** everything else in this step, including the coming-soon page with email capture in week 1.

## Step 3 — Theme and product data model (weeks 1–3)

Lock the data model and URLs before the manager enters a single product. Both are cheap to set now and expensive to change after Google has indexed the store.

**Theme (D-01):** a small custom block theme, `cigarholic`. Brand tokens live in `theme.json`, page structure lives in templates, and around ten block patterns cover hero, category grid, craftsmanship story, trust bar, product content and gift landing pages. The manager rearranges patterns and edits text; layout changes go through Git.

**URL structure.** I recommend Shopify-compatible paths instead of the nested `/humidors/desktop/<product>` in SEO-03:

- Products: `/products/<slug>/`
- Categories: `/collections/<slug>/`, using flat, unique slugs such as `desktop-humidors`
- Journal: `/journal/<slug>/`

Shopify's `/products` and `/collections` prefixes are fixed and can't be redirected ([Shopify redirects](https://help.shopify.com/en/manual/online-store/menus-and-links/url-redirect)). Matching them now means a future Shopify storefront needs no URL changes. Flat URLs also survive re-categorising a product.

**Product data model:**

| Field | Stored as | Example values | Used for |
| --- | --- | --- | --- |
| Category | Product category | Desktop Humidors, Cabinet Humidors, Travel Humidors, Cigar Boxes, Accessories, Gift Sets | Navigation, URLs |
| Occasion | Custom taxonomy `occasion` | Father's Day, Groomsmen, Corporate Gifts, Holiday, Under $150 | Gift collections (CAT-14) |
| Capacity range | Global attribute | Up to 25, 26–50, 51–100, 100+ | Filter |
| Exterior wood, interior lining | Global attributes | Walnut, mahogany / Spanish cedar | Filter, spec table |
| Humidification, hygrometer | Global attributes | Crystal gel, two-way humidity packs / analog, digital | Filter, spec table |
| Finish, lock, seal | Global attributes | Piano gloss, satin, oil / key lock / magnetic lid | Filter, variations |
| Exact capacity, max cigar length, max ring gauge | Numeric fields | 50 cigars, 7.5 in, 60 RG | Spec table, sorting, "will it fit?" |
| Exterior and interior L × W × H, weight | Numeric fields (in, lb) | 14 × 10 × 5 in, 7.2 lb | Spec table shows inches and cm, lb and kg |
| Package size and weight, SKU, GTIN | Core WooCommerce fields | `CH-DH-050-WAL-SAT` | Shipping, 3PL, feeds |
| Wood species (genus species), country of harvest, composite wood (y/n) | Private fields | *Cedrela odorata*, Vietnam | Lacey, CITES and TSCA paperwork |

Design rules:

- Only attributes that change the SKU (finish, size) become variations. Everything else is a plain spec.
- The spec table (CAT-04) is a block rendered from these fields, so units and wording stay consistent on every product.
- **SKU scheme:** `CH-{type}-{capacity}-{wood}-{finish}`, unique per variation. Engraving is an add-on, not a SKU. Buy GS1 barcodes before listing on Google, Amazon or Shopify.
- Every product page uses the same pattern: summary, spec table, what's in the box, care and seasoning, shipping and returns, and "Handcrafted in Vietnam".

**Engraving (CAT-13, P1).** Build it as a small module in `cigarholic-core`, not a large add-on plugin. It adds a per-product "engravable" switch, a price, a character limit, font and placement choices, and a no-returns notice with extra lead time. Engraving details appear on the order, emails and packing slip. Where engraving happens (D-03) matters more than the code: single parcels from Vietnam now pay full duty and need a formal customs entry (see Step 5), so a US engraving partner or the 3PL is far simpler.

**Claude does:** the theme, patterns, attributes, taxonomies, numeric fields, spec-table block and permalink setup. Claude also builds a CSV import template and a `wp cigarholic catalog:check` command that flags products missing a SKU, weight, dimensions, species or image alt text.

**You and the manager do:** fill the CSV for the launch products and supply photos (several angles, wood grain, joinery, interior, a hand or cigar for scale).

## Step 4 — Checkout, sales tax, shipping and emails (weeks 2–4)

At launch: block checkout with guest checkout, US-only addresses, simple shipping rules, and sales tax collected in the one state where your 3PL holds stock.

**Sales tax: where you must collect.** Inventory held at a 3PL creates physical nexus in that state, even though the 3PL manages it. Elsewhere you owe tax only after crossing a state's economic threshold: $100,000 of sales in most states, and $500,000 in California and Texas. Many states, including Illinois (Jan 1, 2026) and Kentucky (Aug 1, 2026), have dropped the 200-transaction test ([thresholds by state](https://virtuecpas.com/blog/economic-nexus-thresholds-by-state/)). Register in the 3PL's state before the first sale, then monitor the rest. Multi-warehouse 3PL programs create nexus in every state that holds stock, so avoid them in year 1. Confirm all of this with a US sales-tax CPA.

| Tool | Calculates in WooCommerce | Files returns | Price |
| --- | --- | --- | --- |
| [WooCommerce Tax](https://woocommerce.com/products/tax/) | Store-address state only | No | Free |
| [Stripe Tax](https://stripe.com/tax/pricing) (official Woo plugin) | Yes, for every gateway, PayPal included | Only on Tax Complete | $0.50 per order where you're registered. Tax Complete starts at $90/month (1-year term) |
| [TaxJar](https://www.taxjar.com/pricing) | Yes | AutoFile, 4 filings/year included | $99/month (Professional, needed for WooCommerce) |
| [Avalara](https://www.avalara.com/us/en/products/sales-and-use-tax/pricing.html) | Yes (free extension) | Yes | $69 per state per month (Core + SST plan) |
| [Kintsugi](https://trykintsugi.com/pricing) | Yes (plugin) | Yes | Free monitoring, $75 per filing |

**Recommendation (D-07):** Stripe Tax pay-as-you-go through its WooCommerce plugin. One state's return is easy to file yourself or through a CPA. Move to Kintsugi or Stripe Tax Complete when a second state registration is needed. The plugin has few reviews, so Claude will test it against real addresses on staging, with TaxJar as the fallback. Set every product to the general tangible-goods tax code.

**Checkout configuration (all scripted):**

- Block cart and checkout, mini-cart drawer, and guest checkout with optional account.
- US addresses only, with a phone number required for the carrier.
- Terms checkbox, coupons, and Order Attribution on.
- Express buttons for Apple Pay and Google Pay on product, cart and checkout pages.
- A manual-review rule (order on hold) above a set order value, using Stripe's built-in fraud screening.

**Shipping:**

- **Zones:** contiguous US at launch (D-06).
- **Methods:** either free shipping on all humidors with the cost priced in (the D-05 default), or flat rates by shipping class. Oversized cabinet humidors get their own class or a "freight quote" note. Real-time carrier rates wait until flat rates prove unprofitable (SHP-11).
- **Ship-time promise:** the product page and cart say "Ships in 1–2 business days from our US warehouse" only if the 3PL contract guarantees it. Engraved items show their own lead time. The FTC Mail Order Rule requires you to ship within the promised time or notify the customer.
- **Tracking:** comes back from the 3PL connector. Where it doesn't, the free Advanced Shipment Tracking plugin lets the manager paste it in.

**Emails:** branded WooCommerce emails for confirmation, processing, shipped, refunded and cancelled, with logo, colours, support contact and ship-time promise. They are sent through Postmark, and Claude checks inbox placement in Gmail and Outlook before launch.

**You do:** register for sales tax in the 3PL's state before the first sale, and decide D-05 and D-06. **Claude does:** the rest of this step, including a scripted test-order matrix across states.

## Step 5 — Import compliance and tariffs (you, with a customs broker)

Expect about **12.5% total duty** on humidors and cigar boxes. HTS 4420.90.20 ("cigar and cigarette boxes" of wood) is duty-free. On top of that, every product of Vietnam has carried an extra 12.5% Section 301 duty since July 24, 2026 (HTS 9903.05.84) ([CBP guidance](https://content.govdelivery.com/accounts/USDHSCBP/bulletins/421d887), [USTR](https://ustr.gov/about/policy-offices/press-office/press-releases/2026/july/ustr-takes-action-forced-labor-section-301-investigations)). Duty is charged on the price you pay the workshop, not on the retail price.

| Product | Likely HTS | Base duty | Extra duty (Vietnam) | Total |
| --- | --- | --- | --- | --- |
| Desktop or travel humidor, cigar box | 4420.90.20.00 | Free | 12.5% | 12.5% |
| Fallback if CBP rejects "cigar box" (unlined wooden box) | 4420.90.45.00 | 4.3% | 12.5% | 16.8% |
| Floor-standing cabinet humidor | 9403.60.80 | Free | 12.5% | 12.5% |

The classification follows CBP ruling [NY K81097](https://www.customsmobile.com/rulings/docview?doc_id=NY+K81097), which placed a Spanish-cedar humidor in 4420.90.20. Hygrometers and humidifiers sold on their own need their own codes, so ask your broker. Small processing fees add about 0.47%.

**How the rate got here:**

- **Aug 7, 2025:** a 20% IEEPA "reciprocal" tariff took effect on Vietnam.
- **Feb 20, 2026:** the Supreme Court ruled IEEPA doesn't authorise tariffs ([docket](https://www.supremecourt.gov/docket/docketfiles/html/public/24-1287.html)).
- **Feb 24 – Jul 23, 2026:** a temporary 10% Section 122 surcharge applied.
- **Jul 24, 2026:** the current 12.5% Section 301 duty replaced it.

Your belief that a Section 301 duty applies is correct; the rate is 12.5%.

**Not affected:** the Section 232 wood tariffs cover lumber, upholstered furniture, and kitchen cabinets and vanities. They don't reach heading 4420 or a cabinet humidor.

**Watch list for Q4 2026 – Q1 2027:**

- **About Nov 29, 2026:** a USTR decision is due in a separate Section 301 case on Vietnam's IP practices ([USTR notice](https://ustr.gov/sites/default/files/files/Press/Releases/2026/USTR%20301%20Vietnam%20IP%20FRN.pdf)). No tariff has been proposed yet.
- **Unsigned trade agreement:** the US–Vietnam deal isn't signed yet; Vietnam sent its final text on Oct 14, 2026. Signing could lower the rate, but that's not confirmed.
- **Low-value parcels:** de minimis is suspended for all countries, so single parcels from Vietnam pay full duty. Formal entry may be needed ([Federal Register](https://www.federalregister.gov/documents/2026/06/24/2026-12670/indefinite-suspension-of-the-de-minimis-exemption-for-merchandise-arriving-through-all-modes-other)). This is why bulk shipping to a 3PL is the right model.

**Checklist before the first container leaves Vietnam:**

- [ ] **Importer of record and broker:** hire a licensed customs broker and freight forwarder. The LLC is importer of record (EIN, CBP Form 5106). Buy a continuous bond (minimum $50,000 coverage) or a single-entry bond.
- [ ] **Binding ruling (optional):** request a free CBP binding ruling on the humidor classification if you want certainty before scaling.
- [ ] **ISF (10+2):** filed at least 24 hours before loading in Vietnam. Late or missing filings risk $5,000 per violation.
- [ ] **Lacey Act declaration:** genus, species and country of harvest for *every* wood in the product, including veneer and the cedar lining. This applies to both 4420 and 9403. Paper PPQ 505 forms stopped being accepted on Jan 1, 2026; the broker files electronically in ACE ([APHIS](https://direct.aphis.usda.gov/plant-imports/file-lacey-act-declaration)).
- [ ] **CITES:** Spanish cedar and mahogany are listed for logs, sawn wood, veneer and plywood only, so finished humidors need no CITES permit. Keep the workshop's legal-timber records anyway. **Rosewood (*Dalbergia*) is different:** finished goods are covered, with only a 10 kg-per-shipment exemption. Avoid rosewood in bulk orders or budget for CITES permits ([CITES PC26](https://cites.org/sites/default/files/documents/E-PC26-37.pdf)).
- [ ] **TSCA Title VI:** applies only if any part is plywood, MDF or particleboard, such as cabinet backs or drawer bottoms. You need certified panels, a "TSCA Title VI compliant" label with fabricator and date, an importer certification at entry, and 3 years of records ([eCFR 40 CFR 770](https://www.ecfr.gov/current/title-40/chapter-I/subchapter-R/part-770)).
- [ ] **Prop 65 (D-08):** wood dust is listed, but OEHHA says finished wood products need no warning without significant exposure such as sanding. Businesses with fewer than 10 employees are exempt ([OEHHA FAQ](https://www.p65warnings.ca.gov/business-resources/frequently-asked-questions-businesses)). Ask the workshop for finish and hardware chemical data before deciding.
- [ ] **Origin marking:** mark "Made in Vietnam" permanently on each item (a secure label is acceptable) and on the retail box. Unmarked goods pay a 10% marking duty ([19 CFR 134](https://www.ecfr.gov/current/title-19/chapter-I/part-134)). Product pages say "Handcrafted in Vietnam" and never imply Made in USA.
- [ ] **Pallets and crates:** heat-treated and ISPM-15 stamped.

**Transit times:** Ho Chi Minh City to Los Angeles takes about 21–23 days, and to Savannah about 40–42 days ([ONE schedule](https://us.one-line.com/sites/unitedstates/files/2026-06/2026%20TRANSPACIFIC%20SERVICE%20%2822June2026%29.pdf)). Add one to two weeks for customs, trucking and 3PL receiving.

## Step 6 — Fulfillment: choose a 3PL (you, decide by week 3)

Bulk-ship to a single-site US 3PL that handles heavy, fragile goods and connects to WooCommerce. Request quotes from three this week and pick one by week 3 (D-02). The 3PL's state also becomes your first sales-tax state.

| 3PL | Why consider it | WooCommerce link | US sites | Sales-tax nexus |
| --- | --- | --- | --- | --- |
| [Red Stag Fulfillment](https://redstagfulfillment.com/integrations/woocommerce-fulfillment-services/) | Specialises in "big, heavy and bulky" items | API or Dropstream middleware | Knoxville, TN; Salt Lake City, UT | One state if you use one site |
| [ShipBob](https://www.shipbob.com/shipping-fulfillment-plugins/) | Fast onboarding, widely used | One-click app for orders and tracking | NYC area, LA, Chicago, Atlanta, Dallas | Many states if you use its inventory-placement program |
| [ShipMonk](https://shipmonk.com/partners/shopping-carts/woocommerce) | Mid-market, clear minimum formula | Listed integration | NV, TX, PA, KY | Several states if stock is split |
| [Stord (ex-Ware2Go)](https://www.stord.com/newsroom/stord-acquires-ware2go-from-ups) | Distributed network | Via platform | Many | Many states. Likely too large for year 1 |

None of them publish rates; all quote per client.

**What to ask each one:**

- **Damage handling:** damage-rate guarantee, packaging for lacquered wood (double-boxing, corner protection), and whether they will drop-test your retail box.
- **Receiving:** how they receive a container (floor-loaded or palletised), receiving fee per hour or unit, and storage per pallet or cubic foot.
- **Shipping performance:** order cut-off time, ship-time SLA, carrier rates for 5–40 lb parcels, and insurance or signature options for high-value orders.
- **Integration:** whether the WooCommerce connector pushes orders, returns tracking and syncs stock, and whether it's native or through middleware.
- **Returns and kitting:** returns inspection, plus kitting for gift sets and engraving, or a nearby engraving partner.
- **Contract terms:** monthly minimums, onboarding fee, contract length, and whether you can keep stock at one site only.

**At launch:** if the connector isn't ready, the manager can export orders by CSV or the 3PL portal and enter tracking by hand (SHP-05). Full automation is a P1 item (SHP-06). Before going live, send five test orders through the 3PL to friends' addresses in different states.

**Claude does:** a one-page RFQ you can send to the three 3PLs, then connector setup and testing on staging.

## Step 7 — Shopify integration options

Recommendation: do Option 1 now, Option 2 only if Shopify becomes a real sales channel, and skip Option 3. A hard limit makes Shopify less useful than it looks: Shopify Payments prohibits "tobacco and related products" ([Shopify Payments eligibility](https://help.shopify.com/en/manual/payments/shopify-payments/onboarding/eligibility)), so a Shopify store would probably need a third-party gateway.

| Option | What it is | Cost and effort | Risks | When |
| --- | --- | --- | --- | --- |
| **1. Exit ramp** | Shopify-style URLs (Step 3), plus a `wp cigarholic export:shopify` command that writes the catalog in [Shopify's product CSV format](https://help.shopify.com/en/manual/products/import-export/using-csv) | About 1–2 days of Claude work. $0/month | Shopify renames CSV columns from time to time (recently "Handle" became "URL handle"). Old names still import | URLs now (P0), export in P1 |
| **2. One-way sync, WooCommerce → Shopify** | WooCommerce owns products and stock; Shopify receives them and sells as an extra channel | Apps: [Syncio](https://apps.shopify.com/syncio) $19–39/month, [Trunk](https://apps.shopify.com/trunk) from $35/month (stock only), [LitCommerce](https://apps.shopify.com/litcommerce) $29/month. Or a custom module on Shopify's GraphQL Admin API | Overselling if sync lags. Shopify orders still need a path back for fulfillment. Plus the Shopify plan and payments question | Only once Shopify proves to be a channel (P3) |
| **3. Two-way orders and stock** | Both stores sell and update each other | Highest. Custom code or multichannel tools | Race conditions and overselling, duplicate customers, refund and tax mismatches. Two sources of truth undermine WooCommerce as system of record | Avoid. Use an inventory platform as the hub instead |
| **4. Full migration (if ever)** | Move the store to Shopify | [Cart2Cart](https://www.shopping-cart-migration.com/cart2cart-woocommerce-to-shopify-migration-app) or [LitExtension](https://litextension.com/pricing.html) from $29 one-off; [Matrixify](https://apps.shopify.com/excel-export-import) $20–50/month | Low if Option 1 is in place | Only if WooCommerce stops working for you |

**Architecture rules (SHO-01):**

- Any sync lives in its own module in `cigarholic-core`, behind a switch, and the site runs normally with it off.
- New Shopify integrations must use the GraphQL Admin API. REST has been legacy since Oct 1, 2024, and new public apps must use GraphQL since Apr 1, 2025 ([Shopify REST status](https://shopify.dev/docs/api/admin-rest)).
- If you add several channels later (Shopify, Amazon, Etsy), sync each one to a single inventory platform (INV-04) rather than connecting stores to each other.

## Step 8 — Marketing: SEO, content and email first

Treat paid ads as high-risk. No platform names humidors, but each bans tobacco "related products", "paraphernalia" or anything that "facilitates" tobacco use. Grow through search, content and email, and test ads only once the store has sales.

| Channel | Wording that decides it | Risk for humidors |
| --- | --- | --- |
| [Google Ads](https://support.google.com/adspolicy/answer/16489929) | Bans "products and services that directly facilitate or promote tobacco consumption" (examples: rolling papers, pipes, filters, cigar bars) | High. Google warns at least 7 days before suspending an account |
| [Google Merchant Center / free listings](https://support.google.com/merchants/answer/6150004?hl=en) | Same wording, applied to "ads or listings" | High. Test one product before submitting a full feed (SEO-09) |
| [Meta ads](https://transparency.meta.com/policies/ad-standards/dangerous-content/tobacco) | "Tobacco or nicotine products and related paraphernalia" | High |
| [Pinterest ads](https://policy.pinterest.com/en/advertising-guidelines) | "Cigars, or cigar bars… or related products" | Very high |
| [TikTok ads](https://ads.tiktok.com/help/article/tiktok-ads-policy-dangerous-products-or-services?lang=en) | "Cigarettes, cigars, tobacco or nicotine, and related products" | Very high |
| [Klaviyo](https://www.klaviyo.com/legal/acceptable-use-policy) and [Omnisend](https://www.omnisend.com/legal/acceptable-use-policy/) email | No tobacco rule for email. SMS bans tobacco ("SHAFT") content | Email fine. SMS unclear, so leave it for later |

**Positioning:** lead with what the product is: handcrafted solid-wood boxes, Spanish-cedar lining and gift-worthy finishes. When you test an ad, describe the product honestly and accept a rejection. Don't try to get past review with disguised wording: "circumventing systems" violations get whole accounts banned, Merchant Center included.

**What to build instead:**

- **SEO (P0–P2):**
  - Clean product schema with shipping and return details, Search Console from day 1, and the spec data from Step 3.
  - Topic hubs on how to choose a humidor (capacity, size, wood), seasoning and care, humidity control, wood species, and gift guides.
- **Content (CNT-06):** 3–5 articles published before launch. The first should be "How to season a new humidor", the most-asked customer question in this category.
- **Email (D-04):**
  - Collect signups from week 1 with the coming-soon page and a launch offer.
  - Start [Klaviyo](https://www.klaviyo.com/pricing) free (250 profiles), then $20/month up to 500.
  - [Omnisend](https://www.omnisend.com/pricing/) is a cheaper alternative at about $20/month for 1,000 contacts.
  - Flows in P1: welcome, abandoned cart, post-purchase care tips, review request.
- **Gifting:** a corporate-gifts page with a quote form, groomsmen bundles and Father's Day (June 20, 2027) landing pages. Gift and wedding blogs and cigar-lounge partnerships replace paid reach.
- **Organic social:** wood-detail photos and workshop process videos on Instagram, Pinterest and YouTube. Organic posts follow separate community rules from ads, so keep them about the craft.

## After launch: P1–P3 roadmap

Ship small releases every week after launch, in the priority order set in FEATURES.md. Work your way back from the calendar: Valentine's Day (Feb 14, 2027) is the first gifting date and Father's Day (Jun 20, 2027) the biggest.

| Phase | Timing | Focus | Main feature IDs |
| --- | --- | --- | --- |
| Phase 2 (P1) | Launch + 2–6 weeks | Safety net: end-to-end tests, dependency PRs, error monitoring | FND-12, FND-13, OPS-10, OPS-11, OPS-12 |
|  |  | Conversion: attribute filters, gift option, trust bar, care guide, reviews | NAV-08, CHK-07, CNT-05, CNT-07, MKT-05 |
|  |  | Revenue: engraving, welcome popup, core email flows | CAT-13, MKT-03, MKT-04 |
|  |  | Operations: 3PL automation, tracking page, packing slips, tax filing, official WooCommerce mobile app | SHP-06–SHP-09, CMP-08, APP-01 |
| Phase 3 (P2) | Months 2–4 | Gift sets and bundles, buy-now-pay-later, gift landing pages, corporate gifting, Merchant Center test, more flows, load test before Father's Day | CAT-15, PAY-08, NAV-12, CNT-10, SEO-09, MKT-06, OPS-14 |
| Phase 4 (P3) | Month 4+ | AI drafting in the helpdesk, then an on-site assistant with guardrails; inventory platform and integration layer; Shopify sync if justified; custom order app only for gaps | AI-03, AI-05–AI-07, INV-02–INV-04, SHO-02, SHO-03, APP-04 |

**Proposed edits to FEATURES.md:**

- **SEO-03:** change to `/products/<slug>` and `/collections/<slug>`, for Shopify compatibility.
- **SHO-02:** move the Shopify CSV export from P3 to P1. It's cheap and keeps your exit option real.
- **CMP-11 (new, P0):** add import compliance data per product: species, harvest country, composite-wood flag and HTS code.
- **D-03:** add a note that per-parcel shipping from Vietnam now pays full duty because de minimis is suspended.
- **MKT-09 and SEO-09:** add an ad-account test plan; expect rejections.
- **PAY-03:** note that PayPal Pay Later costs 4.99% + 49¢, which matters for high-ticket items.

Claude can make these edits in the repo as a PR.

## Plugin stack and running cost

The launch store needs 13–14 plugins, within the 15-plugin budget (FND-11). Fixed running cost is about **$72–101 per month**, plus per-order payment and tax fees.

| Plugin | Job | Cost |
| --- | --- | --- |
| WooCommerce | Store core | Free |
| WooCommerce Stripe Payment Gateway | Cards, Apple Pay, Google Pay, Link | Free (Stripe fees) |
| WooCommerce PayPal Payments | PayPal, Venmo, Pay Later | Free (PayPal fees) |
| Stripe Tax for WooCommerce | Sales-tax calculation | $0.50 per taxed order |
| SEOPress (or Yoast, or Rank Math; one only, D-09) | Meta, sitemaps, schema, redirects | Free; PRO $49/year |
| Two Factor | 2FA for admins and managers | Free |
| Redis Object Cache | Speed | Free |
| FluentSMTP | Sends mail through Postmark | Free |
| Cloudflare | APO cache purge | Free |
| Complianz | Cookie notice, Consent Mode. Global Privacy Control needs Premium | Free; Premium $59/year once ad pixels go in |
| Google Analytics for WooCommerce | GA4 e-commerce events | Free |
| Klaviyo (or your D-04 choice) | Signup forms, checkout opt-in, flows | Free plugin |
| 3PL connector | Orders out; tracking and stock back | Set by the 3PL |
| Advanced Shipment Tracking | Only if the 3PL connector doesn't return tracking | Free |

The contact form with Turnstile, spec table, announcement bar, engraving and order-received page are written in `cigarholic-core`, not added as plugins. That keeps the plugin list short and puts the code where agents can read it. Plugin versions and maintainers checked on Oct 30, 2026 via WordPress.org.

| Monthly fixed cost | Choice | USD/month |
| --- | --- | --- |
| Server | DigitalOcean 2 vCPU/4 GB | 24 |
| Server panel | SpinupWP Essentials | 12 |
| CDN and WAF | Cloudflare Pro, billed annually (APO included) | 20 |
| Transactional email | Postmark, 10,000 emails | 15 |
| Off-site backups | Backblaze B2, about 50 GB | under 1 |
| Uptime monitoring | UptimeRobot free | 0 |
| Email marketing | Klaviyo, free up to 250 profiles | 0–20 |
| SEO and consent add-ons | SEOPress PRO, Complianz Premium | about 9 |
| **Total** |  | **about 72–101** |

**Variable costs per order:**

- **Payments:** Stripe 2.9% + 30¢, or PayPal 3.49% + 49¢.
- **Sales tax:** Stripe Tax $0.50 in states where you're registered.
- **Fulfillment:** 3PL pick, pack and postage, by quote.
- **Import duty:** about 12.5% of the workshop price, plus freight. Duty is paid per container, not per order.

## Who does what

You own every account, contract, legal filing and money decision. Claude builds and configures everything that lives in the repo or the server. The store manager runs day-to-day operations from WP admin.

| Workstream | You (owner) | Claude | Store manager |
| --- | --- | --- | --- |
| Payments | Apply to Stripe and PayPal; answer their reviews | Plugins, webhooks, wallets, test matrix | Refunds, disputes evidence |
| Hosting and security | Create accounts, pay, add secrets to GitHub | Server, Cloudflare, backups, monitoring, 2FA, deploys | Uses 2FA |
| Code, theme, data model | Approve PRs on staging | Repo, CI/CD, theme, patterns, attributes, spec table, provisioning | — |
| Catalog and content | Brand assets, prices, policies sign-off | CSV template, catalog checker, policy page drafts | Product entry, photos, copy, pages |
| Import compliance | Broker, bond, ISF, Lacey data, workshop documents, Prop 65 call | Per-product compliance fields, document checklist | — |
| 3PL | Quotes, contract, first shipment | RFQ draft, connector setup and tests | Daily order checks, tracking |
| Sales tax | Registration, filing (or CPA) | Stripe Tax setup and tests | — |
| Marketing | Offer, budget, partnerships | Email integration, GA4, SEO setup, landing patterns | Articles, newsletters, social |
| After launch | Prioritise the backlog | Weekly releases, updates, fixes, handbook | Follows the handbook |

The handbook (ORD-05) is how the manager learns the store. Claude writes it with screenshots from staging. The manager must complete a product edit, an order, a refund and a tracking update on their own before launch.

## What I still need from you

The first three answers decide the launch date. The rest can come during weeks 1–2.

| # | Question | What it decides |
| --- | --- | --- |
| 1 | Where is the first stock today, and how many units and SKUs? Has it shipped, and is it ocean or air? | Path A (December) or Path B (January) |
| 2 | Which state is the LLC registered in, and what US street address does it use? Is it a registered-agent or virtual address? | Stripe and PayPal approval, sales-tax registration, invoices |
| 3 | Do you already have a 3PL, customs broker or freight forwarder in mind? | Steps 5 and 6 timing, first tax state |
| 4 | Which woods and materials are used? Any rosewood (*Dalbergia*), plywood, MDF, or lacquer you have data for? | CITES permits, TSCA labels, Prop 65 decision |
| 5 | Is the workshop yours or a supplier's? How many employees does the US LLC have? | Lacey and timber documents; the Prop 65 under-10-employees exemption |
| 6 | Where would engraving be done, at what price and lead time? | Whether CAT-13 ships in P1 and how it's fulfilled |
| 7 | Price range per product, and free shipping (priced in) or a threshold? | Shipping rules (D-05), margin check with 12.5% duty |
| 8 | Ship to Alaska, Hawaii, territories and APO/FPO? | Shipping zones (D-06) |
| 9 | Do you have the domain, logo, colours and fonts? Is product photography done or scheduled? | Theme start (week 1) and catalog entry (week 3) |
| 10 | Who is the store manager? Are they in the US or Vietnam, and which language should the handbook use? | Handbook, admin language, support hours |
| 11 | Email platform preference: Klaviyo, Omnisend or MailPoet? | Coming-soon signup form in week 1 (D-04) |
| 12 | Can Claude have collaborator access to `cuong-tran/cigarholic` and a staging server this week? | Start of the build |

The workshop's input matters most for questions 4–6. A one-page material and finish sheet per product answers most of them.

## Sources

All checked on Oct 30, 2026. Trade rules, fees and prices change often, so recheck before signing contracts or shipping.

**Tariffs and customs**

- [CBP CSMS guidance on Section 301 duty for Vietnam](https://content.govdelivery.com/accounts/USDHSCBP/bulletins/421d887)
- [USTR: Section 301 forced-labor action](https://ustr.gov/about/policy-offices/press-office/press-releases/2026/july/ustr-takes-action-forced-labor-section-301-investigations)
- [USTR: Vietnam IP Section 301 notice](https://ustr.gov/sites/default/files/files/Press/Releases/2026/USTR%20301%20Vietnam%20IP%20FRN.pdf)
- [Supreme Court docket 24-1287](https://www.supremecourt.gov/docket/docketfiles/html/public/24-1287.html)
- [CBP ruling NY K81097 (humidor classification)](https://www.customsmobile.com/rulings/docview?doc_id=NY+K81097)
- [Section 232 wood proclamation](https://www.govinfo.gov/content/pkg/DCPD-202500966/html/DCPD-202500966.htm)
- [De minimis suspension, Federal Register](https://www.federalregister.gov/documents/2026/06/24/2026-12670/indefinite-suspension-of-the-de-minimis-exemption-for-merchandise-arriving-through-all-modes-other)
- [CBP continuous bond directive](https://www.cbp.gov/sites/default/files/documents/3510-004_3.pdf)
- [ONE transpacific schedule](https://us.one-line.com/sites/unitedstates/files/2026-06/2026%20TRANSPACIFIC%20SERVICE%20%2822June2026%29.pdf)

**Product regulations**

- [APHIS Lacey Act declaration](https://direct.aphis.usda.gov/plant-imports/file-lacey-act-declaration)
- [TSCA Title VI, 40 CFR 770](https://www.ecfr.gov/current/title-40/chapter-I/subchapter-R/part-770)
- [CITES PC26 Dalbergia document](https://cites.org/sites/default/files/documents/E-PC26-37.pdf)
- [OEHHA wood dust](https://oehha.ca.gov/proposition-65/chemicals/wood-dust)
- [Prop 65 business FAQ](https://www.p65warnings.ca.gov/business-resources/frequently-asked-questions-businesses)
- [19 CFR 134 origin marking](https://www.ecfr.gov/current/title-19/chapter-I/part-134)
- [APHIS wood packaging](https://www.aphis.usda.gov/plant-imports/wood-packaging-material/import)

**Payments**

- [Stripe pricing](https://stripe.com/pricing)
- [Stripe restricted businesses](https://stripe.com/legal/restricted-businesses)
- [Stripe rep tax-ID requirements](https://support.stripe.com/questions/business-rep-owner-tax-id-requirements-for-us-companies)
- [Stripe Radar pricing](https://stripe.com/radar/pricing)
- [WooPayments and Stripe](https://woocommerce.com/document/woopayments/account-management/partnership-with-stripe/)
- [WooPayments prohibited products](https://woocommerce.com/document/woopayments/our-policies/prohibited-and-restricted-products/)
- [PayPal fees](https://www.paypal.com/us/business/paypal-business-fees)
- [PayPal acceptable use](https://www.paypal.com/us/legalhub/paypal/acceptableuse-full)
- [Stripe Apple Pay domain registration](https://docs.stripe.com/payments/payment-methods/pmd-registration)

**Sales tax**

- [Stripe Tax pricing](https://stripe.com/tax/pricing)
- [Stripe Tax for WooCommerce](https://docs.stripe.com/use-stripe-apps/woocommerce)
- [TaxJar pricing](https://www.taxjar.com/pricing)
- [Avalara pricing](https://www.avalara.com/us/en/products/sales-and-use-tax/pricing.html)
- [Kintsugi pricing](https://trykintsugi.com/pricing)
- [Economic nexus thresholds by state](https://virtuecpas.com/blog/economic-nexus-thresholds-by-state/)

**Channels and Shopify**

- [Google Ads tobacco policy](https://support.google.com/adspolicy/answer/16489929)
- [Merchant Center dangerous products](https://support.google.com/merchants/answer/6150004?hl=en)
- [Meta tobacco ad standard](https://transparency.meta.com/policies/ad-standards/dangerous-content/tobacco)
- [Pinterest ad guidelines](https://policy.pinterest.com/en/advertising-guidelines)
- [TikTok dangerous products](https://ads.tiktok.com/help/article/tiktok-ads-policy-dangerous-products-or-services?lang=en)
- [Klaviyo acceptable use](https://www.klaviyo.com/legal/acceptable-use-policy)
- [Shopify Payments eligibility](https://help.shopify.com/en/manual/payments/shopify-payments/onboarding/eligibility)
- [Shopify product CSV](https://help.shopify.com/en/manual/products/import-export/using-csv)
- [Shopify URL redirects](https://help.shopify.com/en/manual/online-store/menus-and-links/url-redirect)
- [Shopify REST Admin API status](https://shopify.dev/docs/api/admin-rest)

**Hosting and tools**

- [Hetzner price adjustment](https://docs.hetzner.com/general/infrastructure-and-availability/price-adjustment/)
- [DigitalOcean droplets](https://www.digitalocean.com/pricing/droplets)
- [SpinupWP pricing](https://spinupwp.com/pricing/)
- [Cloudflare plans](https://www.cloudflare.com/plans/)
- [Postmark pricing](https://postmarkapp.com/pricing)
- [Backblaze B2 pricing](https://www.backblaze.com/cloud-storage/pricing)
- [UptimeRobot pricing](https://uptimerobot.com/pricing/)
- [WooCommerce releases](https://developer.woocommerce.com/releases/)
- [Roots Bedrock releases](https://github.com/roots/bedrock/releases)

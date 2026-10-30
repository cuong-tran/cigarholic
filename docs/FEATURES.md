# Cigarholic — Feature List & Roadmap

> Online store for wooden cigar accessories (humidors, cigar boxes and related accessories), handcrafted in Vietnam, sold to US consumers.
> **No cigars, tobacco or nicotine products are sold.**

| | |
|---|---|
| Platform | WordPress + WooCommerce, self-hosted on a US-region VPS (Cloudflare in front) |
| Market | United States, B2C, USD only |
| Payments | Stripe (cards, Apple Pay, Google Pay, Link) and PayPal (PayPal, Venmo, Pay Later) |
| Operators | Non-technical **site manager** runs products, prices, orders and content in WP admin. **AI coding agents** (e.g. Claude Code) do most development and maintenance. |
| Independence | WooCommerce is the system of record. Any Shopify sync is an optional, removable add-on. |
| Last updated | 2026-10-30 |

---

## 1. How to use this document

**For humans:** Section 3 is the launch scope (everything marked **P0**). Section 4 is the full feature list by area. Section 5 lists non-code launch prerequisites and section 6 the decisions the owner still needs to make.

**For AI agents:**

- Every feature has a **stable ID** (e.g. `CAT-04`). Reference it in branch names, commit messages, issues and PRs (`feat(CAT-04): spec table from attributes`).
- **Never renumber or reuse IDs.** Add new features at the end of their section with the next number. Mark dropped features ~~struck through~~ with a reason rather than deleting them.
- Work in priority order (P0 first). Don't start P1 work while a P0 item is open, unless it's needed to unblock a P0.
- Progress is tracked in `docs/STATUS.md` during the build (GitHub Issues are used only for bugs and owner decisions), not in this file. This file changes only when scope or priority changes.
- The **How** column says how a feature should be built. Prefer, in order: **Core** → **Config** → **Plugin** → **Custom**. A new plugin must be justified in the PR (see FND-11).

### How column legend

| Code | Meaning |
|---|---|
| Core | Built into WordPress/WooCommerce; enable and configure only |
| Config | Settings applied as code (WP-CLI provisioning scripts), not clicked by hand |
| Plugin | Third-party plugin installed through Composer (candidates are suggestions, not decisions) |
| Custom | Custom code in the site plugin (`cigarholic-core`) or the theme |
| Content | Created and maintained by the site manager in WP admin |
| Service | External SaaS (email, tax, monitoring, helpdesk…) |
| Ops | Server, DNS, process or business task, not code |

---

## 2. Priorities and phases

| Priority | Meaning | Phase | Indicative timing |
|---|---|---|---|
| **P0** | **Launch scope.** Needed to take real orders safely and legally, or much cheaper to do before launch than after (URLs, SKUs, repo setup). | Phase 0 – Foundation, Phase 1 – MVP launch | Build ≈ 3–4 weeks; go-live date is usually set by non-code prerequisites (section 5) |
| **P1** | **Fast follow.** Improves conversion and operations right after launch. | Phase 2 | Launch + 2–6 weeks |
| **P2** | **Growth.** Marketing, merchandising and scale features. | Phase 3 | Months 2–4 |
| **P3** | **Strategic / later.** AI customer service, custom mobile app, inventory platform API, Shopify sync. | Phase 4 | Month 4+ |
| **P4** | **Backlog.** Ideas to revisit when data justifies them. | — | Unscheduled |

**Guiding rule:** go live with P0 only, then ship small, frequent releases. A simple store that takes real orders beats a complete store that isn't live.

**Timing note (today is 2026-10-30):** Black Friday is 2026-11-27 and Cyber Monday 2026-11-30. If stock is already in the US and payment accounts get approved quickly, a P0-only launch before the holiday peak is possible but tight. Either way, put up the **Coming-soon page with email capture (CNT-04)** in week 1 so the domain starts collecting subscribers and search history.

---

## 3. MVP launch scope (P0 summary)

A customer in the US can find a humidor, read its full specs, pay by card / Apple Pay / Google Pay / PayPal, get correct sales tax and shipping, receive branded emails and tracking. The manager can run all of that from WP admin, and agents can change the site safely through Git.

1. **Foundation:** Composer-managed repo, local dev, staging, CI, automated deploy, config as code, agent instructions (FND).
2. **Hosting & security:** VPS, Cloudflare, SSL, 2FA, backups, monitoring, transactional email (OPS).
3. **Catalog:** simple and variable products, categories, spec attributes, spec table, gallery, origin labelling (CAT).
4. **Storefront:** header, footer, homepage v1, category pages, search, announcement bar (NAV).
5. **Checkout & payments:** block cart/checkout, guest checkout, coupons, Stripe + wallets, PayPal, refunds (CHK, PAY).
6. **Shipping:** US zones, flat/free rules, ship-time messaging, tracking, hand-off to 3PL (SHP).
7. **Operations:** order handling, branded emails, Shop Manager role, manager handbook (ORD).
8. **Content, SEO & compliance:** core pages and policies, SEO basics, sales tax, accessibility, privacy (CNT, SEO, CMP).

P0 has 84 items, but it's lighter than it looks: 35 are built-in WordPress/WooCommerce settings (Core/Config), 21 are content, ops or SaaS setup, 8 are plugin installs, and only 20 need custom code.

### Go-live checklist (all must pass on production)

- [ ] Real-money orders completed and refunded with: card, Apple Pay (iPhone Safari), Google Pay (Android Chrome), PayPal.
- [ ] Sales tax is correct for test addresses in every state where the business has nexus.
- [ ] Order, shipped and refund emails land in Gmail and Outlook inboxes, not spam (SPF/DKIM/DMARC pass).
- [ ] Full mobile purchase flow tested on iOS and Android.
- [ ] Core Web Vitals "good" on mobile for home, category and product pages (LCP < 2.5 s, INP < 200 ms, CLS < 0.1).
- [ ] A backup has been restored successfully to staging.
- [ ] Policies are published and linked in the footer and at checkout.
- [ ] Staging is `noindex` and password-protected; production is indexable; sitemap submitted in Search Console.
- [ ] Uptime and SSL monitoring alert the owner.
- [ ] The manager has completed a product edit, an order, a refund and a tracking update alone, using the handbook.

---

## 4. Feature list

### 4.1 Foundation, codebase & agent workflow (FND)

| ID | Feature | Pri | How | Notes |
|---|---|---|---|---|
| FND-01 | Composer-managed WordPress repo (Bedrock-style layout): core, plugins and theme as dependencies; uploads, DB dumps and secrets never in Git | P0 | Custom | Plugins from wpackagist / vendor Composer repos; lock file committed |
| FND-02 | One-command local environment (DDEV or wp-env) with seed data (sample products, pages, menus) | P0 | Custom | `make up` / `make seed`; agents must be able to reproduce the site from scratch |
| FND-03 | Config as code: idempotent WP-CLI provisioning scripts for settings, pages, menus, permalinks, shipping zones, tax, emails | P0 | Config | Re-runnable on every deploy; manual admin clicks for settings are discouraged |
| FND-04 | Agent instructions: `AGENTS.md` / `CLAUDE.md` with architecture, commands, coding standards, do/don't rules, plus `docs/` (ADRs, runbooks) | P0 | Custom | Rules include: no page builders, no direct edits on production, every change through a PR |
| FND-05 | Single site plugin `cigarholic-core` (mu-plugin) for all custom PHP, plus a block theme (custom or child) for presentation | P0 | Custom | Small, namespaced, one feature per file/class, referencing feature IDs |
| FND-06 | CI on every PR: PHP lint, PHPCS (WordPress Coding Standards), PHPStan, `composer validate` + `composer audit`, JS/CSS lint | P0 | Custom | GitHub Actions; PR can't merge if red |
| FND-07 | Staging environment mirroring production: basic-auth, `noindex`, Stripe/PayPal test mode, outgoing email trapped | P0 | Ops | Same server stack and PHP version as production |
| FND-08 | Automated deploy: merge to `main` → staging; manual approval or tag → production; atomic releases, one-click rollback, provisioning runs after deploy | P0 | Custom | GitHub Actions over SSH (e.g. Deployer or rsync + symlink switch) |
| FND-09 | Secrets management: `.env` per environment, GitHub Environment secrets; API keys never committed | P0 | Ops | Includes Stripe, PayPal, tax, email provider keys |
| FND-10 | Issue & PR templates that require a feature ID, test notes and screenshots; Conventional Commits; `CHANGELOG.md` | P0 | Custom | Keeps agent work reviewable |
| FND-11 | Plugin budget and register: `docs/plugins.md` lists every plugin, why it's there and who owns it; target ≤ 15 active plugins | P0 | Custom | New plugin needs an ADR line in the PR |
| FND-12 | End-to-end smoke tests (Playwright): browse → add to cart → checkout with Stripe test card → order email; run on staging after every deploy | P0 | Custom | Highest-value test suite for a store. Raised from P1 (2026-10-30): the suite starts in Phase 0 — a smoke subset runs in CI against the local runtime, the full suite against staging after every deploy |
| FND-13 | Automated dependency update PRs (Renovate or Dependabot) gated by CI + E2E | P1 | Service | Weekly batch; security fixes immediately |
| FND-14 | Production → staging DB refresh with customer data anonymised | P2 | Custom | Lets agents debug with realistic data safely |
| FND-15 | Visual regression tests for key templates (home, category, product, cart, checkout) | P3 | Custom | |

### 4.2 Hosting, security & performance (OPS)

| ID | Feature | Pri | How | Notes |
|---|---|---|---|---|
| OPS-01 | US-region VPS: Nginx, PHP 8.x, MariaDB/MySQL, Redis/Valkey | P0 | Ops | Raw VPS provisioned by idempotent scripts in `infra/` run from GitHub Actions; no server panel (SpinupWP-class panel only if the owner asks). Size for spikes (holiday promos) |
| OPS-02 | Domain, DNS on Cloudflare, SSL, HTTPS-only, single canonical host (www or apex) | P0 | Ops | |
| OPS-03 | Cloudflare CDN + WAF: bot protection, rate limit on `wp-login.php` and checkout, block `xmlrpc.php` | P0 | Config | |
| OPS-04 | Transactional email through a provider (Postmark, Amazon SES, Mailgun…) with SPF, DKIM, DMARC | P0 | Service | VPS mail alone lands in spam; order emails are critical |
| OPS-05 | Backups: daily DB + files, stored off-server, 30-day retention, restore runbook tested | P0 | Ops | Extra on-demand backup before every production deploy |
| OPS-06 | Uptime, checkout-endpoint and SSL-expiry monitoring with alerts to owner | P0 | Service | |
| OPS-07 | Admin hardening: 2FA for every admin and shop-manager account, strong passwords, login rate limiting, file editor disabled, least-privilege roles | P0 | Config | |
| OPS-08 | Update policy: WordPress security releases auto-applied; plugins/theme updated through Composer PRs; monthly maintenance window | P0 | Ops | |
| OPS-09 | Caching & media: full-page cache (bypassed for cart/checkout/account), Redis object cache, WebP/AVIF images, responsive sizes, lazy loading | P0 | Config | Product photos are the heaviest asset |
| OPS-10 | Core Web Vitals budget enforced (Lighthouse CI on PRs, field data from Search Console) | P1 | Custom | |
| OPS-11 | Error monitoring: PHP/JS error aggregation (e.g. Sentry) + WooCommerce log review; alerts on payment/webhook failures | P1 | Service | |
| OPS-12 | Malware scanning and file-integrity monitoring | P1 | Service | |
| OPS-13 | Disaster-recovery runbook: rebuild on a new VPS from Git + backups in < 4 h, rehearsed | P2 | Ops | |
| OPS-14 | Load test before major promotions (Black Friday, Father's Day) | P2 | Ops | |

### 4.3 Catalog & product pages (CAT)

| ID | Feature | Pri | How | Notes |
|---|---|---|---|---|
| CAT-01 | Simple and variable products (variations by wood, finish or size, each with own SKU, price, stock and image) | P0 | Core | |
| CAT-02 | Category tree: Humidors (Desktop, Cabinet, Travel), Cigar Boxes, Humidor Accessories (hygrometers, humidifiers, seasoning kits), Gift Sets | P0 | Core | Manager can add/rename; slugs fixed before launch (SEO-03) |
| CAT-03 | Global spec attributes: cigar capacity, exterior wood, interior lining, exterior & interior dimensions, weight, humidification system, hygrometer type, finish, lock, seal type | P0 | Core | Same attribute names on every product, so filters (NAV-08) work later |
| CAT-04 | Spec table on product page, generated from attributes, consistent units (inches + cm, lb + kg) | P0 | Custom | Reference site lists closed/open dimensions and fit (ring gauge, length) — do the same, consistently |
| CAT-05 | Product gallery: multiple high-res images, zoom, lightbox, swipe on mobile, image switches with variation | P0 | Core | Wood grain and joinery close-ups sell the product |
| CAT-06 | Product page content structure: benefit summary, description, "What's in the box", care & seasoning, shipping & returns summary, "Handcrafted in Vietnam" | P0 | Content | Block pattern so every product looks the same |
| CAT-07 | Regular and sale price, scheduled sales, "% off" sale badge | P0 | Core | |
| CAT-08 | Stock status display; per-product backorders with lead-time message | P0 | Core | |
| CAT-09 | Product data for operations: unique SKU, GTIN/UPC, weight and package dimensions on every product/variation | P0 | Core | Needed for shipping, feeds and inventory sync (INV-01) |
| CAT-10 | Bulk product import/export via CSV | P0 | Core | Lets the manager edit prices in bulk |
| CAT-11 | Product video in gallery (hosted on YouTube/Vimeo or optimised MP4) | P1 | Core | |
| CAT-12 | Related products, upsells (better humidor) and cross-sells (hygrometer, humidifier) | P1 | Core | |
| CAT-13 | **Laser-engraving personalisation:** paid add-on with text fields (character limits), font and placement options, "personalised items can't be returned" notice, extra lead time; details shown on order, emails and packing slip | P1 | Custom | Depends on where engraving happens (decision D-03). Small custom feature is more agent-friendly than a large add-on plugin |
| CAT-14 | Gift collections: Father's Day, Groomsmen, Corporate Gifts, Holiday, Gifts under $X | P1 | Content | Curated categories/tags; richer landing pages are NAV-12 |
| CAT-15 | Bundles / gift sets with bundle price (humidor + hygrometer + humidifier + seasoning kit) | P2 | Plugin | |
| CAT-16 | Capacity & size guide ("how many cigars fit?", by ring gauge and length) | P2 | Content | |
| CAT-17 | Pre-orders for incoming stock with expected ship date | P2 | Plugin | Pairs with INV-05 |
| CAT-18 | Back-in-stock email notification | P2 | Plugin | |
| CAT-19 | Low-stock badge ("Only 3 left") driven by real stock | P2 | Custom | Must be truthful (FTC) |
| CAT-20 | Product comparison (side-by-side specs) | P3 | Custom | Uses CAT-03 attributes |
| CAT-21 | Wishlist | P3 | Plugin | |
| CAT-22 | Recently viewed products | P3 | Core | |
| CAT-23 | Product Q&A (questions answered by staff, later by AI) | P3 | Plugin | Feeds AI-01 knowledge base |
| CAT-24 | Live visual preview of the engraving on the product image | P3 | Custom | |
| CAT-25 | 360° spin / AR view | P4 | Plugin | |

### 4.4 Navigation, search & merchandising (NAV)

| ID | Feature | Pri | How | Notes |
|---|---|---|---|---|
| NAV-01 | Responsive header: logo, main menu, search, account, cart with item count; mobile menu | P0 | Custom | Built with theme blocks, editable menu |
| NAV-02 | Announcement bar editable by the manager (promo, shipping message, holiday cut-off), with start/end dates | P0 | Custom | Reference site uses it for sales |
| NAV-03 | Footer: policies, contact, help center, social links (Instagram, Facebook, YouTube, TikTok, Pinterest), newsletter signup, payment icons, company & US warehouse info | P0 | Custom | |
| NAV-04 | Homepage v1: hero, shop by category, best sellers, craftsmanship story, "why buy from us" (handcrafted, US shipping, returns), newsletter | P0 | Content | Built from block patterns the manager can rearrange |
| NAV-05 | Shop and category pages with sorting (price, popularity, newest) and pagination | P0 | Core | |
| NAV-06 | Breadcrumbs | P0 | Core | |
| NAV-07 | Product search (basic) | P0 | Core | |
| NAV-08 | Filters by attribute (capacity, wood, humidification type), price and availability | P1 | Core | Product filter blocks; relies on CAT-03 |
| NAV-09 | Custom 404 page with search and best sellers | P1 | Custom | |
| NAV-10 | Campaign / landing page block patterns (gift guides, holiday sale) the manager can reuse | P1 | Custom | |
| NAV-11 | Mega menu with category images | P2 | Custom | |
| NAV-12 | Gift landing pages for each gift collection (Father's Day, Groomsmen, Corporate) | P2 | Content | Uses NAV-10 patterns |
| NAV-13 | Instant search: autocomplete, typo tolerance, synonyms ("cedar box" → humidor) | P2 | Plugin | |
| NAV-14 | Gift-finder quiz (budget, capacity, recipient) | P3 | Custom | |

### 4.5 Cart & checkout (CHK)

| ID | Feature | Pri | How | Notes |
|---|---|---|---|---|
| CHK-01 | Block-based cart and checkout; guest checkout; optional account creation | P0 | Core | |
| CHK-02 | Mini-cart drawer opened on add-to-cart | P0 | Core | |
| CHK-03 | Coupons: % off, fixed amount, free shipping, usage limits, expiry | P0 | Core | Needed for launch / welcome offers |
| CHK-04 | US-only addresses with state dropdown; phone number for carrier | P0 | Config | |
| CHK-05 | Terms & privacy links and acceptance at checkout | P0 | Core | |
| CHK-06 | Order-received page with clear next steps (ship time, tracking email, support contact) | P0 | Custom | |
| CHK-07 | "This is a gift" option: gift message + gift receipt (no prices on packing slip) | P1 | Custom | Strong fit with gift positioning |
| CHK-08 | Cart recommendations in mini-cart ("You may also like"), as on the reference site | P2 | Custom | |
| CHK-09 | Free-shipping progress bar ("$25 away from free shipping") | P2 | Custom | Only if a threshold is used (D-05) |
| CHK-10 | US address autocomplete and validation | P2 | Plugin | Reduces failed deliveries |
| CHK-11 | Paid gift wrapping | P2 | Custom | |
| CHK-12 | Post-purchase one-click upsell on order-received page | P3 | Plugin | |

### 4.6 Payments (PAY)

| ID | Feature | Pri | How | Notes |
|---|---|---|---|---|
| PAY-01 | Card payments through Stripe (hosted fields, 3-D Secure); card data never touches the server | P0 | Plugin | Official *WooCommerce Stripe Payment Gateway* |
| PAY-02 | Apple Pay, Google Pay and Link express buttons on product, cart and checkout | P0 | Plugin | Same plugin; needs Apple Pay domain verification |
| PAY-03 | PayPal, Venmo and Pay Later | P0 | Plugin | Official *WooCommerce PayPal Payments*. If approval lags, launch on Stripe and move to P1. Note: Pay Later transactions cost 4.99 % + 49 ¢ (standard PayPal checkout is 3.49 % + 49 ¢); enable it knowingly |
| PAY-04 | Full and partial refunds from WP admin, synced to the gateway | P0 | Core | |
| PAY-05 | Payment webhooks configured and monitored (success, failure, refunds, disputes) | P0 | Config | Missed webhooks = orders stuck in "pending" |
| PAY-06 | Fraud basics: Stripe Radar defaults, AVS/CVC checks, manual review of orders above a set value | P0 | Config | Humidors are high-ticket; tune rules in P1 |
| PAY-07 | Custom Radar rules and a chargeback/dispute runbook (evidence: tracking, delivery confirmation) | P1 | Ops | |
| PAY-08 | Buy now, pay later (Klarna, Afterpay, Affirm) via Stripe for high-ticket items | P2 | Plugin | Same Stripe plugin |
| PAY-09 | Saved payment methods for returning customers | P2 | Plugin | |
| PAY-10 | Gift cards / store credit | P3 | Plugin | |

### 4.7 Shipping & fulfillment (SHP)

| ID | Feature | Pri | How | Notes |
|---|---|---|---|---|
| SHP-01 | Shipping zones: US contiguous states at launch; decide on Alaska, Hawaii, territories and APO/FPO (D-06) | P0 | Config | |
| SHP-02 | Shipping methods: flat rate and/or free shipping (always or above a threshold), with shipping classes for oversized items (cabinet humidors) | P0 | Config | Simple rules beat real-time rates at launch |
| SHP-03 | Ship-time messaging on product page and cart ("Ships in 1–2 business days from our US warehouse"; personalised items + X days) | P0 | Custom | Must match reality (CMP-05) |
| SHP-04 | Tracking number on the order and a "Shipped" email with carrier tracking link | P0 | Plugin | Shipment-tracking extension or the 3PL/shipping integration |
| SHP-05 | Fulfillment hand-off to the US warehouse/3PL: manual at launch (CSV export, portal or email), automated order push + tracking back in P1 | P0 | Ops | Automation is P1 once the 3PL is chosen (D-02) |
| SHP-06 | Automated 3PL integration: orders pushed, tracking and stock returned | P1 | Plugin | Use the 3PL's WooCommerce connector if one exists |
| SHP-07 | Customer order-tracking page (order number + email) | P1 | Custom | Cuts "where is my order?" emails |
| SHP-08 | Packing slips and invoices (PDF), including engraving details and gift message | P1 | Plugin | |
| SHP-09 | Holiday shipping cut-off banner and countdown | P1 | Custom | Uses NAV-02 |
| SHP-10 | Label printing for orders shipped by the business itself (not 3PL) | P1 | Plugin | Only if self-fulfilling |
| SHP-11 | Real-time carrier rates (USPS, UPS, FedEx) | P2 | Plugin | Only if flat rates lose money on heavy items |
| SHP-12 | Shipping insurance and signature-required option for high-value orders | P2 | Config | |
| SHP-13 | Estimated delivery date by ZIP code | P3 | Plugin | |
| SHP-14 | Canada and international shipping | P4 | Config | Adds duties, tax and returns complexity |

### 4.8 Order management & admin experience (ORD)

| ID | Feature | Pri | How | Notes |
|---|---|---|---|---|
| ORD-01 | Order management (HPOS): statuses, notes, refunds, resend emails, search and filters | P0 | Core | |
| ORD-02 | New-order and failed-payment notifications to the manager | P0 | Core | |
| ORD-03 | Branded transactional emails: order confirmation, processing, shipped, refunded, cancelled, account emails | P0 | Config | Logo, colours, support contact, ship-time promise |
| ORD-04 | Site manager uses the **Shop Manager** role (products, orders, coupons, reports; no plugins, themes or code) with 2FA | P0 | Config | Content editing via Editor capabilities if needed |
| ORD-05 | Manager handbook `docs/manager-guide.md`: add/edit product, change price, run a sale, process order, refund, add tracking, edit pages | P0 | Content | Plus short screen recordings |
| ORD-06 | Sales analytics: revenue, orders, products, coupons, stock, order source attribution | P0 | Core | WooCommerce Analytics + Order Attribution |
| ORD-07 | Low-stock and out-of-stock email alerts | P0 | Core | |
| ORD-08 | Custom order statuses: *Awaiting engraving*, *Sent to warehouse* | P1 | Custom | Needed once CAT-13 / SHP-06 exist |
| ORD-09 | Simplified admin for the manager: hide unused menus, dashboard widget with quick links and handbook | P1 | Custom | |
| ORD-10 | Shared support inbox / helpdesk (email + contact form tickets) | P1 | Service | Later the base for AI-02 and AI-03 |
| ORD-11 | Admin activity log (who changed what: manager, agents, plugins) | P1 | Plugin | Very useful when humans and agents both change the site |
| ORD-12 | Order export (CSV) for accounting | P1 | Core | |
| ORD-13 | Returns / RMA requests from My Account with status tracking (launch: email-based process per policy) | P2 | Plugin | |
| ORD-14 | Accounting sync (QuickBooks / Xero) | P3 | Plugin | |

### 4.9 Customer accounts (ACC)

| ID | Feature | Pri | How | Notes |
|---|---|---|---|---|
| ACC-01 | My Account: order history, order details, addresses, account details, password reset | P0 | Core | |
| ACC-02 | Customer data export / erasure requests | P1 | Core | WordPress privacy tools |
| ACC-03 | Social login (Google, Apple) | P3 | Plugin | |
| ACC-04 | "Buy again" / reorder | P4 | Custom | Low value for durable goods |

### 4.10 Content & brand (CNT)

| ID | Feature | Pri | How | Notes |
|---|---|---|---|---|
| CNT-01 | Brand system in `theme.json` (logo, colours, typography, spacing) and block patterns; no page builder | P0 | Custom | Manager edits content, not design |
| CNT-02 | Core pages: About / Our Craft, Contact, FAQ / Help Center, Shipping Policy, Returns & Refunds, Warranty, Privacy Policy, Terms of Service, Accessibility Statement | P0 | Content | Policy texts reviewed by the owner (legal review recommended) |
| CNT-03 | Contact form with spam protection (Cloudflare Turnstile) routed to the support inbox | P0 | Plugin | |
| CNT-04 | **Coming-soon page with email signup** while the store is built | P0 | Core | Do this first; WooCommerce has a built-in coming-soon mode |
| CNT-05 | Humidor care & seasoning guide, linked from every humidor product | P1 | Content | Top customer question category |
| CNT-06 | Journal / blog with categories (guides, gift ideas, craftsmanship) and 3–5 launch articles | P1 | Content | Main acquisition channel given ad restrictions |
| CNT-07 | Trust bar: secure checkout, US shipping, returns, warranty, handcrafted | P1 | Custom | |
| CNT-08 | Craftsmanship story: Vietnam workshop, wood species, process photos and video | P1 | Content | Core of the brand |
| CNT-09 | Press ("As seen in") and testimonials — real ones only | P2 | Content | Reference site shows press logos |
| CNT-10 | Corporate gifting page with quote-request form (quantity, logo engraving, deadline) | P2 | Custom | Pairs with MKT-08 |
| CNT-11 | Wood species & materials pages (Spanish cedar, walnut, rosewood…) | P2 | Content | SEO + transparency |
| CNT-12 | Wholesale / authorized-retailer application page | P3 | Custom | Reference site has "Become a Partner" form |
| CNT-13 | Downloadable care manuals (PDF) and warranty registration | P3 | Content | |

### 4.11 SEO (SEO)

| ID | Feature | Pri | How | Notes |
|---|---|---|---|---|
| SEO-01 | SEO plugin: title/meta templates, XML sitemaps, canonicals, robots rules, Open Graph / social cards | P0 | Plugin | One of Yoast, Rank Math, SEOPress |
| SEO-02 | Structured data: Product / Offer (price, availability, shipping and return policy), BreadcrumbList, Organization, WebSite | P0 | Core | WooCommerce + SEO plugin; validate with Rich Results Test |
| SEO-03 | URL structure fixed before launch: products `/products/<slug>/`, categories `/collections/<slug>/` (flat, unique slugs), journal `/journal/<slug>/` | P0 | Config | Shopify-compatible paths, so a future storefront move (SHO-02) keeps every URL. Changing later costs rankings |
| SEO-04 | Google Search Console and Bing Webmaster Tools verified; sitemaps submitted | P0 | Ops | |
| SEO-05 | Image SEO: descriptive file names and required alt text (checklist in handbook) | P0 | Content | Also an accessibility requirement |
| SEO-06 | Review / AggregateRating structured data once reviews exist | P1 | Core | |
| SEO-07 | 301 redirect manager (for renamed products/pages) | P1 | Plugin | Often included in the SEO plugin |
| SEO-08 | Content plan with topic hubs and internal linking (buying guides, comparisons, care) | P2 | Content | |
| SEO-09 | Google Merchant Center product feed for free listings | P2 | Plugin | Check policy first: Google bars products that "facilitate tobacco consumption"; humidors may be disapproved. Expect rejections: submit one product first and keep the rest out of the feed until it is approved |
| SEO-10 | Pinterest catalog / rich pins | P3 | Plugin | Gift and décor audience |

### 4.12 Marketing & retention (MKT)

| ID | Feature | Pri | How | Notes |
|---|---|---|---|---|
| MKT-01 | Email platform connected (Klaviyo, Omnisend, MailPoet…): footer signup + marketing opt-in at checkout | P0 | Service | Start collecting from day 1 |
| MKT-02 | GA4 e-commerce tracking (view_item, add_to_cart, begin_checkout, purchase) with Consent Mode | P0 | Plugin | Basic install P0, full event QA P1 |
| MKT-03 | Welcome offer popup (e.g. 10 % off first order, like the reference site): delayed or exit-intent, frequency-capped, mobile-friendly | P1 | Service | |
| MKT-04 | Email flows: welcome series, abandoned cart, post-purchase care tips, review request | P1 | Service | Abandoned cart is the highest-ROI flow |
| MKT-05 | Product reviews: verified-buyer star ratings on product pages (core reviews on at launch), then automated review requests and photo reviews | P1 | Plugin | Core reviews enabled in P0; never gate or fake reviews (CMP-09) |
| MKT-06 | More flows: browse abandonment, win-back, birthday / anniversary of purchase | P2 | Service | |
| MKT-07 | Promotions: automatic discounts (tiered, BOGO, bundle), scheduled campaigns, Black Friday playbook | P2 | Plugin | |
| MKT-08 | Corporate / bulk orders: quote requests and quantity-tier pricing | P2 | Custom | |
| MKT-09 | Ad & social pixels (Meta, Pinterest, TikTok) with server-side conversions | P2 | Plugin | Platforms may restrict tobacco-adjacent ads; triggers CMP-03 opt-out link. Expect ad or ad-account rejections: test one product on one platform before investing in pixels and budget |
| MKT-10 | Referral program ("give $20, get $20") | P3 | Plugin | |
| MKT-11 | SMS marketing with TCPA-compliant opt-in | P3 | Service | |
| MKT-12 | Affiliate / influencer program with tracked links and commissions | P3 | Plugin | |
| MKT-13 | Wholesale pricing: role-based prices, minimum order quantities, net terms | P3 | Plugin | Pairs with CNT-12 |
| MKT-14 | Instagram / user-generated photo gallery | P3 | Plugin | |
| MKT-15 | Loyalty / rewards points | P4 | Plugin | Low repeat rate for durable goods |

### 4.13 Compliance, legal & trust — US (CMP)

| ID | Feature | Pri | How | Notes |
|---|---|---|---|---|
| CMP-01 | Sales tax calculated at checkout for every state with nexus (at minimum the warehouse/3PL state), product tax codes set | P0 | Service | Options: Stripe Tax, WooCommerce Tax (calculation only), TaxJar, Avalara (D-07) |
| CMP-02 | Country of origin shown on product pages ("Handcrafted in Vietnam"); nothing implies "Made in USA" | P0 | Content | FTC origin rules |
| CMP-03 | Privacy: privacy policy, cookie notice, Google Consent Mode; "Do Not Sell or Share" link and Global Privacy Control honoured once ad pixels are added (MKT-09) | P0 | Plugin | Notice in P0; opt-out mechanism with MKT-09 |
| CMP-04 | Accessibility target WCAG 2.2 AA: keyboard navigation, focus states, contrast, labels, alt text; accessibility statement | P0 | Custom | Built in from the start; ADA demand letters target e-commerce |
| CMP-05 | Ship-time promises match reality; process for delay notices and cancellation option | P0 | Ops | FTC Mail Order Rule |
| CMP-06 | California Prop 65 warnings on product page / checkout **if** a product requires one (wood dust, finishes) | P0 | Custom | Owner decides with supplier (D-08) |
| CMP-07 | CAN-SPAM: unsubscribe link and physical address in every marketing email | P0 | Service | |
| CMP-08 | Automated sales-tax filing / remittance and economic-nexus monitoring | P1 | Service | WooCommerce Tax doesn't file; Stripe Tax, TaxJar, Avalara or a filing service can |
| CMP-09 | Honest reviews and endorsements: no fake, gated or undisclosed incentivised reviews | P1 | Ops | FTC rule on consumer reviews |
| CMP-10 | Material disclosures: wood species and finish on every product (from CAT-03) | P1 | Content | Supports Lacey Act declarations and customer questions |
| CMP-11 | Per-product import compliance data: wood species (genus + species) for every wood incl. lining and veneer, country of harvest, composite-wood flag (plywood/MDF/particleboard → TSCA Title VI), HTS code | P0 | Custom | Private product fields in `cigarholic-core`, included in the CSV template (CAT-10), checked by `wp cigarholic catalog:check`, exportable for the customs broker (Lacey Act, TSCA, L-11); feeds CMP-10 |

### 4.14 AI customer service — later phase (AI)

Most of this is P3, but the groundwork is cheap and starts earlier: clean structured product data (CAT-03), real FAQ and policy pages (CNT-02) and a helpdesk (ORD-10).

| ID | Feature | Pri | How | Notes |
|---|---|---|---|---|
| AI-01 | Structured knowledge base: FAQ, policies, care guides and product specs written as clean, single-source content | P1 | Content | Same content serves humans, SEO and the AI |
| AI-02 | Chat widget with human handoff to the helpdesk (no AI yet) | P2 | Service | Measures what customers ask |
| AI-03 | AI drafting of email/ticket replies in the helpdesk, human approves before sending | P2 | Service | Lowest-risk first use of AI |
| AI-04 | AI-assisted content: draft product descriptions and alt text for manager review | P2 | Service | |
| AI-05 | On-site AI assistant: answers product, sizing, care and policy questions grounded in catalog + knowledge base (e.g. Claude API with retrieval) | P3 | Custom | Responses cite product links |
| AI-06 | Order-status lookup in chat after verification (order number + email or ZIP), read-only scoped API | P3 | Custom | Never exposes other customers' data |
| AI-07 | Guardrails: stays on store topics, no health or legal claims, never promises beyond policy, escalates to human, logs conversations, regression test set of real questions | P3 | Custom | Required before AI-05 goes live |
| AI-08 | Insights loop: unanswered or escalated questions become FAQ / content tasks | P3 | Custom | |
| AI-09 | AI product recommendations ("which humidor for 50 cigars under $300?") | P3 | Custom | Uses CAT-03 attributes |
| AI-10 | Owner/agent store operations through WooCommerce MCP and the WordPress Abilities API (staging first) | P3 | Core | Still labelled developer preview by WooCommerce; read-only on production at first |

### 4.15 Mobile app for order management — later phase (APP)

| ID | Feature | Pri | How | Notes |
|---|---|---|---|---|
| APP-01 | **Official WooCommerce mobile app** (iOS/Android, free): orders, push notifications for new orders, product edits, analytics | P1 | Core | Covers most needs at zero development cost; set up and train the manager |
| APP-02 | API readiness: per-integration API keys / application passwords, least privilege, documented in `docs/integrations.md` | P2 | Config | Shared foundation for APP, INV, SHO, AI |
| APP-03 | Custom push alerts via webhooks (high-value order, failed payment, engraving queue) | P3 | Custom | |
| APP-04 | Custom order-management app (React Native/Expo or PWA over the WooCommerce REST API) — only for gaps the official app can't cover (engraving queue, warehouse status, barcode pick/pack) | P3 | Custom | Decide after 2–3 months using APP-01 |

### 4.16 Inventory platform integration — later phase (INV)

| ID | Feature | Pri | How | Notes |
|---|---|---|---|---|
| INV-01 | SKU convention and data hygiene: unique SKU per product/variation, barcode where available | P0 | Content | Cheap now, expensive to fix later |
| INV-02 | Decision record: which system owns stock (WooCommerce, inventory platform or 3PL) | P2 | Ops | ADR in `docs/adr/` |
| INV-03 | Integration layer in `cigarholic-core`: queued jobs (Action Scheduler), retries, idempotency keys, logs, admin status page | P3 | Custom | Shared by INV, SHO and 3PL sync |
| INV-04 | Two-way sync with the inventory platform: stock levels in, orders and refunds out (REST API + webhooks) | P3 | Custom | Use the platform's own connector if it's good enough |
| INV-05 | Inbound shipments from Vietnam (purchase orders, container ETA) feeding pre-order / backorder dates | P3 | Custom | Pairs with CAT-17 |
| INV-06 | Multi-location stock (US warehouse, Vietnam workshop) | P4 | Plugin | |

### 4.17 Shopify sync — optional (SHO)

| ID | Feature | Pri | How | Notes |
|---|---|---|---|---|
| SHO-01 | **Architecture rule:** WooCommerce is the system of record; the site has no runtime dependency on Shopify; any sync is a separate module that can be switched off | P0 | Custom | Enforced in AGENTS.md and code review |
| SHO-02 | Shopify-compatible product CSV export | P1 | Custom | Cheapest way to stand up a Shopify storefront if ever needed. Raised from P3 (2026-10-30): cheap once the catalog data model (CAT-03, CAT-09) and the Shopify-compatible URLs (SEO-03) exist, and it keeps the option open with no runtime dependency (SHO-01) |
| SHO-03 | One-way product and inventory sync WooCommerce → Shopify (Shopify Admin API) | P3 | Custom | Built on INV-03 |
| SHO-04 | Orders from Shopify back into WooCommerce (two-way) | P4 | Custom | Only if Shopify becomes a real sales channel |

### 4.18 Other channels & markets (CHN)

| ID | Feature | Pri | How | Notes |
|---|---|---|---|---|
| CHN-01 | Marketplace listings (Amazon, Etsy, Walmart) fed from WooCommerce | P4 | Plugin | Check each marketplace's category rules |
| CHN-02 | Multi-currency and international storefront | P4 | Plugin | After SHP-14 |

### 4.19 Explicitly out of scope

| Item | Reason |
|---|---|
| Selling cigars, tobacco, nicotine or vaping products | Business decision; would trigger PACT Act, age verification and payment restrictions |
| Age-verification gate | Not legally required for wooden accessories; revisit only if the product range changes |
| Page builders (Elementor, Divi, etc.) | Hard for agents to diff and review; use blocks + patterns |
| Headless / decoupled front end | Adds cost and complexity without a clear benefit at this stage |
| Shopify as a dependency | See SHO-01 |

---

## 5. Launch prerequisites outside the codebase (owner)

These usually decide the launch date more than development does.

| # | Prerequisite | Why it blocks launch |
|---|---|---|
| L-01 | Stripe account approved for live payments; Apple Pay domain verified | No card / wallet payments without it |
| L-02 | PayPal Business account approved | PAY-03 |
| L-03 | Business description for payment providers: "handcrafted wooden storage boxes and humidors; no tobacco sold" | Stripe and PayPal list cigars/tobacco as restricted; accessories aren't named, so a clear description avoids delays |
| L-04 | Sales-tax registration in nexus state(s) (incl. the 3PL state) and tax tool chosen | CMP-01 |
| L-05 | US warehouse / 3PL contracted and first stock received | Nothing to ship otherwise |
| L-06 | Product photos (multiple angles, wood detail, interior, scale), specs and copy for every launch product | CAT-04 – CAT-06 |
| L-07 | Prices, shipping rates and free-shipping rule set | SHP-02 |
| L-08 | Returns window, who pays return shipping, warranty length, personalised-item policy | CNT-02 |
| L-09 | Prop 65 determination with the workshop (wood dust, finishes, coatings) | CMP-06 |
| L-10 | Logo, brand colours, fonts; domain registered; business email set up | CNT-01, OPS-02, OPS-04 |
| L-11 | Customs/import paperwork in place (HTS classification, Lacey Act declaration, CITES where needed for species like Spanish cedar, TSCA Title VI for composite wood) | Stock can't clear customs without it |

---

## 6. Open decisions

| ID | Decision | Affects | Suggested default |
|---|---|---|---|
| D-01 | Theme base: custom block theme vs child of an existing block theme | CNT-01, NAV | Custom block theme, kept small |
| D-02 | Fulfillment at launch: 3PL vs self-ship from a US address; which 3PL | SHP-05, SHP-06, SHP-10 | 3PL with a WooCommerce connector |
| D-03 | Where laser engraving is done (Vietnam before shipping vs US warehouse/partner) and the lead time | CAT-13, SHP-03 | Decide before promising personalisation. Note: de minimis is suspended for all countries, so a single engraved parcel shipped from Vietnam pays full duty (about 12.5 %) and may need formal entry; engraving in the US (warehouse or partner) avoids that. Default until decided: not in P0, CAT-13 built in P1 and fulfilled in the US |
| D-04 | Email marketing platform | MKT-01, MKT-03, MKT-04 | Klaviyo (strong e-commerce flows) or MailPoet (runs inside WP) |
| D-05 | Free-shipping policy (always free vs threshold) | SHP-02, CHK-09 | Free shipping on all humidors, priced in |
| D-06 | Ship to Alaska, Hawaii, territories, APO/FPO? | SHP-01 | Contiguous US at launch |
| D-07 | Sales-tax tool | CMP-01, CMP-08 | Stripe Tax if Stripe is the main gateway |
| D-08 | Is a Prop 65 warning needed? | CMP-06 | Ask the workshop for finish/material data |
| D-09 | SEO plugin | SEO-01 | Whichever the team knows; only one |
| D-10 | Helpdesk tool (must have an API for AI-02, AI-03, AI-05 later) | ORD-10, AI | Any helpdesk with API + email channel |

---

## 7. Reference site takeaways — peterjamesco.com

Reviewed 2026-10-30. It runs on Shopify; features worth matching or beating:

| Seen on reference | Our feature |
|---|---|
| Announcement bar promoting sales | NAV-02 |
| Compare-at prices and "% off" badges | CAT-07 |
| Cart drawer with "You may like" products | CHK-02, CHK-08 |
| Detailed specs: open/closed dimensions, cigar fit by ring gauge and length, humidification details | CAT-03, CAT-04, CAT-16 |
| Press logos (*Cigar Aficionado*, *Forbes*…) | CNT-09 |
| "10 % off your first order" signup | MKT-01, MKT-03 |
| "Become a Partner" retailer application form | CNT-12, MKT-13 |
| Help Center, policy pages, contact form | CNT-02, CNT-03 |
| PayPal and Venmo | PAY-03 |
| Social links incl. YouTube and TikTok | NAV-03 |
| Company and fulfillment location in footer | NAV-03 |
| News blog | CNT-06 |

Gaps on the reference site we can beat: no visible reviews, no delivery-time estimates, no engraving/personalisation, thin help-center content, and humidity claims on one product page that don't agree with each other — keep our specs consistent and verifiable.

---

## 8. Summary by priority

| Area | P0 | P1 | P2 | P3 | P4 | Total |
|---|---|---|---|---|---|---|
| FND – Foundation & agent workflow | 12 | 1 | 1 | 1 | 0 | 15 |
| OPS – Hosting, security & performance | 9 | 3 | 2 | 0 | 0 | 14 |
| CAT – Catalog & product pages | 10 | 4 | 5 | 5 | 1 | 25 |
| NAV – Navigation & merchandising | 7 | 3 | 3 | 1 | 0 | 14 |
| CHK – Cart & checkout | 6 | 1 | 4 | 1 | 0 | 12 |
| PAY – Payments | 6 | 1 | 2 | 1 | 0 | 10 |
| SHP – Shipping & fulfillment | 5 | 5 | 2 | 1 | 1 | 14 |
| ORD – Order management & admin | 7 | 5 | 1 | 1 | 0 | 14 |
| ACC – Customer accounts | 1 | 1 | 0 | 1 | 1 | 4 |
| CNT – Content & brand | 4 | 4 | 3 | 2 | 0 | 13 |
| SEO – SEO | 5 | 2 | 2 | 1 | 0 | 10 |
| MKT – Marketing & retention | 2 | 3 | 4 | 5 | 1 | 15 |
| CMP – Compliance & trust | 8 | 3 | 0 | 0 | 0 | 11 |
| AI – AI customer service | 0 | 1 | 3 | 6 | 0 | 10 |
| APP – Mobile order app | 0 | 1 | 1 | 2 | 0 | 4 |
| INV – Inventory integration | 1 | 0 | 1 | 3 | 1 | 6 |
| SHO – Shopify sync | 1 | 1 | 0 | 1 | 1 | 4 |
| CHN – Other channels | 0 | 0 | 0 | 0 | 2 | 2 |
| **Total** | **84** | **39** | **34** | **32** | **8** | **197** |

# Plugin register (FND-11)

Every plugin on the site, why it is there and who owns it. Budget: **15 active plugins or fewer** on production. A new plugin needs a row here and a justification in its PR; prefer Core → Config → Plugin → Custom.

Versions are pinned in `composer.lock`; `docs/versions.md` has the tested dates. Updates arrive as Composer PRs tested on staging.

## Active on production (planned at launch)

| # | Plugin | Package | Version | Feature IDs | Why | Owner | HPOS / block checkout |
|---|---|---|---|---|---|---|---|
| 1 | WooCommerce | `wpackagist-plugin/woocommerce` | 11.2.1 | all | The store | Agents | yes (we enable HPOS, block cart/checkout) |
| 2 | WooCommerce Stripe Payment Gateway | `wpackagist-plugin/woocommerce-gateway-stripe` | 11.1.1 | PAY-01, PAY-02, PAY-04, PAY-05, PAY-06 | Cards, 3DS, Apple Pay, Google Pay, Link; official plugin, not WooPayments | Owner (account), agents (config) | yes |
| 3 | WooCommerce PayPal Payments | `wpackagist-plugin/woocommerce-paypal-payments` | 4.2.0 | PAY-03, PAY-04, PAY-05 | PayPal, Venmo, Pay Later; official plugin | Owner (account), agents (config) | yes |
| 4 | Stripe Tax for WooCommerce | `wpackagist-plugin/stripe-tax-for-woocommerce` | 2.2.0 | CMP-01, CMP-08 | Sales tax per nexus state (D-07); TaxJar is the fallback | Owner (registrations), agents | yes |
| 5 | SEOPress | `wpackagist-plugin/wp-seopress` | 10.4 | SEO-01, SEO-02, SEO-07 | Titles, sitemaps, canonicals, Open Graph, redirects (D-09); the one SEO plugin | Agents | yes |
| 6 | Two Factor | `wpackagist-plugin/two-factor` | 0.17.0 | OPS-07, ORD-04 | 2FA for every admin and shop manager; WordPress-maintained | Agents | n/a |
| 7 | Redis Object Cache | `wpackagist-plugin/redis-cache` | 3.0.0 | OPS-09 | Object cache in Valkey (Redis protocol, `php-redis`) | Agents | n/a |
| 8 | FluentSMTP | `wpackagist-plugin/fluent-smtp` | 2.4.1 | OPS-04, ORD-03 | Transactional mail through Postmark | Agents | n/a |
| 9 | Complianz | `wpackagist-plugin/complianz-gdpr` | 7.5.6 | CMP-03, MKT-02 | Cookie notice and Google Consent Mode; Premium only when ad pixels arrive (MKT-09) | Agents | n/a |
| 10 | Google Analytics for WooCommerce | `wpackagist-plugin/woocommerce-google-analytics-integration` | 2.4.4 | MKT-02 | GA4 e-commerce events | Agents | yes |
| 11 | Modern Image Formats (Performance Lab) | `wpackagist-plugin/webp-uploads` | 2.7.1 | OPS-09 | WebP/AVIF generation for uploads; removed if core does it natively | Agents | n/a |

**Active count at launch: 11 of 15.** The mu-plugin `cigarholic-core` and the theme are not plugins in this count.

## Development only (never active on production)

| Plugin | Package | Version | Why |
|---|---|---|---|
| Query Monitor | `wpackagist-plugin/query-monitor` | 4.1.1 | Debugging in the Compose runtime; `require-dev`, excluded by `composer install --no-dev` on servers |

## Considered and not installed

| Plugin | Decision |
|---|---|
| WooPayments | Brief: Stripe and PayPal official plugins instead |
| Cloudflare | Cache purge runs from the deploy workflow through the API; APO only if cart/checkout bypass is verified (OPS-09, later). Cloudflare itself is deferred by the owner |
| Klaviyo / Omnisend / MailPoet | D-04 undecided; coming-soon signups are stored by `cigarholic-core` and exported later (MKT-01) |
| Advanced Shipment Tracking | Only if the 3PL connector does not return tracking (SHP-04, P1) |
| 3PL connector | After D-02 (P1) |
| Action Scheduler (standalone) | Bundled with WooCommerce; used by `cigarholic-core` |
| Contact form plugins | Contact form with Turnstile is written in `cigarholic-core` (CNT-03) |
| Page builders | Out of scope (docs/FEATURES.md section 4.19) |

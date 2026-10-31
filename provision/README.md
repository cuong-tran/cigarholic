# Provisioning: settings as code (FND-03)

`wp cigarholic provision` runs every file in `steps/` in name order. A step is a PHP file that returns a callable receiving a `Cigarholic\Core\Provision\Context`; it compares before it writes, so running it again changes nothing. `--dry-run` reports what would change, `--step=00-core` runs one step.

The deploy workflow runs provisioning after every release; `scripts/runtime-up.sh` runs it in the Compose runtime. Settings changed in WP admin by hand are overwritten on the next run: change the step instead.

| Step | Feature IDs | Content |
|---|---|---|
| `00-core` | FND-03, SEO-03 | Site title, tagline, timezone (Florida HQ), formats, registration off, comments closed by default, permalink structure `/%postname%/` |
| `10-woocommerce-general` | FND-03, CHK-01, ORD-07, MKT-05 | Store address (FL HQ), USD, sell and ship to the US only, lbs and inches, stock management with 60-minute holds and low-stock alerts to the admin email, verified-owner reviews, no telemetry or marketplace suggestions, onboarding skipped and task list hidden, Order Attribution on, HPOS on (store without orders; compatibility sync off), WooCommerce block pages (shop, cart, checkout, my-account) recreated when missing |
| `11-woocommerce-checkout` | FND-03, CHK-01, CHK-03, CHK-04 | Guest checkout, optional account creation at checkout (generated password), no stand-alone registration, phone required, company optional, coupons on, non-sequential discounts |
| `12-woocommerce-permalinks` | FND-03, SEO-03 | `woocommerce_permalinks`: products `/products/`, categories `collections`, tags `product-tag`, no attribute base; rewrite flush queued |
| `20-pages` | FND-03, CNT-02, CHK-05, SEO-03 | Draft placeholder pages with the headings a real text needs (`about`, `contact`, `faq`, `shipping-policy`, `returns-refunds`, `warranty`, `privacy-policy`, `terms-of-service`, `accessibility-statement`), published `home` (front page, theme template) and `journal` (posts page), `show_on_front` = page, terms and privacy pages for checkout, WordPress sample page trashed. Existing pages are never overwritten |
| `30-navigation` | FND-03, NAV-01 | The "Primary" `wp_navigation` menu (Shop, Humidors, Cigar Boxes, Accessories, Gift Sets, Journal, About, Contact), updated in place; every other navigation post is removed so the header's `ref`-less block always falls back to it |
| `40-roles` | FND-03, ORD-04 | Shop Manager: WooCommerce, product, order and coupon capabilities, pages and posts, import/export; never plugins, themes, theme options, core updates, file editing or `manage_options`. Administrator keeps every WooCommerce capability |
| `50-shipping` | FND-03, SHP-01, SHP-02 | Shipping classes `humidor`, `oversized-humidor`, `accessory`; zone "Contiguous US" (48 states + DC) with one taxable flat rate per order (humidors 0, accessories 9.95 placeholder until L-07); zone "Alaska, Hawaii and US territories" without methods (D-06); rates by shipping address, calculator on the cart |
| `60-tax` | FND-03, CMP-01 | Taxes on, prices entered and displayed excluding tax, based on the shipping address, shipping taxable, itemized totals; rates come from Stripe Tax (owner connects it, OWNER-ACTIONS B4) |
| `70-emails` | FND-03, ORD-02, ORD-03, CMP-07 | Sender "Cigarholic <support@cigarholic.com>", footer with company and both addresses, colours from the theme palette, new / cancelled / failed order emails to the admin email, customer order and account emails on |
| `80-coming-soon` | FND-03, CNT-04 | WooCommerce coming-soon mode on for the whole site, no private link; the theme's `coming-soon.html` renders for visitors, administrators and shop managers see the store |

Order matters where a step needs what an earlier one made: `20-pages` creates the terms and privacy pages and sets `woocommerce_terms_page_id` and `wp_page_for_privacy_policy`; `50-shipping` creates the shipping classes before the flat rate that prices them. Content the manager owns (page text, status) is created once and never overwritten; settings are.

Steps use the `Context` helpers (`option`, `page`, `term`, `navigation`, `changed`, `log`, `is_dry_run`), WooCommerce's CRUD classes (`WC_Shipping_Zone`, `WC_Install::create_pages`) and plain options; never raw SQL. PHPUnit covers them in `tests/phpunit/ProvisionTest.php` (every step applies, a second run is a no-op, a dry run writes nothing) and `tests/phpunit/RolesTest.php`.

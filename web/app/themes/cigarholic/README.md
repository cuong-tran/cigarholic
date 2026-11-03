# Cigarholic block theme

Presentation layer of the Cigarholic store (feature IDs FND-05, CNT-01, NAV-01 to NAV-04, CNT-04, CAT-06, CHK-06, CMP-04). Everything visual is a token in `theme.json`; markup is block templates, parts and patterns; `functions.php` only declares theme supports, a pattern category and one block style. All logic, including the dynamic blocks the templates reference, lives in the `cigarholic-core` mu-plugin.

Design rules that this theme carries: no page builder, WCAG 2.2 AA from the first template, no external requests (the brand fonts are self-hosted, ADR 0009), "Handcrafted in Vietnam" and never anything that implies tobacco is sold or that a product is made in the USA. The brand system (colours, type, contrast rules) comes from the owner's design canvas; the plan is `docs/plans/CNT-01-brand-style.md`.

## What is in it

```text
style.css                  theme header + the few rules theme.json cannot express (section-aware focus ring,
                           error and eyebrow colours, outline buttons on Forest, font synthesis, skip link
                           colours, 24 px targets, reduced motion, print)
theme.json                 version 3: brand palette, self-hosted font faces, fluid type scale, spacing scale, layout,
                           element and block styles, template part areas
functions.php              theme supports, pattern category "cigarholic", block styles "card" and "eyebrow",
                           font preloads, stylesheet
templates/                 index, front-page, page, single (journal post), archive, home (/journal/),
                           search, 404, single-product, archive-product, taxonomy-product_cat,
                           order-confirmation, coming-soon
parts/                     header on Forest (announcement bar, logo, primary menu, search, account, mini cart;
                           Forest phone menu)
                           footer on Forest (logo, shop/help/company menus, newsletter, company + warehouse, social,
                           payment methods)
patterns/                  hero, category-grid, best-sellers, craftsmanship-story, trust-bar,
                           product-content, gift-landing, newsletter, faq, coming-soon-content
styles/sections/forest.json  the Forest section style (dark sections: header, hero, footer, coming soon)
assets/fonts/              Young Serif and Hanken Grotesk woff2 (latin) with their OFL licences
assets/brand/              logo (WebP), site icon (PNG, the C on Forest) and email logo (PNG); provisioning step 25
                           imports all three into the media library (masters in /brand)
assets/images/             SVG placeholders used until photos exist: product and workshop in the brand's photo
                           sand, hero in Forest raised
```

WooCommerce supplies cart, checkout, my-account, product search results and the mini-cart drawer from its own block templates; the theme does not override them.

## Tokens (theme.json)

| Kind | Slugs | Notes |
|---|---|---|
| Colours | `base` Ash #eee9e0 (page), `neutral-1` Ash tint #f7f4ef (cards, inputs, quiet sections), `line` #d6cec1 (dividers only), `neutral-2` Stone #5e574e (muted text), `contrast` Ink #1a1815 (text), `accent` Cedar #9c5034 (links and buttons on light), `forest` #1f2b25 (dark sections), `forest-2` #26342d (panels on Forest), `mist` #cfc9be (muted text on Forest), `gold` #c1904f (accent on Forest only) | Rules, all checked by `ThemeContrastTest`: on Ash and Ash tint use Ink, Stone, Cedar; on Forest use Ash, Mist, Gold. Gold on light is 2.36:1 and Cedar on Forest 2.52:1, so never. Lowest allowed pair: Cedar on Ash, 4.81:1 |
| Font families | `hanken-grotesk` (body, buttons, navigation, h4 to h6), `young-serif` (h1 to h3, site title, post titles, quotes) | Self-hosted woff2 in `assets/fonts/` (latin; Young Serif 400 only, Hanken Grotesk variable 100 to 900), OFL licences beside them, both preloaded by `functions.php`. Young Serif has no bold or italic: serif text stays at 400 and upright (`font-synthesis: none`) |
| Font sizes | `x-small` (labels), `small`, `medium` (body, 17 px), `large` (lead), `x-large` (H3), `xx-large` (H2), `xxx-large` (H1, 40 to 64 px) | Fluid except `x-small` and `small` |
| Spacing | `10` to `80` (0.25 rem up to a clamp of 3.5 to 7 rem) | Use `var:preset\|spacing\|50` in block attributes, `var(--wp--preset--spacing--50)` in CSS |
| Shadow | none | The canvas's shadows belong to product mock-ups; web surfaces are flat |
| Layout | content 1200 px, wide 1400 px, root padding `clamp(1rem, 4vw, 2rem)` on both sides | `useRootPaddingAwareAlignments` is on, so full-width blocks bleed and constrained content keeps a 16 px gutter on phones, 32 px on desktop |

Block styles (registered in `functions.php` with `style_data`, so there is no extra stylesheet): `core/group` → **Card** (`is-style-card`): Ash tint fill, 1 px `line` border, square corners, `spacing.50` padding, no shadow. `core/paragraph` and `core/heading` → **Eyebrow** (`is-style-eyebrow`): Hanken Grotesk 12 px, 600, uppercase, +0.24 em; Cedar on light, Gold on Forest.

Button variations: default (Cedar with white text, Forest on hover; Gold with Ink text inside Forest) and core's `outline` (Ink border and text; Ash inside Forest). Radius 3 px.

Section style **Forest** (`styles/sections/forest.json`, slug `forest`, for `core/group` and `core/columns`): Forest background, Ash text and headings, Ash links with Gold hover, Gold buttons, Mist captions. Add `is-style-forest` (or pick "Forest" in the block's Styles panel) to make a section dark. `style.css` follows it with the focus ring (Ink on light, Gold on Forest), the form error colour (`#9b1c1c` on light, `#f2a08a` on Forest) and the eyebrow colour, through `--cigarholic-focus`, `--cigarholic-error` and `--cigarholic-eyebrow`. Text set with `textColor` presets inside Forest must come from the Forest rules (`mist`, not `neutral-2`).

## Blocks expected from cigarholic-core

The theme references these by name only. While the plugin is not active each one renders as nothing and the rest of the page is unaffected (self-closing dynamic blocks with no registered render callback produce empty output).

| Block | Where | Attributes used |
|---|---|---|
| `cigarholic/announcement-bar` | top of `parts/header.html` (NAV-02) | none; content and start/end dates come from the plugin's settings |
| `cigarholic/signup-form` | `parts/footer.html`, `patterns/newsletter.php`, `patterns/coming-soon-content.php` (CNT-04, D-04) | `heading` (string, rendered by the block as an H2), `buttonLabel` (string) |
| `cigarholic/spec-table` | `patterns/product-content.php`, so on every product page (CAT-04) | none; reads the product's attributes and numeric meta, inches + cm, lb + kg |

Two more plugin-side expectations: the primary menu is a `wp_navigation` post that provisioning creates (the header `core/navigation` block has no `ref`, so core falls back to the most recent published navigation menu; provisioning can also write its ID into the `ref` attribute of the template part), and the "Additional information" tab of `woocommerce/product-details` will be removed by cigarholic-core once the spec table exists, so attributes are not shown twice.

## Templates and the URLs they assume

Page slugs created by provisioning and linked from the footer, 404 page and patterns: `/shop/`, `/journal/`, `/about/`, `/contact/`, `/faq/`, `/shipping-policy/`, `/returns-refunds/`, `/warranty/`, `/privacy-policy/`, `/terms-of-service/`, `/accessibility-statement/`, `/my-account/`. Category links use `/collections/<slug>/` with the slugs `desktop-humidors`, `cabinet-humidors`, `travel-humidors`, `cigar-boxes`, `humidor-accessories`, `gift-sets` (ADR 0002 and 0003). Social links in the footer point at the platforms' home pages until the real profile URLs exist; the manager changes them in the Site Editor (footer part → "Social media" navigation).

Heading order: the hero and the product title carry the only H1 of their pages; the header shows the Site logo block (the wordmark, alt "Cigarholic", set by provisioning step 25) instead of the site title; on the coming-soon page the logo is followed by the H1 "Handcrafted humidors" in the Eyebrow style. Patterns meant for use inside a page (`gift-landing`, `faq`, `craftsmanship-story`, `trust-bar`, `newsletter`) start at H2 because `page.html` renders the page title as H1.

Landmarks: `header` and `footer` come from the template part areas, `main` from the `core/group` with `tagName: main` in every template, and every navigation block has an `ariaLabel` ("Primary", "Shop", "Help", "Company", "Social media"). The skip link is printed by core for block themes.

## How to add a pattern

1. Create `patterns/<name>.php` with the header (the first comment block is parsed by core):

   ```php
   <?php
   /**
    * Title: Human name
    * Slug: cigarholic/<name>
    * Categories: cigarholic
    * Template Types: page
    * Keywords: a, few, words
    * Description: One sentence.
    *
    * Feature IDs: ...
    *
    * @package Cigarholic
    */

   ?>
   <!-- wp:group ... -->
   ...
   <!-- /wp:group -->
   ```

2. Wrap every user-facing string with `esc_html__()` / `esc_html_e()` (text) or `esc_attr__()` (attributes, including values inside block JSON), text domain `cigarholic`. Build URLs with `esc_url( home_url( '/path/' ) )` and image sources with `esc_url( get_theme_file_uri( 'assets/...' ) )`.
3. Keep the block comments balanced and the saved HTML in core's serialization form (class order `wp-block-x align… className has-…-color has-…-background-color has-text-color has-background`, preset values as `var:preset|…` in JSON and `var(--wp--preset--…)` in `style`), otherwise the Site Editor flags the block as invalid.
4. Give every image an `alt`, start headings at the right level for where the pattern will be used, and prefer `core/details` over scripted accordions.
5. Reference it from a template with `<!-- wp:pattern {"slug":"cigarholic/<name>"} /-->` or insert it from the "Cigarholic" category in the editor. Core and remote pattern libraries are turned off, so only this theme's and WooCommerce's patterns appear.

## How to test

From the repository root, after `composer install`:

```bash
for f in web/app/themes/cigarholic/functions.php web/app/themes/cigarholic/patterns/*.php; do php -l "$f"; done
vendor/bin/phpcs --standard=WordPress-Extra --extensions=php web/app/themes/cigarholic
jq . web/app/themes/cigarholic/theme.json > /dev/null && echo theme.json ok
```

Balanced block comments (every `<!-- wp:name` opener closed by `<!-- /wp:name -->` or self-closing `/-->`):

```bash
python3 -I - web/app/themes/cigarholic/templates/*.html web/app/themes/cigarholic/parts/*.html web/app/themes/cigarholic/patterns/*.php <<'PY'
import re, sys
rx = re.compile(r'<!--\s+(/?)wp:([a-z0-9-]+/)?([a-z0-9-]+)(\s+\{.*?\})?\s*(/?)-->', re.S)
bad = 0
for p in sys.argv[1:]:
    stack = []
    for m in rx.finditer(open(p, encoding='utf-8').read()):
        closing, ns, name, _, selfclose = m.groups(); full = (ns or 'core/') + name
        if closing:
            if not stack or stack.pop() != full: print(p, 'bad closer', full); bad += 1
        elif not selfclose: stack.append(full)
    for full in stack: print(p, 'unclosed', full); bad += 1
print(len(sys.argv) - 1, 'files,', bad, 'errors'); sys.exit(1 if bad else 0)
PY
```

In the running store (`make up`), check on home, a category, a product, cart, checkout and the order-received page: the skip link lands on `main`; one H1 per page; Tab reaches every control with a visible focus ring; the mobile menu opens and closes with the keyboard; axe reports no violations; Lighthouse accessibility 100 and the mobile performance budget met. The coming-soon template is what anonymous visitors see while WooCommerce's coming-soon mode is on (provisioning turns it on, CNT-04).

## Status of the copy

All text is placeholder copy written to be true for the planned store (ships in 1 to 2 business days from Houston, contiguous US only at launch, Stripe and PayPal). It makes no claims about return windows, warranty terms or free shipping; those live on the policy pages the owner approves. Replace the SVG placeholders and alt texts when product photos arrive (OWNER-ACTIONS B9).

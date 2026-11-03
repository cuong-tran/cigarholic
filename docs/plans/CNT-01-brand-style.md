# Plan: apply the Cigarholic brand style to the theme (CNT-01)

Status: **PR 1 implemented** (2026-11-03: tokens, fonts, Forest section style, coming-soon page, email colours) **PR 2** (Forest header and footer) and **PR 5** (logo, site icon, email logo, from the owner's raster artwork); PRs 3 and 4 open. Source: the owner's design canvas "Cigarholic Brand" (claude.ai artifact `QssWn4fkbnqPAQcDJ66oLh`, version of 2026-10-30), boards *Logo suite*, *Color & type*, *Applications* and *Homepage hero*. The three earlier boards (A · The Band, B · Growth Rings, C · Dovetail) are explorations the canvas itself labels "earlier"; nothing is taken from them, including B's Cedar Light `#D9946E` and C's Archivo.

Feature IDs: CNT-01 (brand system), with NAV-01 (header), NAV-03 (footer), NAV-04 (homepage), CNT-04 (coming soon), CMP-04 (accessibility), ORD-03 (branded transactional emails). Satisfies most of OWNER-ACTIONS B9 (colours and fonts); the logo **files** are still missing (see "Owner inputs").

## 1. The style, as tokens

### Colour

| Brand name | Hex | Role in the canvas | theme.json slug (proposed) |
|---|---|---|---|
| Ash | `#EEE9E0` | Page ground | `base` (was `#ffffff`) |
| Ash tint | `#F7F4EF` | Cards, inputs, quiet sections, email body | `neutral-1` (was `#f4f1ec`) |
| Photo sand | `#DCD3C4` | Image placeholders | used only in the placeholder SVGs |
| Line | `#D6CEC1` | Hairlines, dividers | `line` (new) |
| Stone | `#5E574E` | Muted text, captions | `neutral-2` (was `#5b564f`) |
| Ink | `#1A1815` | Text, one-colour logo | `contrast` (was `#1c1917`) |
| Cedar | `#9C5034` | Links and buttons **on light** | `accent` (was `#6b4a2e`) |
| Forest | `#1F2B25` | Header, hero, footer, gift card; home of the gold logo | `forest` (new) |
| Forest raised | `#26342D` | Placeholder panel inside Forest | `forest-2` (new) |
| Mist | `#CFC9BE` | Body text on Forest | `mist` (new) |
| Gold | `#C1904F` | Accent **on dark only**: eyebrow, primary button on Forest, seal rings | `gold` (new) |

The existing slugs are kept and re-pointed so every template, part, pattern and the cigarholic-core block CSS keep working without a rename; only new roles get new slugs. Balance by area from the canvas: about 56 % light, 24 % Forest, 10 % Ink, 5 % Cedar, 5 % Gold.

Contrast, measured with the WCAG formula (AA needs 4.5:1 for text, 3:1 for UI and large text):

| Pair | Ratio | Rule that follows |
|---|---|---|
| Ink on Ash / on Ash tint | 14.65 / 16.15 | |
| Stone on Ash / tint / Photo sand `#DCD3C4` | 5.89 / 6.49 / 4.80 | Stone is the lightest muted text allowed on any light ground |
| **Cedar on Sand `#E3DCD0`** | **4.27** | Fails: Sand (a panel colour on the canvas boards, not in its Color & type palette) is not a web token; quiet sections use Ash tint (found by the page contrast check in PR 1) |
| Cedar on Ash / tint | 4.81 / 5.30 | Cedar links pass on light |
| White on Cedar, Ash on Cedar | 5.82 / 4.81 | Primary button on light: white text |
| Ash on Forest, Mist on Forest | 12.14 / 8.91 | |
| Gold on Forest | 5.15 | Gold labels and Gold button on Forest pass |
| Ink on Gold | 6.22 | Gold button text is Ink |
| **Cedar on Forest** | **2.52** | Never use Cedar on Forest (text or button) |
| **Gold on Ash** | **2.36** | Never use Gold as text or a control on light (the logo is exempt as a logotype) |
| **Line on Ash** | **1.29** | Line is decoration only; input borders use Stone or Ink (3:1 minimum) |
| **Ink focus ring on Forest** | **1.21** | Today's focus ring disappears on Forest: it must change per section |
| Error `#9b1c1c` on Ash / Ash tint | 6.74 / 7.43 | Today's signup/contact error colour stays on light grounds |
| **Error `#9b1c1c` on Forest** | **1.80** | Fails: inside Forest the error colour switches to `#F2A08A` |
| Error on dark `#F2A08A` on Forest / Forest raised | 7.11 / 6.31 | Lighter than Gold (relative luminance 0.46 vs 0.32), so an error never reads as a label by hue alone; the message text itself says what went wrong |

The two error colours are a `style.css` custom property (`--cigarholic-error`), not palette entries, so the manager never picks them in the editor.

### Type

| Use | Face | Size / line height | Notes |
|---|---|---|---|
| Display, H1 | Young Serif 400 | `clamp(2.5rem, 5vw, 4rem)` / 1.05, −0.01em | Hero 64/68 px at desktop |
| H2 | Young Serif 400 | `clamp(2rem, 3.5vw, 2.5rem)` / 1.15 | 40/46 px |
| H3, card titles | Young Serif 400 | 1.5 to 1.625 rem / 1.2 | 24 to 26 px |
| Body | Hanken Grotesk 400 | 1.0625 rem (17 px) / 1.65 | Hero lead 18 px |
| Small | Hanken Grotesk 400 | 0.875 rem / 22 px | |
| Label (eyebrow) | Hanken Grotesk 600 | 0.75 rem, uppercase, +0.24 em (hero +0.3 em) | Cedar on light, Gold on Forest |
| Buttons, nav | Hanken Grotesk 500/600 | 15 to 16 px | |

Young Serif has **one weight and no italic**. Headings therefore go from weight 600 to 400, `core/quote` loses its italic, and `font-synthesis` is turned off for the serif so the browser never fakes bold or italic. h4 to h6 stay in Hanken Grotesk (600). Fallbacks as in the canvas: `Georgia, serif` and `"Helvetica Neue", Arial, sans-serif`.

### Shape and spacing

- Buttons: radius 3 px, height 46 to 50 px (padding about 0.875 rem × 1.625 rem), weight 600. Outline buttons 1.5 px border.
- Cards and image frames: square corners, **no shadow** on the web (the shadows in the canvas belong to the product mock-ups). The `soft` shadow preset goes away.
- Layout: content 1200 px (unchanged), section padding 72 to 80 px (spacing preset `70`), 24 px grid gaps, 36 px between a section heading and its grid. Root gutter stays 16 px on phones and grows to 32 px.
- Header: Forest with a Gold hairline at 35 % opacity (`rgba(193, 144, 79, 0.35)`), logo 56 px high; footer logo 48 px.
- Links: Cedar, no underline in navigation and "Shop the collection →" style links; body-copy links stay underlined (WCAG 1.4.1, link not identified by colour alone).

## 2. How it maps onto WordPress (no plugin, no page builder)

1. **`theme.json`**: palette above; `fontFamilies` `young-serif` and `hanken-grotesk` with `fontFace` entries pointing at `file:./assets/fonts/*.woff2` (`fontDisplay: swap`); remove the two system stacks; new size scale (`x-small` label, `small`, `medium` 17 px, `large`, `x-large`, `xx-large` H2, `xxx-large` H1); element styles for link, button (Cedar/white, hover Forest/Ash), outline button (Ink, hover Ink fill), headings, caption; block styles for navigation, site title, post title, quote, pullquote, search, separator and the WooCommerce blocks already listed there (product button, price, sale badge, breadcrumbs, mini cart).
2. **Fonts, self-hosted** (rule: no external requests; ADR to record it): Young Serif 400 latin and Hanken Grotesk variable (wght 400 to 600 used) latin from `@fontsource/young-serif` 5.3.0 and `@fontsource-variable/hanken-grotesk` 5.3.0 (both OFL-1.1, checked on npm 2026-11-03). Vendor the woff2 files once into `assets/fonts/` with `OFL.txt`; no npm runtime dependency. Record versions in `docs/versions.md`. File sizes are measured in PR 1 (latin subsets are typically a few tens of KB each). `functions.php` prints one `<link rel="preload">` for Young Serif (the H1 is the LCP element on the home and coming-soon pages).
3. **Section style "Forest"** (`styles/sections/forest.json` with `"slug": "forest"`, a block style variation with `blockTypes` `core/group` and `core/columns` (not `core/cover`: a Cover block has its own overlay colour, so a dark cover is made with its overlay set to Forest); core finds it because `WP_Theme_JSON_Resolver::get_style_variations()` walks `styles/` with a `RecursiveDirectoryIterator`, and `wp_register_block_style_variations_from_theme_json_partials()` registers it as `is-style-forest`, both checked in WordPress 7.1.4): Forest background, Ash text, Ash headings, Gold-on-hover links, Gold button with Ink text, Ash outline button, Ash navigation links, Mist captions. Header part, hero, footer, coming-soon template and the gift-card style panels use it, so the dark treatment is one class (`is-style-forest`) the manager can also pick in the editor.
4. **`style.css`** (only what theme.json cannot express): focus ring colour as a custom property, Ink by default and Gold inside `.is-style-forest` (5.15:1); the same switch for the form error colour (`--cigarholic-error`: `#9b1c1c` on light, `#F2A08A` inside `.is-style-forest`, used by the signup and contact block CSS) and the eyebrow colour; `font-synthesis: none` on the serif.
5. **Block styles in `functions.php`**: `core/paragraph` → **Eyebrow** (label style); `core/group` → **Card** restyled (Ash tint fill, 1 px Line border so it stays visible on Ash tint sections, no shadow, square corners) for the trust bar and order-received next steps. The category and gift grids drop the card frame and follow the canvas: image, serif title, Stone text, Cedar "Shop the collection →" link.
6. **Image placeholders**: recolour `placeholder-product.svg` and `placeholder-workshop.svg` to Photo sand with a Stone caption, so pages read as the canvas does until photos arrive (B9).
7. **Logo and icon** (when the files arrive): a new idempotent provisioning step (`25-brand.php`) imports the gold lockup (WebP/PNG at 2× of 56 px, a few KB, not the 282 KB canvas PNG) as `site_logo`, the monogram (512 × 512 on Forest) as `site_icon`, and sets `woocommerce_email_header_image`; it compares a file hash so re-runs change nothing. `core/site-logo` stays in the header so the manager can swap it. SVG masters live in `brand/`; SVG is not uploaded to the media library (it would need an upload allowance or a plugin).

## 3. Surfaces, in the order they ship

| # | Surface | Change |
|---|---|---|
| 1 | Coming soon (`templates/coming-soon.html`, `patterns/coming-soon-content.php`) | Whole page on Forest: logo, Young Serif H1, Mist lead, signup form with Gold button and Ash input, Mist footer text. This is the only page production visitors see today, so it is the first visible result |
| 2 | Header (`parts/header.html`, NAV-01) | Forest, Gold hairline, logo only (site title kept for screen readers or dropped if the lockup carries the name), Ash nav at 15 px/500, Ash search/account/cart icons at 44 px targets, mobile overlay set to Forest/Ash (`overlayBackgroundColor`, `overlayTextColor`). Announcement bar keeps `contrast` (Ink) with `base` (Ash) text, which the re-pointed slugs give for free |
| 3 | Footer (`parts/footer.html`, NAV-03) | Forest; column headings as Gold eyebrows; links Ash; legal and address text Mist; signup form in the Forest style; the logo at 48 px; keep every NAV-03 item (addresses, social, payment methods, "we do not sell cigars or tobacco products") |
| 4 | Homepage (`patterns/hero.php` and the others, NAV-04) | Hero on Forest in the canvas layout (eyebrow, H1, lead, Gold primary + Ash outline buttons, 420 px photo panel on Forest raised); category grid in the canvas card layout with a right-aligned "All collections →" link; best sellers, craftsmanship, trust bar, newsletter on Ash with Card fills |
| 5 | WooCommerce surfaces | Product page, archive, category, cart, checkout, mini-cart drawer, my account, order confirmation: buttons, inputs (Ash tint fill, Stone border), price, badges, notices. WooCommerce block CSS hard-codes some whites; fix what the screenshots show, through theme.json first and `style.css` only when needed |
| 6 | cigarholic-core block CSS | Signup and contact error colour through the custom property; announcement bar and spec table already read the presets; the Prop 65 notice keeps its warning yellow |
| 7 | Emails (`provision/steps/70-emails.php`, ORD-03) | Header colour Forest, background Ash, body Ash tint, text Ink, footer Stone; header image once the logo exists. The step's slug mapping changes, because re-pointing the slugs alone would give Cedar, Sand and Ash through today's `accent`, `neutral-1` and `base`: `woocommerce_email_base_color` ← `forest`, `_background_color` ← `base`, `_body_background_color` ← `neutral-1`, `_text_color` ← `contrast`, `_footer_text_color` ← `neutral-2`, with the hard-coded fallback array updated to the new hex values. `ProvisionTest` asserts all five options |

### Deliberately not taken from the canvas

- **"Engraving" in the menu and "Personalize with engraving" / "Add engraving" buttons:** CAT-13 is P1 and not built; promising it now would break CMP-05 ("must match reality"). The second hero button stays "See how they are made".
- **"Gifts for the occasion" (Father's Day, Corporate gifts, Groomsmen):** CAT-14/NAV-12 are P1/P2. The section layout ships as a pattern, linked to the existing `gift-sets` collection until those collections exist.
- **Copy changes** ("built to be handed down", "Handcrafted humidors" as the only eyebrow): the hero keeps "Handcrafted in Vietnam, shipped from Texas" (CMP-02 origin wording). Copy edits are the owner's call, not part of the style work.
- **Product-mock-up effects** (wood grain, engraving shadows, the box seal): print and packaging applications, not web UI.
- The menu itself (provisioning step 30) is content and stays as it is.

## 4. Pull requests

One PR per primary feature ID; where a change necessarily touches other IDs, the PR is named by the primary one and lists the rest (AGENTS.md, "How work flows" 1). Each is squash-merged after green CI and deploys staging; production only from the owner's tag.

1. **`feat(CNT-01): brand tokens, self-hosted fonts and the Forest section style`**, also CNT-04, CMP-04, ORD-03: theme.json, fonts + OFL, `styles/sections/forest.json`, `style.css`, block styles, placeholders, coming-soon restyle, email colours + test, ADR 0009 (brand system and self-hosted fonts), theme README token table, `docs/versions.md`, `docs/manager-guide.md` (which colours go where: Gold only on Forest, Cedar only on light), `CHANGELOG.md`, `docs/STATUS.md`. Worth a `v0.2.0` tag because it changes the live coming-soon page.
2. **`feat(NAV-01): Forest header and footer`**, also NAV-03: done (2026-11-03). The search toggle is a plain 44 px icon like account and cart, and on phones the menu button sits last in one row.
3. **`feat(NAV-04): homepage patterns in the brand layout`**.
4. **`fix(CNT-01): WooCommerce surfaces in the brand style`** (product, archive, cart, checkout, mini cart, account, order received).
5. **`feat(CNT-01): logo, site icon and email header from the brand files`**: done second (2026-11-03), from the owner's raster artwork; provisioning step 25 imports the logo, the site icon and the email logo.

## 5. Verification for every PR

- `composer check`, `npm run lint` (stylelint covers `style.css`), the balanced-block-comments check from the theme README, `make test`, `make e2e`.
- New Playwright spec `tests/e2e/brand.spec.js`: no request leaves for `fonts.googleapis.com` or `fonts.gstatic.com`; the Young Serif and Hanken Grotesk woff2 files answer 200 from the theme's `assets/fonts/`; after `document.fonts.ready`, `document.fonts` holds a Young Serif face with `status === 'loaded'` (`document.fonts.check()` alone is not enough: it returns true when no face matches at all); in the Chromium project, `CSS.getPlatformFontsForNode` over a CDP session reports Young Serif as the font actually rendering the H1, so a silent fallback to Georgia fails the test; the header and footer backgrounds compute to Forest; the focus ring on a Forest link computes to Gold; a form error inside Forest computes to `#F2A08A`.
- A contrast unit test (`tests/phpunit/ThemeContrastTest.php`) that reads `theme.json` **and every `styles/**/*.json` partial**, resolves `var:preset|color|…` to hex, and asserts text/background, link/background and button text/button background in each context it declares (root, elements, blocks, and each section variation with its nested elements and blocks) at 4.5:1. It therefore fails if someone pairs Cedar with Forest in `forest.json`. The email colour pairs from step 70 are asserted in the same test.
- Screenshots at 390 px and 1440 px of coming soon, home, a category, a product, cart, checkout and order received (admin session, since anonymous visitors get coming soon), attached to the PR.
- Keyboard pass: skip link, focus visible on light and Forest, mobile menu opens and closes; Lighthouse mobile on the home and coming-soon pages: accessibility 100, LCP and CLS in the "good" band with the font preload.
- Staging after merge, production after the owner's tag; check the coming-soon page from outside for the same assertions.

## 6. Owner inputs (to add to OWNER-ACTIONS B9)

1. **Logo, vector master:** the raster artwork arrived on 2026-11-03 and is in use (`brand/`). Still wanted: the wordmark as a vector file (SVG, AI or PDF) for print, packaging and large banners.
2. **Confirm the canvas is final** (colours, fonts, the Forest-heavy homepage), so CNT-01 can close B9 for everything except photos and catalog content.
3. **Copy:** keep the current hero copy or switch to the canvas lines; either is a one-line change later.

## 7. Risks

- Font swap shifts layout (CLS): mitigated by the preload and by fallback stacks with similar metrics; measured with Lighthouse in PR 1.
- WooCommerce block styles override some theme colours (drawer and checkout backgrounds); found by the screenshot pass in PR 4.
- Site Editor customisations would shadow theme files: none exist today (provisioning writes no `wp_template` or `wp_template_part` posts); re-check with `wp post list --post_type=wp_template_part` on staging and production before PR 2 ships.
- The manager could combine Gold or Cedar badly in the editor: the manager guide gets the two rules, and custom colours stay available but the palette names say where each colour belongs.

# ADR 0009: Brand system in theme tokens, self-hosted fonts

Status: accepted, 2026-11-03. Feature IDs: CNT-01, CMP-04, ORD-03. Supersedes the placeholder tokens of D-01 (OWNER-ACTIONS).

## Context

The owner's design canvas "Cigarholic Brand" (claude.ai artifact, boards *Logo suite*, *Color & type*, *Applications*, *Homepage hero*) settled the brand: Forest `#1F2B25`, Cedar `#9C5034`, Gold `#C1904F`, Ash `#EEE9E0` with a `#F7F4EF` tint, Ink `#1A1815`; Young Serif for display and headings, Hanken Grotesk for body and interface. The theme had neutral placeholders and system font stacks, and the rules say no external requests, WCAG 2.2 AA from the first template, and no page builder. The plan is `docs/plans/CNT-01-brand-style.md`.

## Decision

- **Tokens stay semantic.** The existing palette slugs keep their roles and change value (`base` Ash, `neutral-1` Ash tint, `neutral-2` Stone, `contrast` Ink, `accent` Cedar); new roles get new slugs (`forest`, `forest-2`, `mist`, `gold`, `line`). Templates, patterns and the cigarholic-core block CSS keep working without a rename. The canvas's board colour Sand `#E3DCD0` is not a token: Cedar links on it are 4.27:1.
- **Contrast rules are part of the system.** On light grounds (Ash, Ash tint): Ink, Stone, Cedar. On Forest: Ash, Mist, Gold. Gold never as text on light (2.36:1), Cedar never on Forest (2.52:1), Line only for decoration. `ThemeContrastTest` checks every declared pair and this matrix; `tests/e2e/brand.spec.js` checks every visible text on rendered pages.
- **Dark sections are one block style.** `styles/sections/forest.json` (slug `forest`, groups and columns) carries the Forest colours, Gold buttons and Ash links; `style.css` switches the focus ring, form error and eyebrow colours with it through custom properties (`--cigarholic-focus`, `--cigarholic-error`, `--cigarholic-eyebrow`). The manager can pick "Forest" in the editor.
- **Fonts are self-hosted.** Young Serif 400 and Hanken Grotesk variable (100 to 900), latin subsets, vendored once from Fontsource 5.3.0 (OFL-1.1, licences next to the files) into `web/app/themes/cigarholic/assets/fonts/`, declared as theme.json `fontFace` with `font-display: swap`, both preloaded. No request leaves for Google Fonts; no npm runtime dependency.
- **Young Serif has one weight and no italic**, so serif headings are weight 400 and `font-synthesis` is off for them; quotes are upright.

## Consequences

- Changing a brand colour is a theme.json edit plus the contrast test; adding a background colour means adding it to the test's rule matrix first.
- Email colours follow the palette through provisioning step 70 (Forest header, Ash outer, Ash tint body), so a deploy updates staging and production emails.
- Other scripts (Latin Extended, Vietnamese) fall back per glyph to the system stacks; add subsets if product copy or customer names need them in the brand fonts.
- Font updates are manual: replace the files, update `docs/versions.md`, check the preload URLs still match.

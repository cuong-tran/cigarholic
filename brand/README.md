# Brand assets (CNT-01, OWNER-ACTIONS B9)

Masters for the Cigarholic logo. The web copies the store uses live in the theme (`web/app/themes/cigarholic/assets/brand/`) and reach the site through provisioning step `provision/steps/25-brand.php`. Colours and type are in `theme.json`; the rules are in ADR 0009.

| File | What | Size |
|---|---|---|
| `cigarholic-wordmark.png` | The "Cigarholic" wordmark, gold, transparent background | 772 × 296 px, RGBA |
| `cigarholic-monogram.png` | The "C" of the wordmark on its own, transparent | 202 × 293 px, RGBA |

## Where they came from

The owner supplied the artwork on 2026-11-03 as an 832 × 1248 px raster: the wordmark above "by MaX2lab" and "www.max2lab.com", on a transparency checkerboard that was part of the pixels (no alpha channel). As asked, only the wordmark is kept; the MaX2lab lines are not in the repository.

How the masters were made, so the result can be judged and redone from a better source:

1. Rows 453–736 of the source hold the wordmark; the MaX2lab lines start at row 747.
2. The checkerboard (white and grey 226, cells about 11.6 px) was modelled per pixel. Gold and dark-brown lettering (chroma ≥ 40 or darker than 165) is opaque; plain checkerboard pixels are transparent; anti-aliased edge pixels get their alpha by unmixing against the nearest solid gold colour, so the edges stay soft without a white fringe on dark grounds. Specks under 40 px were dropped.
3. The C was separated from the "i" by connected shapes, not by a straight cut, because the "i" sits inside the C's columns.

## Web copies (in the theme)

| File | Use |
|---|---|
| `cigarholic-logo.webp` | Site logo (header, coming-soon page), imported into the media library as `site_logo`; 53 KB, WordPress makes the smaller sizes |
| `cigarholic-icon.png` | Site icon (favicon, app icons): the C on Forest `#1F2B25`, 512 × 512 px, imported as `site_icon` |
| `cigarholic-email-logo.png` | Email header logo, 400 px wide for 200 px display, 256-colour PNG (Outlook does not show WebP), served from the theme |

To change the logo: replace the master here and the web copies in the theme, commit, deploy. Provisioning sees the new file (by SHA-256) and imports it as a new attachment; the old one stays in the media library.

## Still wanted from the owner

A vector master (SVG, AI or PDF) of the wordmark. The raster is 772 px wide, which is enough for the header, the coming-soon page and emails, but not for print, packaging or large banners.

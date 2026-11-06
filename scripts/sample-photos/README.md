# Sample photos

Renders the stand-in photos the store shows until the owner's photography arrives (OWNER-ACTIONS B9). Feature IDs CNT-01, NAV-04, FND-02.

Every image is made from code: three.js in headless Chromium, no downloaded textures, models or stock photos. Wood grain (walnut, mahogany, white oak, acacia, cherry, Spanish cedar, beech, pine) comes from value noise. The hygrometer dial, the LCD and the care card are drawn on canvases in the brand fonts. Lacquer, oil, brass, steel, glass and felt are physical materials lit by softboxes. Each image averages 48 frames with a jittered area light, sky light, sub-pixel offset and lens position: soft shadows, anti-aliasing and depth of field without a path tracer. GTAO adds contact shadows.

No cigars, tobacco or smoking appear in any shot (AGENTS.md).

## Output

| Shot | File | Size |
|---|---|---|
| `hero` | `web/app/themes/cigarholic/assets/images/sample-hero.webp` | 1440 × 1080 |
| `category-<slug>` (six) | `web/app/themes/cigarholic/assets/images/sample-category-<slug>.webp` | 1200 × 800 |
| `workshop` | `web/app/themes/cigarholic/assets/images/sample-workshop.webp` | 1400 × 1050 |
| `product-<slug>` (seven) | `web/app/mu-plugins/cigarholic-core/assets/sample-products/<slug>.jpg` | 1200 × 1200, marked "Sample image" |

The theme patterns `hero`, `category-grid` and `craftsmanship-story` show the WebP files. `wp cigarholic seed` attaches the JPEGs to the `SAMPLE-` products and uploads a photo again only when its file changed.

## Run

```bash
npm ci
node scripts/sample-photos/render.mjs                                   # every shot, into the repo (about 25 min in the sandbox)
node scripts/sample-photos/render.mjs --only hero,workshop --frames 8 --out /tmp/preview   # quick preview
```

`render.mjs` serves this folder, `node_modules/three` and the theme fonts on a local port and drives Chromium with SwiftShader (software WebGL), so it needs no GPU. Fewer frames give noisier shadows and depth of field.

## Files

- `lib.js`: noise, wood textures, materials, the humidor, travel humidor, hygrometer, humidifier and cutter models, the sweep backdrop, the `Studio` renderer and the photographic finish (vignette, grain, label).
- `studio.js`: the shots (scene, lights, camera) and `window.renderShot()`.
- `studio.html`: import map and fonts.
- `render.mjs`: the command line.

## Replacing them

When real photos arrive, overwrite the files above with photos of the same subject and aspect ratio, update the alt texts in the patterns and in `Catalog\Seed::products()`, and delete the shot here if it is no longer needed.

# cigarholic-core

All custom PHP of the store, one class per feature ID from `docs/FEATURES.md`. Loaded by `web/app/mu-plugins/cigarholic-core.php`; classes autoload through Composer (`Cigarholic\Core\` → `src/`).

| Namespace | Feature IDs | What |
|---|---|---|
| `Plugin` | FND-05 | Boot, HPOS and block-checkout compatibility declaration, text domain, WP-CLI registration |
| `Features`, `Feature` | FND-05 | Registry of features with toggles: default in code, override from `CIGARHOLIC_FEATURES` in `.env` (`signups:on,engraving:off`), filter `cigarholic_feature_enabled` |
| `Logger` | FND-05 | `wc_get_logger()` with source `cigarholic` |
| `Cli\Commands` | FND-05, FND-03 | `wp cigarholic version`, `features`, `provision [--dry-run] [--step=<name>]` |
| `Provision\Runner`, `Provision\Context` | FND-03 | Runs `provision/steps/*.php`; helpers that compare before writing |
| `Dev\Development` | FND-02 | Development only: valid `wp_mail()` sender, HTTPS verified against the system CA store |
| `Security\Hardening` | OPS-07 | XML-RPC off, no generator tags, generic login errors, no anonymous `/wp/v2/users` |
| `Security\Turnstile` | CNT-04, CNT-03 | Cloudflare Turnstile widget and server-side verification (keys from `.env`; `cigarholic_turnstile_verified` filter short-circuits it in tests) |
| `Signups\Signups`, `Repository`, `Cli` | CNT-04, MKT-01 | `cigarholic/signup-form` block, `POST /wp-json/cigarholic/v1/signups`, admin-post fallback, honeypot and rate limit, own table, WooCommerce > Signups with CSV, `wp cigarholic signups ...` |
| `Storefront\AnnouncementBar` | NAV-02 | WooCommerce > Announcement bar settings and the `cigarholic/announcement-bar` block |
| `Contact\ContactForm` | CNT-03 | `cigarholic/contact-form` block, `POST /wp-json/cigarholic/v1/contact`, admin-post fallback, Turnstile, honeypot, rate limit, email to the support inbox (WooCommerce > Contact form) |

## Blocks

Dynamic blocks live in `blocks/<name>/` with a `block.json` (`render: file:./render.php`), a plain-JavaScript `editor.js` registered with `ServerSideRender` (dependencies in `editor.asset.php`, no build step), optional `view.js` and `style.css`. The theme references them by name.

## Adding a feature

1. Create `src/<Area>/<Name>.php` implementing `Feature` (`id()` returns the feature ID, `register()` hooks everything). The class docblock names the ID.
2. Add it to `Features::REGISTRY` with its toggle slug and default. Unfinished work defaults to `false`.
3. Settings it needs go in a `provision/steps/NN-name.php` step. Tests go in `tests/phpunit/`.
4. Rules: WPCS, PHPStan level 6, 8.3-compatible syntax, i18n with text domain `cigarholic`, WooCommerce CRUD only, idempotent side effects through Action Scheduler.

# cigarholic-core

All custom PHP of the store, one class per feature ID from `docs/FEATURES.md`. Loaded by `web/app/mu-plugins/cigarholic-core.php`; classes autoload through Composer (`Cigarholic\Core\` → `src/`).

| Namespace | Feature IDs | What |
|---|---|---|
| `Plugin` | FND-05 | Boot, HPOS and block-checkout compatibility declaration, text domain, WP-CLI registration |
| `Features`, `Feature` | FND-05 | Registry of features with toggles: default in code, override from `CIGARHOLIC_FEATURES` in `.env` (`signups:on,engraving:off`), filter `cigarholic_feature_enabled` |
| `Logger` | FND-05 | `wc_get_logger()` with source `cigarholic` |
| `Cli\Commands` | FND-05, FND-03 | `wp cigarholic version`, `features`, `provision [--dry-run] [--step=<name>]` |
| `Provision\Runner`, `Provision\Context` | FND-03 | Runs `provision/steps/*.php`; helpers that compare before writing: `option()`, `page()` (create once, never overwrite), `term()`, `navigation()` (`wp_navigation` menu updated in place), `changed()` / `log()` for custom checks |
| `Dev\Development` | FND-02 | Development only: valid `wp_mail()` sender, HTTPS verified against the system CA store |
| `Security\Hardening` | OPS-07 | XML-RPC off, no generator tags, generic login errors, no anonymous `/wp/v2/users` |

## Adding a feature

1. Create `src/<Area>/<Name>.php` implementing `Feature` (`id()` returns the feature ID, `register()` hooks everything). The class docblock names the ID.
2. Add it to `Features::REGISTRY` with its toggle slug and default. Unfinished work defaults to `false`.
3. Settings it needs go in a `provision/steps/NN-name.php` step. Tests go in `tests/phpunit/`.
4. Rules: WPCS, PHPStan level 6, 8.3-compatible syntax, i18n with text domain `cigarholic`, WooCommerce CRUD only, idempotent side effects through Action Scheduler.

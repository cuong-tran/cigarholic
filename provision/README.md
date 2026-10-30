# Provisioning: settings as code (FND-03)

`wp cigarholic provision` runs every file in `steps/` in name order. A step is a PHP file that returns a callable receiving a `Cigarholic\Core\Provision\Context`; it compares before it writes, so running it again changes nothing. `--dry-run` reports what would change, `--step=00-core` runs one step.

The deploy workflow runs provisioning after every release; `scripts/runtime-up.sh` runs it in the Compose runtime. Settings changed in WP admin by hand are overwritten on the next run: change the step instead.

| Step | Feature IDs | Content |
|---|---|---|
| `00-core` | FND-03, SEO-03 | Site title, tagline, timezone (Florida HQ), formats, registration off, comments closed by default, permalink structure `/%postname%/` |

Steps for WooCommerce settings, pages, menus, roles, shipping, tax, emails and coming-soon mode follow in the FND-03 PR.

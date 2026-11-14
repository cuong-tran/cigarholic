## Feature IDs

<!-- Primary ID in the title (`feat(CAT-04): ...`); list every ID this PR touches, from docs/FEATURES.md. -->

- 

## What changed

<!-- Short, concrete. Link the ADR if a decision was made. -->

## Plugins

<!-- Any plugin added, removed or upgraded: its row in docs/plugins.md, the justification, HPOS compatibility, the new active count (budget 15). Write "none" otherwise. -->

none

## Test notes

<!-- Keep the four groups; never claim more than you ran. -->

- **Executed and passed:**
- **Executed and failed:**
- **Could not run here (and why):**
- **Not implemented / pending behind a toggle:**

## Screenshots

<!-- Required for anything visible (storefront, admin, emails). "n/a" for non-visual changes. -->

## Checklist

- [ ] `composer check` and the tests pass locally; CI is green
- [ ] Provisioning updated if a setting changed (`provision/`), and `wp cigarholic provision` re-run cleanly
- [ ] `CHANGELOG.md`, `docs/STATUS.md` and the package's row in `docs/PLAN.md` updated; `docs/versions.md` / `docs/plugins.md` / `docs/manager-guide.md` / `docs/TESTING.md` where relevant
- [ ] The new test fails without the change (checked by hand); `code-review` and `security-review` ran, findings fixed or listed above
- [ ] Unfinished parts are behind a feature toggle and listed above
- [ ] No secrets, dumps, uploads or generated files in the diff; nothing confidential (the repository is public)
- [ ] Custom code touching orders is idempotent and uses WooCommerce CRUD only (HPOS)

# Status

Where the Cigarholic build is today. A fresh agent reads `CLAUDE.md` (which imports `AGENTS.md`), this file, then `docs/HANDOVER.md`. Kept short on purpose: history lives in `docs/history/`, the work in `docs/PLAN.md`, the owner's list in `docs/OWNER-ACTIONS.md`.

Last updated: 2026-11-13, Turn 12: **project revision.** Every document was read and the code, infrastructure and tests audited; the architecture stays (ADR 0010); the documents were restructured and 22 features added. No code, configuration or server changed in this turn.

## Where we are

| | |
|---|---|
| Milestone | **M1 "Coming soon live" reached 2026-11-03.** Next: **M1b "Safe to keep live"** (backups, monitoring, mail, form protection, scheduled jobs, approval gaps), then M2 "Store works on staging" (waits for payment test credentials). `docs/ROADMAP.md` |
| Production | `https://cigarholic.com`, release 6, tag **`v0.4.0`** (`19533a9`, 2026-11-06). Coming-soon page with email signup for every visitor; WordPress 7.1.4, WooCommerce 11.2.2, 11 plugins active, HPOS on, object cache on, two-factor required for administrators and shop managers. No products. `www`, `max2lab.com` and `http://` redirect to the apex; Let's Encrypt certificate to 2027-01-31 |
| Staging | `https://staging.cigarholic.com`, `main` at #75 (`19325f3`, deploy run 42218595711 on 2026-11-06). Everything on production plus tracking numbers and the shipping email (SHP-04), the sample photos, and two-factor not required. Basic auth, `noindex`, coming soon on, seven `SAMPLE-` products |
| Waiting for a tag | **`v0.5.0`** (OWNER-ACTIONS A11): SHP-04 and the sample photos to production. Not urgent |
| In progress | Nothing in flight. First package: `docs/PLAN.md` WP-01 |
| Open PRs | Dependabot #76 (npm tooling, CI red: stylelint peer conflict), #77 (WooCommerce stubs, green), #79 (WooCommerce 11.3.0 with Stripe and PayPal updates, CI red: five shipping-email tests; an `x.0` release, not to be merged). Handled by WP-02 |
| Blocked on the owner | B2 backups, B3 monitoring, B1 Postmark, B6 Turnstile (all four are M1b); B4 and B5 payment test credentials (M2); B9 and B12 catalog; A12 repository protection; B14 where the stock is |

## Risks that are open on the live site

Found by the review of 2026-11-13 (`docs/ARCHITECTURE.md` section 7). None has caused harm that we know of; each has a work package.

1. **The owner's production approval can be bypassed** by a WP-CLI workflow run against staging, and three workflows act on production without it (WP-01). Until fixed, agents run `wp-cli.yml` with plain WP-CLI arguments only and never dispatch `infra-provision.yml` actions on production without the owner saying so in the session.
2. **No backup leaves the server and the nightly backup fails every night** for lack of credentials; only pre-deploy database dumps exist, on the same disk (WP-07, B2).
3. **Nothing watches the site** (B3).
4. **Scheduled jobs are not driven by anything** but chance page views (WP-03).
5. **Mail cannot be delivered**, and supplying the Postmark token alone will not change that (WP-06, B1). A contact message would be lost; the contact page is still a draft, but its REST route is live.
6. **The public signup and contact routes can be flooded**: the rate limit trusts a header the client sets, and Turnstile is not configured (WP-05, B6).

## Tests, latest totals

On `main` (CI run of #75, and Turn 10 in the sandbox): `composer check` and `npm run lint` clean; PHPUnit **123 tests, 1,453 assertions**; Playwright **35 of 35**; infrastructure tests green. Turn 12 changed documents only; what was run is in `docs/history/test-log.md`. What the suite does not cover is in `docs/TESTING.md` section 5: of the twelve required matrix rows, one is covered, eight are partial, three are not covered.

## What changed in Turn 12

- New: `docs/ARCHITECTURE.md`, `docs/ROADMAP.md`, `docs/PLAN.md`, `docs/CHECKLIST.md`, `docs/TESTING.md`, `docs/DECISIONS.md`, `docs/HANDOVER.md`, `docs/adr/0010-architecture-review-2026-11.md`, `docs/history/` (merged PRs, server runs and the 2026-11-02 incident, test log, environment report; moved from this file unchanged).
- Revised: this file (from 72 KB to a page), `README.md`, `AGENTS.md`, `CLAUDE.md`, `docs/FEATURES.md` (22 features, D-11, scope change log), `docs/OWNER-ACTIONS.md` (A12, B14, B15, order of the B items), `docs/LAUNCH-PLAN.md` and `docs/KICKOFF-BRIEF.md` (a note on what changed since), runbook pointers, the PR template, `CHANGELOG.md`.

## Next

`docs/PLAN.md`, stage 1, in order: WP-01 (approval gaps), WP-02 (dependency PRs and WooCommerce 11.3), WP-03 (scheduled jobs), WP-04 (repository guard), WP-05 (forms), WP-06 (mail wiring), WP-07 (backups). Owner items B1, B2, B3 and B6 jump the queue when they arrive.

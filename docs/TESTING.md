# Testing

How Cigarholic is tested, what the tests cover today, what they do not, and the order in which the gaps close. Written from a read of every test file on 2026-11-13 (project revision); the per-session results are in `docs/history/test-log.md`.

Last updated: 2026-11-13.

## 1. Rules

1. **Say only what you ran.** Every report, PR and status update separates four groups: executed and passed / executed and failed / could not run here (and why) / not implemented.
2. **Never weaken a test, skip a check or switch off a security control to get green.** A test that is wrong is fixed in its own commit with the reason; a test that is flaky is fixed or deleted, never retried into passing.
3. **A new feature ships with a test that fails without it.** Check it once by hand: remove the hook or the line, watch the test fail, put it back (the "mutation check" in the test log). A test that passes with the feature removed is not a test.
4. **Test through the real thing.** PHPUnit runs against a real WordPress and WooCommerce, through WooCommerce's own REST routes and email classes where the feature lives there. Playwright drives the real pages of the Compose stack. No mocks of WooCommerce.
5. **Payment truth is tested at the webhook.** A payment test asserts the order state after the gateway's webhook, never after the browser redirect, and always includes a duplicate delivery.
6. **Sample data is a fixture, not a dependency.** A test that needs the seven `SAMPLE-` products says so, and CI fails if the seed is missing (see TD-01) instead of skipping.

## 2. Layers

| Layer | Tool | Where it runs | What it proves | Command |
|---|---|---|---|---|
| Static | parallel-lint, PHPCS (WordPress-Extra, Docs, PHPCompatibilityWP 8.3+), PHPStan level 6 with no baseline and no ignored errors, `composer validate`, `composer audit` | Host PHP, CI on PHP 8.3 and 8.5 | The code parses on both PHP versions, follows the standard and type-checks | `composer check` |
| Static (front end, shell, workflows) | eslint, stylelint, shellcheck, actionlint | Host, CI | Block and theme JS/CSS, every script and workflow lint clean | `npm run lint`, CI job "Shell and workflow lint" |
| Infrastructure | Shell tests in `tests/infra/` (the shipped Nginx rules loaded into a real Nginx, the secret generator, the status report) | Host with Docker, CI | Staging basic auth and its webhook exemptions, hard denies, routing of virtual files; secrets are alphanumeric; the installer-request counter | `tests/infra/nginx-staging-auth.sh` and the two others |
| Integration | PHPUnit 9.6 inside the PHP container, WordPress test suite, WooCommerce, Two Factor and SEOPress loaded | Compose stack, CI runtime job (PHP 8.5 on PRs, 8.5 and 8.4 on `main`) | `cigarholic-core` classes, provisioning (apply, then no change), blocks' PHP rendering, emails | `make test` |
| End to end | Playwright 1.63, projects `chromium` (all specs) and `mobile` (Pixel 7: coming soon and storefront) | Compose stack, CI runtime job | What a visitor, a buyer and the manager see in a browser, including mail in Mailpit | `make e2e` (`ARGS="--project=chromium"` to narrow) |
| Deploy check | Deployer health check: one request for the home page on the server, rollback on failure | Every staging and production deploy | The release boots | part of the deploy |
| Outside checks | `curl` from the sandbox against production | After each production tag | Redirect hosts, security headers, robots, sitemaps, denied URLs, coming soon | `docs/HANDOVER.md`, "Check production from outside" |
| Manual | Owner and manager on staging and production | Before go-live and after each release that changes what they use | Real devices, real wallets, real inboxes, the handbook | `docs/CHECKLIST.md` |

Numbers on `main` today: **PHPUnit 123 tests** (21 files, 104 methods, 1,453 assertions), **Playwright 35 runs** (30 cases in 8 specs; 5 of them also on mobile), **3 infrastructure test scripts** (the Nginx one has 69 cases).

## 3. Running the tests

```bash
scripts/tools.sh && COMPOSER_ALLOW_SUPERUSER=1 composer install && npm ci
```

```bash
make up
```

```bash
composer check && npm run lint
```

```bash
make test
```

```bash
make e2e
```

One PHPUnit file or one Playwright spec:

```bash
docker compose exec -T php tools/phpunit.phar --filter ShippingTrackingTest
```

```bash
make e2e ARGS="tests/e2e/tracking.spec.js --project=chromium"
```

Against another host (staging), the Playwright config reads `E2E_BASE_URL` and `E2E_BASIC_AUTH`; no workflow sets them yet (TD-03).

## 4. What CI runs

`.github/workflows/ci.yml`, on every PR and on every push to `main`:

| Job | Runs | Notes |
|---|---|---|
| PHP 8.3 and PHP 8.5 | `composer validate`, lint, PHPCS, PHPStan, `composer audit` | No tests in this job |
| JS and CSS lint | eslint, stylelint | |
| Shell and workflow lint | shellcheck, the three `tests/infra` scripts, actionlint | |
| Runtime | Builds the Compose stack, installs WordPress, provisions, seeds, runs PHPUnit, then every Playwright project | PHP 8.5.12 on PRs; 8.4.19 is added on pushes to `main` (the fallback of the PHP gate, ADR 0001). One worker, one retry, 60 s per test, 40-minute job limit. On failure the Playwright report, traces, screenshots and container logs are kept for 7 days |

Not in CI today: secret scanning (FND-17), accessibility checks with axe (CMP-04), a performance budget (OPS-10), any test against staging after a deploy (FND-12), PHPUnit on PHP 8.3 (the code is linted for 8.3, never executed on it).

## 5. Coverage of the required test matrix

The matrix is section 9 of `docs/KICKOFF-BRIEF.md`. "Closes with" names the work package in `docs/PLAN.md`.

| # | Requirement | Today | What is missing | Closes with |
|---|---|---|---|---|
| a | Variant selection updates price, stock status and image; SKU uniqueness enforced on save | Partly | SKU scheme, duplicates and the revert of an invalid SKU are tested (`CatalogSkuTest`, `CatalogCheckTest`). No browser test selects a variation; a duplicate SKU on save and the variation validator are not tested | WP-12 |
| b | Stock decrements on order, holds during pending payment, restores on cancel or failure | Partly | A decrement is asserted only in passing (`OrderNextStepsTest`). No hold, no restore | WP-12 |
| c | Cart totals, tax and shipping zone for TX, FL, another contiguous state, and a rejected AK/HI/non-US address | Not covered | Only the settings are asserted (`ProvisionTest`). No cart total, no tax amount, no rejected address. Tax amounts need Stripe Tax (B4) | Zones and rejection: WP-12. Tax: WP-21 |
| d | Coupons: %, fixed, free shipping, expiry, usage limits | Not covered | Everything | WP-12 |
| e | Checkout validation: US-only, phone required, terms, guest and account | Partly | Settings are asserted. The end-to-end checkout runs as a logged-in administrator and ticks the terms box only "if present"; there is no guest checkout and no refusal test | WP-12 |
| f | Order status transitions; payment success, failure, pending, and a duplicate webhook that must not double-process | Partly | Transitions through shipping are tested (`ShippingTrackingTest`). No payment state is tested: there is no gateway in the sandbox | WP-20 |
| g | Refunds, full and partial, synced to the gateway | Not covered | Everything | WP-20 |
| h | A customer cannot see another customer's order; the Shop Manager cannot install plugins or edit themes or code | Partly | The role's capabilities are tested (`RolesTest`). Customer-to-customer access is not, except the order key on the order-received card | WP-12 |
| i | Spec table in both unit systems; `catalog check` flags missing data and `SAMPLE-` products on production | Covered | The command's exit code (the CLI wrapper) has no test | WP-12 |
| j | Coming soon: visitors see the page and can sign up (Turnstile verified, duplicates deduplicated); administrators see the store | Partly | The page, signup, dedupe and refusal are tested. The real Turnstile HTTP verification is always short-circuited, and the helper returns silently when the widget is absent | WP-11 |
| k | Redirect hosts to the apex with path and query, one hop, 301; HTTP to HTTPS; staging `noindex` and behind basic auth with webhook exemptions | Partly | Basic auth, exemptions and `noindex` are tested against the shipped Nginx rules. The `www` and `max2lab.com` redirects and HTTP to HTTPS are checked only by hand from outside | WP-11 |
| l | Accessibility (axe) and a Lighthouse budget on home, category, product, cart, checkout | Not covered | No axe, no Lighthouse. Substitutes exist: a contrast check on every visible text of six page types, a theme contrast test, an overflow sweep, an image-size budget | axe: WP-10. Lighthouse: WP-27 |

Two rows are fully covered or nearly so (i, and j apart from the real Turnstile call); three are not covered at all (d, g, l); the rest are partial. Rows c (tax), f and g cannot close before the payment test credentials arrive (OWNER-ACTIONS B4, B5).

## 6. Test debt

Problems in the existing tests, found by reading them. Each is a small change; none needs credentials.

| ID | Problem | Why it matters | Fix |
|---|---|---|---|
| TD-01 | Eight Playwright tests skip themselves when the store is not in coming soon or the sample catalog is missing (`coming-soon.spec.js:12`, `brand.spec.js:25,196`, `seo.spec.js:63`, `ship-time.spec.js:14`, `storefront.spec.js:14`, `tracking.spec.js:15`). Nothing asserts that CI skipped none | A broken seed or a store accidentally switched to live turns CI green with skips | In CI set a flag that turns these skips into failures, and fail the job when the report shows any skipped test other than the one Chromium-only check |
| TD-02 | The storefront checkout test selects Cash on delivery and ticks the terms box only "if present", runs as the administrator, and its spec-table check matches any table that mentions capacity | It passes with the terms box, the gateway choice or the spec table removed | Assert each is present; run the checkout as a guest and as a customer (row e) |
| TD-03 | Nothing runs against staging after a deploy | A release that boots but breaks checkout reaches staging unnoticed; FND-12 asks for the suite there | A workflow job after "Deploy staging" runs the smoke subset with `E2E_BASE_URL` and the staging basic-auth secrets |
| TD-04 | `tracking.spec.js` asserts "no Completed order email" straight after the shipping email arrives | The second email could arrive later; the test can pass falsely | Wait for the mail queue to settle (poll Mailpit for a fixed window) before asserting absence |
| TD-05 | Shared state between PHPUnit tests: `$wp_rewrite` is patched per test, `EmailNextStepsTest` changes WooCommerce's shared email objects, two tests unset `$GLOBALS['product']` in the body, provisioning imports brand files into uploads | Order-dependent failures when a test is added or the order changes | Reset in `tear_down`; one helper for the rewrite reset; delete imported files after each provisioning test |
| TD-06 | End-to-end runs leave orders, signups and reduced stock in the development database and share the administrator's cart | Repeated local runs can run a sample out of stock and change results | Create and delete a customer per run; reset sample stock in a global setup |
| TD-07 | `HardeningTest` checks only that the word "admin" is absent from the login error; the end-to-end dedupe test sees "Thank you" twice and nothing else | Weak assertions | Assert the generic message itself; assert one stored row through WP-CLI in the end-to-end test |
| TD-08 | Untested code paths: the form-post, export and settings-save handlers of `Signups`, `ContactForm` and `AnnouncementBar` (the nonce and capability branches), `TwoFactorRequired::forget`, the CLI wrappers, the blocks' `view.js` and `editor.js` | These are the paths where a missing capability check would hide | One test per handler for: no nonce, wrong capability, valid request |
| TD-09 | Theme templates never opened by a test: 404, search, journal list, journal post, category page in the contrast walk | A broken template ships unseen | Add them to the page list of the brand and accessibility specs |
| TD-10 | PHPUnit has no random order and no coverage report | Hidden order dependence; no view of untested lines | Random order with a printed seed once TD-05 is done; a coverage report as a CI artifact (no threshold yet) |

## 7. What can be tested where

| Environment | Can | Cannot |
|---|---|---|
| Sandbox and CI (Compose stack) | Everything in sections 2 and 3; cart to order to email with Cash on delivery; mail through Mailpit; Turnstile with Cloudflare's always-pass test keys | Stripe, PayPal, Stripe Tax (no keys, by rule); real inboxes; Lighthouse (not in the image; add it in CI, not in the sandbox); the VPS |
| Staging | Stripe and PayPal test mode, Stripe Tax test, webhooks through the basic-auth exemptions, the PHP 8.5 gate on the real server, a restore drill | Real money; real inboxes until Postmark exists (B1); viewing pages from the sandbox (the basic-auth credentials are secrets, so staging pages are tested from a workflow) |
| Production | Outside checks; after go-live the real-money matrix with the owner | Anything destructive; test orders with sample products |

## 8. Release testing

Every production tag (the pre-release gate, FND-16, makes this one workflow; until it exists the agent runs the list by hand and writes the result into the release notes):

1. CI green on the tagged commit, including the runtime job.
2. `composer audit` clean; no secret in the diff or the history (FND-17).
3. The staging deploy of the same commit succeeded, and a provisioning dry run on staging shows no change.
4. The staging smoke run passed (TD-03).
5. Every active plugin declares HPOS compatibility.
6. The changelog section for the release exists and lists anything untested or incomplete.

Go-live adds the real-money matrix, the device matrix and the inbox checks of `docs/CHECKLIST.md`.

## 9. Recording results

At the end of every session add a section to `docs/history/test-log.md` in the four groups of rule 1, with counts (tests, assertions, Playwright runs), run IDs for anything on the servers, and the mutation checks made. `docs/STATUS.md` carries only the latest totals and anything failing.

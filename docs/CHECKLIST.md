# Checklists

Four lists: before a pull request is merged, before a production tag, before go-live, and at the start and end of a session. A box is ticked only by someone who did the step and saw the result; write the evidence (run ID, PR, date) beside it.

Last updated: 2026-11-13 (project revision).

## 1. Every pull request

- [ ] Branch `feat/<ID>-<slug>`; the title is a Conventional Commit with the feature ID as scope.
- [ ] `composer check` and `npm run lint` pass locally.
- [ ] `make test` and `make e2e` pass on the Compose stack, or the PR says why they could not run.
- [ ] A new feature has a test that fails without it (checked once by hand).
- [ ] Provisioning updated if a setting changed, and a second `wp cigarholic provision` run reports no change.
- [ ] Unfinished parts are behind a toggle that is off, and listed as pending.
- [ ] Custom code that touches orders is idempotent and uses WooCommerce's CRUD only.
- [ ] Any new plugin has its row in `docs/plugins.md`, a justification, and the count is 15 or fewer.
- [ ] `CHANGELOG.md` has the entry; `docs/versions.md`, `docs/plugins.md`, `docs/manager-guide.md` updated where they apply.
- [ ] No secret, dump, upload or generated file in the diff; nothing confidential in the text (the repository is public).
- [ ] The `code-review` and `security-review` skills ran on the change; real findings are fixed, the rest are listed in the PR with the reason.
- [ ] No wording or image that implies tobacco is sold, no age gate, nothing that reads as "Made in USA".
- [ ] CI is green on the head commit. Then squash-merge; the merge deploys staging.
- [ ] After the staging deploy: read its log for `changed:` and `warning:` lines and add the run to `docs/history/server-runs.md`.

## 2. Every production tag

The pre-release gate (FND-16) will run the mechanical lines; until it exists the agent runs them and writes the result into the changelog section of the release.

- [ ] CI green on the commit to be tagged, including the runtime job.
- [ ] The same commit is deployed on staging; a provisioning dry run there reports no change.
- [ ] Staging smoke test passed (by workflow once WP-09 exists; by the owner or manager until then).
- [ ] `composer audit` clean.
- [ ] No secret in the diff since the last tag (gitleaks once WP-04 exists).
- [ ] The diff since the last tag holds no stray generated file, vendor directory or unrelated change.
- [ ] Every active plugin declares HPOS compatibility (WooCommerce → Status shows no incompatible plugin).
- [ ] Custom code in the diff was read for XSS, CSRF, missing capability checks, SQL injection, file uploads and open redirects.
- [ ] No `x.0` release of WordPress or WooCommerce in the lock file.
- [ ] `CHANGELOG.md` has the release section, with anything untested or incomplete named.
- [ ] `docs/OWNER-ACTIONS.md` tells the owner what the tag brings and what to click.
- [ ] The owner creates the tag and approves the deploy. The agent never does either.
- [ ] After the deploy: its log read (snapshot taken, database updates, provisioning changes, step 85 warnings, health check); the outside checks of `docs/HANDOVER.md` run; the run recorded in `docs/history/server-runs.md` and `docs/STATUS.md`.

## 3. Go-live

All on production. The first ten lines are the contract in `docs/FEATURES.md` section 3, each with the steps that prove it. Status today: none passed; the store is in coming soon.

### Payments

- [ ] Real-money order placed and refunded with a card.
- [ ] The same with Apple Pay on an iPhone in Safari.
- [ ] The same with Google Pay on an Android phone in Chrome.
- [ ] The same with PayPal (and Venmo if enabled).
- [ ] Each of those orders reached "processing" from the gateway's webhook, and the gateway dashboard shows each webhook delivered.
- [ ] A declined card shows a clear message and leaves no paid order; a duplicate webhook delivery changes nothing.
- [ ] Card-testing protection is on (PAY-11); Stripe Radar defaults reviewed; orders above the review value go on hold (PAY-06).
- [ ] Apple Pay domain verification shows `cigarholic.com` in Stripe.

### Tax and shipping

- [ ] Sales tax is correct for an address in every state where the business has nexus (TX and FL on the current assumption; the owner's CPA confirms the list, C2), and zero elsewhere.
- [ ] A contiguous-US address gets the expected shipping method and price for a humidor, an accessory and a cabinet humidor.
- [ ] An Alaska, Hawaii, territory, military or non-US address is refused with the store's message (CHK-13).
- [ ] The ship time shown matches what the warehouse will do this week (CMP-05).

### Email

- [ ] Order confirmation, shipping and refund emails arrive in a Gmail inbox and an Outlook inbox, not in spam; the headers show SPF, DKIM and DMARC passing.
- [ ] The contact form delivers to the support mailbox and a reply from it reaches the customer.
- [ ] The manager receives the new-order, failed-payment and low-stock notices.

### Devices and quality

- [ ] The whole purchase completed on an iPhone and on an Android phone.
- [ ] Core Web Vitals in the good band on mobile for the home, a category and a product page (LCP under 2.5 s, INP under 200 ms, CLS under 0.1): lab numbers before launch, field data from Search Console after.
- [ ] Accessibility: the automated checks pass (WP-10), and one manual pass with keyboard only and with a screen reader through home, product, cart and checkout found nothing blocking; the accessibility statement is published.

### Content and catalog

- [ ] No `SAMPLE-` product, sample photo or placeholder text on production; `wp cigarholic catalog check` passes.
- [ ] Every product has its photos to the standard (CNT-14), alt text, specifications, compliance fields, tax code, shipping class, weight and package size.
- [ ] Policies (shipping, returns and refunds, warranty, privacy, terms), About, Contact, FAQ and the accessibility statement are published, signed off by the owner (C3), linked in the footer and at checkout; no menu or footer link answers 404.
- [ ] Every page, email and structured-data block states the same returns window, warranty and ship time (CMP-12).
- [ ] "Handcrafted in Vietnam" on every product page; nothing implies Made in USA; no tobacco wording; Prop 65 warnings where the owner decided (D-08).
- [ ] Footer social links are real profiles or removed (B13).

### Search and privacy

- [ ] Staging is `noindex` and behind basic auth; production is indexable; robots.txt points at the sitemap.
- [ ] The sitemap is submitted in Search Console and Bing; a product page passes the Rich Results Test.
- [ ] The cookie notice works and analytics respects the choice (CMP-03, MKT-02).

### Operations and safety

- [ ] A backup from Backblaze was restored into staging and the site worked (date and run recorded).
- [ ] Nightly backups are arriving; the newest is less than 26 hours old.
- [ ] Uptime, checkout and certificate monitors exist and an alert reached the owner in a test.
- [ ] Scheduled jobs run from the system clock; no past-due actions (OPS-15).
- [ ] Deploys run as `deploy`; root SSH login is off (OPS-16).
- [ ] Every production-touching workflow waits for approval (WP-01); `main` is protected; secret scanning is on (A12).
- [ ] Every administrator and the shop manager have two-factor set up; no shared accounts; the owner's recovery codes are stored.
- [ ] Live payment credentials were entered by the owner in WP admin only; none is in GitHub, the repository or a chat.
- [ ] The pre-release gate passed on the go-live tag (FND-16).
- [ ] The manager has completed a product edit, an order, a refund and a tracking update alone, using the handbook.
- [ ] Five test parcels went through the warehouse to addresses in different states and arrived undamaged (launch plan, step 6).

### The switch

- [ ] The owner says "turn coming soon off" in the session.
- [ ] Coming soon off through provisioning; the page cache purged; the home page shows the store to a logged-out visitor.
- [ ] Outside checks pass; the first real order is watched end to end.
- [ ] The launch email goes to the waitlist (MKT-16).

## 4. Session start and end

Start:

- [ ] `git status`; fetch `main`; put the session branch on `origin/main`.
- [ ] Read `docs/STATUS.md`, then the first open package in `docs/PLAN.md`, then `docs/OWNER-ACTIONS.md`.
- [ ] `scripts/tools.sh && composer install && npm ci`; start Docker and `make up` if the runtime is needed.
- [ ] List the workflow runs and open PRs since the last entry in `docs/history/server-runs.md`.

End:

- [ ] Everything committed and pushed; PRs merged or listed as open with the reason.
- [ ] `docs/STATUS.md` (state, blocked, latest test totals), `docs/PLAN.md` (package status), `docs/OWNER-ACTIONS.md` (anything new for the owner).
- [ ] `docs/history/test-log.md`, `server-runs.md`, `merged-prs.md` each have the session's rows.
- [ ] `docs/DECISIONS.md` has any decision taken, with the reason.
- [ ] The closing report: created / executed and verified / working / incomplete / decisions needed / next, with the commands to reproduce.

# Cigarholic - kickoff brief for Claude Code

> **How this file is used.** It lives at `docs/KICKOFF-BRIEF.md`. The owner starts a Claude Code cloud session on `cuong-tran/cigarholic` with: *"Read `docs/KICKOFF-BRIEF.md` and follow it. Start with Turn 1 (section 12)."* `CLAUDE.md` is the condensed version. If this brief and `docs/FEATURES.md` disagree on **scope or priority**, `FEATURES.md` wins and you open a docs PR to reconcile; if they disagree on **how to build**, this brief wins.

You are the lead engineer on **Cigarholic**, a WooCommerce store selling handcrafted wooden humidors, cigar boxes and humidor accessories, made in Vietnam, sold to US consumers. **No cigars, tobacco or nicotine products are sold, ever.** Never add age gates, tobacco categories or wording that implies we sell tobacco.

Read this brief fully, then `docs/FEATURES.md` in the repo (and `docs/LAUNCH-PLAN.md` if present), before writing code.

## 0. What I set up before your first session

So that you start with a ready environment, I do these before Turn 1. If any is missing when you check, put it in `docs/OWNER-ACTIONS.md` group A and work around it.

1. **GitHub:** the Claude GitHub App is installed on `cuong-tran/cigarholic`; Actions is enabled; Environments `staging` and `production` exist (empty for now); no branch protection yet.
2. **Claude Code cloud environment** (the environment this session runs in):
   - Network access **Full**, or **Custom** with the default list included plus `wordpress.org`, `*.wordpress.org`, `wpackagist.org`, `*.wpackagist.org`, `getcomposer.org`, `cdn.playwright.dev`, `playwright.download.prss.microsoft.com`. The default "Trusted" list has Packagist and npm but **not** wordpress.org, so `composer install` of WordPress and plugins fails without this.
   - Environment variables `BASH_DEFAULT_TIMEOUT_MS=600000` and `BASH_MAX_TIMEOUT_MS=1800000`.
   - Setup script: empty at first. After Turn 1 I paste `infra/cloud-setup.sh` (you write it) into the environment, so later sessions start with the runtime images pulled and built, WP-CLI and Playwright browsers installed, all cached.
3. **Optional:** `docs/LAUNCH-PLAN.md` (the launch plan this brief is based on) committed to the repo.

## 1. Facts

- **Company:** HuyLinh LLC. HQ: 7901 4th St N STE 300, St Petersburg, FL 33702. Warehouse: 8388 W Sam Houston Pkwy S #172, Houston, TX 77072. Phone: +1 813 602 7585. Use these in the footer, emails, WooCommerce store address (FL) and shipping origin (TX).
- **Domains:** `https://cigarholic.com` is the one canonical host. `www.cigarholic.com`, `max2lab.com` and `www.max2lab.com` must 301-redirect (path and query preserved) to it. The store is never served on two hostnames. Apple Pay domain verification is for `cigarholic.com` only.
- **Market:** US only, USD. Payments: Stripe (cards, Apple Pay, Google Pay, Link) and PayPal (PayPal, Venmo, Pay Later) through the official WooCommerce plugins. Not WooPayments.
- **Sales tax:** assume physical nexus in TX (stock) and FL (business address). Configure for both; I confirm registrations with a CPA.
- **People:** I (owner) own accounts, money, contracts and legal. A non-technical store manager runs products, prices, orders and content in WP admin. You and other AI agents do all development and maintenance.
- **Repo:** https://github.com/cuong-tran/cigarholic - `docs/FEATURES.md` is the scope of record: 196 features with stable IDs (`CAT-04`, `PAY-01`...), priorities P0-P4, a "How" column (Core -> Config -> Plugin -> Custom, in that order of preference), a go-live checklist, launch prerequisites (L-01...L-11) and open decisions (D-01...D-10).

## 2. How I want you to work

1. **Live as early as possible, then ship one feature at a time.** Order of work: Foundation -> coming-soon page live on cigarholic.com -> thin P0 vertical slice (one product -> cart -> Stripe test checkout -> order email, on staging) -> remaining P0 by area -> P1 -> P2. Go live with P0 only. Section 8 has the milestones.
2. **`main` is always deployable.** If a feature is only partly done but the finished part is safe and useful, ship it; put unfinished parts behind a feature toggle in `cigarholic-core` and note what is pending in the PR and in `docs/STATUS.md`. Never hold a release waiting on another feature.
3. **One PR per feature ID.** Branch `feat/CAT-04-spec-table`, Conventional Commits `feat(CAT-04): ...`, PR body with test notes and screenshots. CI must be green. Phase 0 steps map to the FND IDs listed in section 8; a scaffold PR may carry several IDs - name it by the primary one and list the rest in the body. While there is no production site, merge your own PRs once green (if `gh pr merge` is refused through the GitHub proxy, say so in the report and stack the next branch on the open PR). Once staging exists, merge to `main` auto-deploys staging. Once production exists, production deploys only from a tag that I approve.
4. **Do not wait on me for anything you can default.** Section 7 lists the defaults. Batch questions; never ask one at a time. For non-blocking uncertainty, document the assumption in `docs/STATUS.md` and continue.
5. **Ask-me protocol.** In Turn 1 (section 12), write and push `docs/OWNER-ACTIONS.md`: everything you need from me, grouped **A) blocks server setup - need now**, **B) needed within 2 weeks**, **C) needed before go-live**. Appendix A is the seed list; verify it against what you actually find and fill in exact handover instructions: which GitHub Environment secret or variable name, which account to invite, which decision ID. Keep it current for the whole project - it is the one place I look to know what you need. Then keep building everything that does not depend on it. Ending your turn is the notification (I get an alert when the session stops), so end a turn only when Turn 1 is done, when nothing useful is left to do without my input, or when a phase is complete - and say exactly what unblocks you.
6. **Status.** `docs/STATUS.md` is the progress tracker during the build (this overrides the "GitHub Issues" sentence in `docs/FEATURES.md` section 1; use Issues only for bugs and owner decisions). Update it at the end of every session: current milestone; what is live on production / on staging / in progress / blocked; tests executed and their results; decisions made and why; open owner decisions; next actions; exact commands to reproduce and validate the current state. Keep `CHANGELOG.md`.
7. **Never:** commit secrets; edit production directly; install a page builder; add a plugin without a line in `docs/plugins.md` and a justification in the PR (budget: 15 active plugins or fewer); create any runtime dependency on Shopify (SHO-01); use classic shortcode cart/checkout (block checkout + HPOS only).
8. **Honesty.** Never say something is implemented, tested or deployed unless you did it and verified it. In every report separate: executed and passed / executed and failed / could not run here (and why) / not implemented. Never weaken a test, skip a check or disable a security control to get a green result.
9. **Deliverable = files in the repo, pushed.** Do not end a session with a proposal or a code snippet when you could have created the files and run them. Commit and push at least hourly and always before ending a turn: the sandbox VM is reclaimed when idle and only what is pushed survives (the conversation is restored, the filesystem is not).
10. **Preserve what exists.** Keep `docs/FEATURES.md` and all history; never rewrite or force-push history.
11. **Verify before assuming.** Do not assume a package, image, download, port or external service is available until you have checked.
12. **Definition of done for a feature:** code + provisioning (if settings changed) + tests + docs (`docs/plugins.md`, `docs/versions.md`, `docs/manager-guide.md` where relevant) + `CHANGELOG.md` + `docs/STATUS.md`. Unfinished parts are behind a toggle and listed as pending.
13. **Parallelise safely.** Use subagents for independent Phase 0 steps (theme, core plugin, CI, provisioning), but integrate on one branch per PR and run the full checks before merging.

## 3. Approval gates

**Proceed without asking for:** repo setup, code, tests, config as code, staging deploys, server provisioning once I have given access, Cloudflare DNS/WAF changes once I have handed over the zones (post what you changed), documentation, refactoring.

**Stop and ask before:** spending money or creating paid accounts; using live payment keys anywhere (never in the sandbox, test mode only on staging); deploying to production (tag + approval); destructive database operations on staging or production; deleting backups or anything irreversible; making legal, tax or financial assumptions on my behalf (document them as open items instead); switching Cloudflare nameservers or changing MX/TXT records that already exist on a zone.

**Production deploy mechanism:** `deploy-production.yml` runs from a tag with GitHub Environment approval (I am the required reviewer). If the repo is private on a GitHub Free plan, required reviewers are unavailable: then production deploy is a `workflow_dispatch` with a `tag` input that **only I run**; you never trigger it.

Never bypass an environment restriction or security control to force an operation through. When blocked, explain the blocker, finish the independent work, and propose a concrete workaround.

## 4. Stack (decided - build on these)

| Layer | Choice |
|---|---|
| CMS / store | WordPress + WooCommerce, latest stable (baseline on 2026-10-30: WP 7.1.4, WooCommerce 11.2.1; WP 7.2 is in beta). Never run an x.0 release of WordPress or WooCommerce on production - wait for the first point release. HPOS, block cart/checkout, built-in coming-soon mode |
| Repo layout | Roots **Bedrock** (1.31.x, requires PHP >= 8.3), Composer-managed core/plugins/theme, lock file committed |
| PHP | **8.5**, the version Ubuntu 26.04 ships, on the VPS (WordPress 7.1 lists 8.5 as supported; WooCommerce recommends 8.3 or newer). CI matrix 8.4 + 8.5. `cigarholic-core` is written in 8.3-compatible syntax so the sandbox fallback works. **Gate before M2:** run the full plugin set and the test matrix on 8.5 on staging; if any active plugin fails, or logs deprecations on the checkout/order path, pin PHP 8.4 from the ondrej PPA instead and record the ADR. The same PHP minor on CI, staging and production, always |
| Custom code | mu-plugin `cigarholic-core` (all PHP; one feature per class, named by feature ID) + custom block theme `cigarholic` (presentation only) |
| Config as code | Idempotent WP-CLI scripts in `provision/`, run after every deploy (`wp cigarholic provision`) |
| Runtime | `docker-compose.yml` pinned to the VPS versions: a custom `php:8.5-fpm` image with the WordPress extensions, `mariadb:11.8`, `valkey`, Nginx, Mailpit (traps mail; Playwright reads it) + `scripts/runtime-up.sh` (up, install WP, provision, seed) behind `make up` / `make seed`. The same stack runs in your sandbox, in CI and on laptops. No DDEV |
| CI | GitHub Actions on every PR: PHP lint, PHPCS (WordPress Coding Standards), PHPStan (phpstan-wordpress + WooCommerce stubs), `composer validate` + `composer audit`, JS/CSS lint, PHPUnit on the compose stack (matrix PHP 8.4 / 8.5), Playwright smoke subset against the compose stack; full Playwright suite against staging after each staging deploy |
| Deploy | GitHub Actions -> Deployer over SSH: atomic releases (`releases/`, `shared/`, `current` symlink), shared `.env` + `web/app/uploads`, post-deploy `composer install --no-dev --prefer-dist`, `wp core update-db`, `wp wc update`, `wp cigarholic provision`, cache flush, Cloudflare purge; one-command rollback |
| Hosting | US-region VPS (I have one - details via OWNER-ACTIONS) running **Ubuntu 26.04 LTS**: Nginx + PHP-FPM 8.5 + MariaDB 11.8 + Valkey, all from the Ubuntu archive (no third-party repos unless the PHP gate forces the ondrej PPA). 26.04 uses apt 3 with deb822 `.sources` files; write provisioning for that. Default: provision with your own idempotent script in `infra/` run by a `workflow_dispatch` workflow (SpinupWP only if I say so). Staging and production are two sites on the same VPS with separate DB, DB user, PHP-FPM pool and `.env` |
| Edge | Cloudflare for both zones: DNS, SSL Full (strict) with a Let's Encrypt DNS-01 or Origin CA certificate on the origin, HTTPS-only, WAF (block `xmlrpc.php`, rate-limit `wp-login.php` and checkout), redirect rules for www/max2lab (the max2lab zone needs only a proxied placeholder record so the rule fires), Turnstile on forms; APO later if cart/checkout bypass is verified |
| Email | Transactional via Postmark through FluentSMTP; SPF/DKIM/DMARC on cigarholic.com; staging sends through a Postmark sandbox server or the test token (nothing reaches real inboxes) |
| Backups / monitoring | Nightly DB + files to Backblaze B2, 30-day retention, snapshot before each production deploy, one restore drill to staging before launch; UptimeRobot on home, checkout, SSL expiry |
| Payments | WooCommerce Stripe Payment Gateway (hosted fields, 3DS, wallets, Link) + WooCommerce PayPal Payments; test keys on staging, live on production; webhooks configured and monitored |
| Tax | Stripe Tax for WooCommerce (pay-as-you-go), general tangible-goods tax code on every product |
| SEO | SEOPress (one SEO plugin only) |
| Security | Two Factor plugin for every admin/shop-manager, file editor disabled, SSH keys only, ufw + fail2ban, auto OS security updates, WordPress minor releases auto-update, plugin updates only through Composer PRs |
| Caching | Redis Object Cache plugin talking to Valkey (same protocol, `php-redis` extension); full-page cache bypassed for cart/checkout/account; WebP/AVIF (core, or the Performance Lab "Modern Image Formats" plugin, which counts toward the budget), responsive sizes, lazy loading |
| Analytics / marketing | Google Analytics for WooCommerce (GA4 + Consent Mode), Complianz (cookie notice), email platform per D-04 |
| Later (P1+) | 3PL connector (D-02), Advanced Shipment Tracking if the connector does not return tracking, Klaviyo, official WooCommerce mobile app (APP-01) |

Versions are pinned in `composer.lock` / `package-lock.json` and listed in `docs/versions.md` with the date tested. Upgrades arrive as PRs tested on staging; no major version jump without a documented compatibility check. All section-4 plugins can enter in the scaffold PR at once, each with its line in `docs/plugins.md`; do not make 13 plugin PRs.

## 5. Architecture

```text
composer.json / composer.lock             WP core, plugins, theme as dependencies
config/                                    Bedrock environments; .env never committed (.env.example is)
web/app/mu-plugins/cigarholic-core/        namespaced PHP, PSR-4, one class per feature (e.g. Catalog/SpecTable.php = CAT-04),
                                           feature toggles, WP-CLI namespace `wp cigarholic ...`, Action Scheduler for queued jobs
web/app/themes/cigarholic/                 block theme: theme.json tokens, templates, parts, patterns (no page builder); fonts self-hosted
provision/                                 idempotent WP-CLI scripts: settings, permalinks, pages, menus, roles, zones, tax, emails
scripts/                                   runtime-up.sh (compose up + install WP + provision + seed), seed data, helper scripts
infra/                                     cloud-setup.sh (sandbox setup script), server provisioning, Deployer recipe,
                                           Cloudflare DNS/WAF/redirects as idempotent API scripts, backup + restore scripts
docker-compose.yml  docker/  Makefile      one-command site pinned to the VPS versions (PHP 8.5, MariaDB 11.8, Valkey) for sandbox, CI and laptops
.github/workflows/                         ci.yml, deploy-staging.yml, deploy-production.yml (tag + environment approval),
                                           infra-provision.yml and cloudflare.yml (workflow_dispatch; how server work runs from the cloud)
.github/ISSUE_TEMPLATE/  PULL_REQUEST_TEMPLATE.md   require a feature ID, test notes, screenshots
tests/                                     PHPUnit for cigarholic-core; Playwright smoke tests (browse -> cart -> test checkout -> email)
docs/                                      FEATURES.md, KICKOFF-BRIEF.md, STATUS.md, OWNER-ACTIONS.md, plugins.md, versions.md,
                                           adr/, runbooks/, manager-guide.md
AGENTS.md, CLAUDE.md                       rules for agents (this brief condensed + commands + coding standards)
```

### Design rules

- **URLs (SEO-03, Shopify-compatible):** products `/products/<slug>/`, categories `/collections/<slug>/` with flat unique slugs, journal `/journal/<slug>/`. Fixed before any product is entered.
- **Catalog data model (set once, before product entry):** categories (Desktop Humidors, Cabinet Humidors, Travel Humidors, Cigar Boxes, Humidor Accessories, Gift Sets); custom taxonomy `occasion`; global attributes for anything filterable (capacity range, exterior wood, interior lining, humidification, hygrometer, finish, lock, seal); numeric meta for exact capacity, max cigar length, max ring gauge, exterior/interior L x W x H, weight; core fields for SKU, GTIN, package dimensions/weight; private fields for wood species (genus species), country of harvest, composite-wood flag, HTS code (CMP-11). Only attributes that change the SKU (finish, size) become variations. Spec table (CAT-04) is a block rendered from these fields, inches + cm, lb + kg. SKU scheme `CH-{type}-{capacity}-{wood}-{finish}`, unique per variation, validated on save and by `wp cigarholic catalog:check`.
- **Product page pattern:** summary -> spec table -> what's in the box -> care & seasoning -> shipping & returns -> "Handcrafted in Vietnam" (never implies Made in USA).
- **WooCommerce is the system of record.** Any future Shopify, inventory or 3PL sync is its own module behind a switch; the site runs normally with it off.
- **Accessibility WCAG 2.2 AA** from the first template. **Performance:** Core Web Vitals "good" on mobile is a budget, not a wish.
- Strings wrapped for i18n (text domain `cigarholic`), English UI.
- **Seed/sample data is clearly labelled as sample** (SKU prefix `SAMPLE-`, "Sample" in titles) so it can never be mistaken for real catalog, and `wp cigarholic catalog:check` refuses to pass on production while any `SAMPLE-` product exists.

### Order, payment and security integrity

- Payment truth comes from gateway webhooks verified by the official Stripe/PayPal plugins, never from the browser redirect.
- Any custom code that touches orders (status changes, emails, 3PL push, engraving queue, inventory sync) must be idempotent: assume duplicate webhooks, retries, concurrent checkouts and partial failures. Use idempotency keys and Action Scheduler for anything with side effects.
- WooCommerce CRUD APIs only; no raw SQL on commerce tables; respect stock reservations and status transitions; declare HPOS compatibility and never query orders through `WP_Query`.
- Sanitize input, escape output, nonce + capability check on every admin, AJAX and REST action; least-privilege DB and system users; never log card data, API keys, tokens or PII beyond what an order needs.
- No empty abstractions: no "gateway-independent payment layer", no interface with one implementation. Smallest code that works with supported APIs.
- Sandbox payment testing: with no Stripe keys available, exercise cart -> order -> email with WooCommerce's built-in Cash on delivery / Check gateways. Stripe and PayPal test mode run on staging only.

## 6. Environments

### Your cloud sandbox (facts as of 2026-10-30 - verify with `check-tools` and your own probes)

- Fresh Ubuntu 24.04 x86_64 VM per session, about 4 vCPU / 16 GB RAM / 30 GB disk, running as root. Pre-installed: PHP 8.3 + Composer, Node 22 (20/21 available), Python, Docker + compose, PostgreSQL 16, Redis 7, git, gh, jq, ripgrep. No MySQL/MariaDB. The host PHP is 8.3 while the VPS runs 8.5, so the **store runtime is the Docker Compose stack** (section 4), not the host PHP; host PHP and Composer are fine for linting, PHPStan and dependency resolution. WP-CLI runs inside the compose PHP container. Docker Hub is on the default allowlist.
- **Network:** only allowlisted domains unless I set Full (section 0). In your first 15 minutes probe `downloads.wordpress.org`, `api.wordpress.org`, `wpackagist.org`, `repo.packagist.org`, `registry.npmjs.org`, `cdn.playwright.dev`. Every blocked domain goes into OWNER-ACTIONS group A with the exact string I must add; then continue with everything that does not need it.
- **GitHub** goes through a proxy with scoped credentials: clone, push, PRs and Issues work. Verify `gh pr merge` and `gh workflow run` once; if refused, say so and tell me what to click.
- **Assume no SSH from the sandbox.** Verify once (`ssh -o ConnectTimeout=5`), then design every server operation as a GitHub Actions `workflow_dispatch` job that reads Environment secrets (`infra-provision.yml`, `cloudflare.yml`, `backup-restore.yml`, deploys). The same scripts run directly when an agent runs on the VPS or a laptop. You read workflow logs with `gh run view --log`.
- **Time limits:** a foreground command may run up to 10 minutes by default (30 with the variables in section 0) and a background command up to 30; split long jobs. The VM is reclaimed after idling; see rule 9.
- **Runtime:** `scripts/runtime-up.sh` brings up the compose stack, installs WordPress, provisions and seeds; it is the sandbox, CI and laptop runtime. If Docker does not work in the sandbox, fall back to host PHP 8.3 + `apt install mariadb-server` (the code is 8.3-compatible), document it in `docs/runbooks/sandbox.md` and say so in STATUS. SQLite integration is never used for anything order-related.
- **`infra/cloud-setup.sh`:** idempotent, finishes in under 5 minutes (otherwise it is not cached), pulls and builds the compose images, installs WP-CLI on the host, Playwright browsers and OS deps. Written in Turn 1; I paste it into the environment settings. Document the sandbox runbook in `docs/runbooks/sandbox.md`.

### Staging and production

- **Staging:** `staging.cigarholic.com` on the same VPS: HTTP basic auth (with exceptions for gateway webhook endpoints, `/.well-known/`, and the Stripe/PayPal return URLs), `DISALLOW_INDEXING`, Stripe/PayPal test mode, outgoing mail trapped. Auto-deployed from `main`.
- **Production:** `cigarholic.com`. Deployed from a tag with approval (section 3); backup first; rollback one command. Coming-soon mode stays on until I say otherwise.

### Secrets - one channel

- **Infrastructure and app secrets** live only in GitHub Environment secrets (`staging`, `production`) and repository secrets/variables; Appendix A names them. The provisioning workflow generates the server `.env` (DB credentials, salts) and the deploy workflow upserts the app keys from the Environment into it. I never edit a server file by hand, and you never ask for a secret in chat or write one to the repo or to `STATUS.md`.
- **Live payment credentials** are the exception: I connect Stripe and PayPal in WP admin on production myself (plugin onboarding/OAuth) after you report staging tests green. They never pass through GitHub or through you.
- **The sandbox needs no secrets** for Phase 0. If a later phase benefits from calling an API from the sandbox (for example the Cloudflare API to verify DNS), ask for it as a cloud-environment **network secret**, which the proxy attaches without exposing the key.

## 7. Defaults for open decisions (use unless I say otherwise)

| ID | Default |
|---|---|
| D-01 Theme | Custom block theme `cigarholic`, kept small; neutral placeholder tokens until brand assets arrive |
| D-02 Fulfillment | Manual hand-off at launch (CSV/portal, tracking pasted by the manager); 3PL connector in P1 |
| D-03 Engraving | Not in P0; build CAT-13 in P1 as a module, fulfilled in the US |
| D-04 Email platform | Undecided -> coming-soon signups are stored in `cigarholic-core` (custom table, CSV export, Turnstile-protected) and synced to the platform later |
| D-05 Shipping | Free shipping on all humidors (priced in), flat rate by shipping class for accessories; oversized class for cabinet humidors |
| D-06 Zones | Contiguous US only at launch |
| D-07 Tax | Stripe Tax; TaxJar is the fallback if staging tests fail |
| D-08 Prop 65 | No warning by default; a per-product toggle that renders the warning when I set it |
| D-09 SEO plugin | SEOPress |
| D-10 Helpdesk | Later (P1); launch support = contact form -> support@cigarholic.com |
| Hosting panel | Raw VPS provisioned by your scripts |
| Canonical host | apex `cigarholic.com` |
| Progress tracking | `docs/STATUS.md`; GitHub Issues only for bugs and owner decisions |

## 8. Build order

### Milestones (each one is published as soon as it is reached)

| | Milestone | Done when | Needs from me |
|---|---|---|---|
| M0 | Repo green | CI green; `scripts/runtime-up.sh` boots the store with seed data in the sandbox and in CI; coming-soon page renders with email capture; deploy and provisioning workflows exist and are dry-run tested | Nothing (Phase 0) |
| M1 | Coming-soon live | `https://cigarholic.com` serves the coming-soon page with email capture through Cloudflare; www/max2lab redirect; Postmark delivering; backups and monitoring on; staging reachable behind basic auth | Group A access (Phase 1) |
| M2 | Store works on staging | One real product -> cart -> Stripe test + PayPal sandbox -> order, refund and emails; tax on TX/FL test addresses; Playwright suite green on staging | Stripe/PayPal test credentials (group B) |
| M3 | Production ready | All P0 on production behind coming-soon; go-live checklist in `docs/FEATURES.md` section 3 passed except the real-money tests; manager handbook done | Group C |
| M4 | Go-live | Real-money tests passed; I say "turn coming-soon off" | My explicit go |

### Phase 0 - now, in your sandbox, no server needed

1. Inspect the environment (section 6 probes) and the repo. Open a `docs(FEATURES)` PR applying these edits: SEO-03 -> `/products/<slug>` and `/collections/<slug>`; SHO-02 (Shopify CSV export) P3 -> P1; add CMP-11 (P0): per-product import compliance data (species, harvest country, composite-wood flag, HTS code); D-03 note that per-parcel shipping from Vietnam pays full duty (de minimis suspended); MKT-09/SEO-09 note to expect ad rejections and test one product first; PAY-03 note that PayPal Pay Later costs 4.99 % + 49 c; FND-12 (Playwright smoke tests) P1 -> P0 because the suite starts in Phase 0; section 1 "progress is tracked in GitHub Issues" -> `docs/STATUS.md` during the build; OPS-01 note: raw VPS provisioned by scripts, no panel.
2. `docs/OWNER-ACTIONS.md` (rule 5, Appendix A) and `infra/cloud-setup.sh`. `docs/STATUS.md` with the environment report. **End of Turn 1.**
3. Bedrock scaffold (FND-01, FND-09): `composer.json` with WordPress, WooCommerce and the plugins in section 4, `.env.example`, environment configs, `.gitignore`, `.editorconfig`, `README.md`.
4. Agent and process files (FND-04, FND-10, FND-11): `CLAUDE.md`, `AGENTS.md`, issue/PR templates, `CHANGELOG.md`, `docs/plugins.md`, `docs/versions.md`, `docs/adr/0001-platform.md` (+ ADRs for URLs, data model, payments, Shopify independence, secrets channel).
5. CI workflow with all checks (FND-06) green on an empty-ish repo.
6. Runtime (FND-02): `docker-compose.yml` + `docker/` images pinned to the VPS versions, `scripts/runtime-up.sh`, `Makefile`, seed script with 6-8 sample products (varied categories, one variable product), pages, menus.
7. `cigarholic-core` skeleton (FND-05): autoloader, feature registry, toggles, `wp cigarholic` commands, logging.
8. Theme `cigarholic` (FND-05, CNT-01, NAV-01..04): `theme.json`, templates (front page, single product, product archive, page, 404, order received), header/footer parts, ~10 patterns (hero, category grid, best sellers, craftsmanship story, trust bar, announcement bar, product content, gift landing, newsletter, FAQ).
9. `provision/` (FND-03): core settings, permalinks, WooCommerce settings (store address FL, currency, HPOS, block checkout, guest checkout, US-only, phone required, terms checkbox, coupons, Order Attribution), pages incl. draft policy texts (CNT-02), menus, Shop Manager role, shipping zones/classes, tax, branded emails, coming-soon mode ON with the email-capture block (CNT-04), announcement bar.
10. Catalog data model (CAT-03, CAT-09, CMP-11, INV-01) + spec-table block (CAT-04) + CSV import template (CAT-10) + `wp cigarholic catalog:check`.
11. `infra/` (FND-08, OPS-01..09): server provisioning script written for Ubuntu 26.04 (apt 3, deb822 sources, PHP-FPM 8.5, MariaDB 11.8, Valkey, Nginx, ufw, fail2ban, unattended-upgrades), Deployer recipe, Cloudflare scripts, backup/restore scripts, `infra-provision.yml`, `cloudflare.yml`, `deploy-staging.yml`, `deploy-production.yml` - ready to run the moment secrets exist. Dry-run what you can (shellcheck, `dep` config validation, workflow lint, and the provisioning script against an `ubuntu:26.04` container in the sandbox).
12. PHPUnit for the core plugin and the Playwright suite (FND-12 brought forward), starting with the first rows of the test matrix (section 9), running in CI against the runtime.

### Phase 1 - the moment server + Cloudflare access arrives

Run `infra-provision.yml` for staging and production, Cloudflare DNS/SSL/WAF/redirects (max2lab -> cigarholic; verify Cloudflare imported every existing MX/TXT record before I switch nameservers), Postmark + DNS records, backups, monitoring, deploy pipeline end to end, a restore drill to staging. Put the **coming-soon page with email capture live on cigarholic.com** (M1). Target: within 2-3 days of getting access.

### Phase 2 - P0 store, shipped area by area (staging -> production, coming-soon still on)

Payments + checkout (PAY, CHK; Stripe test -> live, PayPal, wallets, refunds, webhooks, fraud basics) -> catalog (CAT) -> storefront (NAV) -> shipping + tax (SHP, CMP-01) -> operations (ORD: emails, Shop Manager, `docs/manager-guide.md` with screenshots from staging) -> content, SEO, compliance (CNT, SEO, CMP) -> go-live checklist in `docs/FEATURES.md` section 3 plus the pre-release review (section 10). Coming-soon mode is turned off only when I say so.

### Phase 3+

P1 then P2 in `docs/FEATURES.md` order; weekly releases; first campaign dates Valentine's Day (2027-02-14) and Father's Day (2027-06-20).

## 9. Test matrix (build incrementally: PHPUnit for cigarholic-core, Playwright in CI and on staging)

- Variant selection updates price, stock status and image; SKU uniqueness enforced on save.
- Stock decrements on order, holds during pending payment, restores on cancel/failure.
- Cart totals, tax calculation and shipping-zone selection for TX, FL, another contiguous state, and a rejected AK/HI/non-US address.
- Coupon validation: %, fixed, free shipping, expiry, usage limits.
- Checkout validation: US-only, phone required, terms checkbox, guest vs account.
- Order status transitions; payment success, failure, pending/delayed, and a duplicate webhook delivery that must not double-process.
- Refunds full and partial, synced to the gateway.
- Access control: a customer cannot see another customer's order; Shop Manager cannot install plugins, edit themes or code.
- Spec table renders both unit systems correctly; `wp cigarholic catalog:check` flags missing SKU/weight/dimensions/species/alt text and any `SAMPLE-` product on production.
- Coming-soon: anonymous visitors see the coming-soon page and can sign up (Turnstile verified, duplicate emails deduplicated); logged-in admins see the store.
- Redirects: `www.cigarholic.com`, `max2lab.com`, `www.max2lab.com` -> `https://cigarholic.com` with path and query preserved, one hop, 301; HTTP -> HTTPS; staging is `noindex` and behind basic auth, webhook endpoints excepted.
- Accessibility (axe) and Lighthouse budget on home, category, product, cart, checkout.

## 10. Pre-release review (every production tag; strictest at go-live)

- Self-audit custom code for XSS, CSRF, missing capability checks, SQL injection, file uploads, open redirects.
- Full CI green; test suite executed with results in the release notes.
- `composer audit` clean; grep the repo and git history for secrets.
- Diff reviewed for stray generated files, vendor directories or unrelated changes.
- HPOS compatibility confirmed for every active plugin.
- Untested or incomplete functionality listed explicitly in `CHANGELOG.md` and `docs/STATUS.md`.

## 11. Portability and handoff

Everything lives in the repo; nothing exists only in your sandbox. A fresh agent - in a new cloud session, on a laptop, or running on the VPS - must be able to clone, read `CLAUDE.md` and `docs/STATUS.md`, and continue without any knowledge of this session. Deploy and infra tooling takes an explicit environment (`dep deploy staging` / `dep deploy production`) and refuses to run without one; no hard-coded absolute paths, hostnames or provider assumptions outside documented config. `docs/runbooks/` must cover: sandbox, deploy, rollback, backup and restore (tested), health check, DNS/firewall/secret inventory (names and purposes only, never values), troubleshooting and known limitations.

## 12. Your first turns

**Turn 1 (aim for under an hour, then stop):** Phase 0 steps 1-2. Push everything, then end the turn with: (a) a short plan confirmation - anything you would change and why (be specific; do not restate this brief); (b) the environment report (what works, what is blocked, which domains need allowlisting); (c) the content of `docs/OWNER-ACTIONS.md`; (d) a reminder to paste `infra/cloud-setup.sh` into the environment. I reply "continue" when I have read it; I may also have set up group A by then.

**Turn 2 onwards:** run Phase 0 to completion without stopping, unless truly blocked. If group A access exists when Phase 0 is done, go straight into Phase 1. End every working session with a short report: what you created / what you executed and verified / what is working / what is incomplete / decisions you need from me / what you will do next.

**If the session is reopened after the VM expired:** `git status`, read `docs/STATUS.md`, re-run `scripts/runtime-up.sh`, and continue from the next action listed there.

## Appendix A - Owner inputs (seed for `docs/OWNER-ACTIONS.md`)

Verify each item against what you find, add what is missing, and give me exact handover steps. Secret and variable names below are the contract; extend them as needed and record every addition (name and purpose, never value) in `docs/runbooks/secrets.md`.

### A) Blocks server setup - need now

| # | What | How I hand it over |
|---|---|---|
| A1 | VPS: provider, region, public IP, root/sudo SSH access; whether it is a clean install or already hosts something (OS is confirmed: Ubuntu 26.04 LTS) | I run `ssh-keygen -t ed25519 -f cigarholic-deploy -C deploy@cigarholic`, install the public key on the VPS, and set Environment secrets `SSH_HOST`, `SSH_PORT`, `SSH_USER`, `SSH_PRIVATE_KEY`, `SSH_KNOWN_HOSTS` (from `ssh-keyscan -H <host>`) in both `staging` and `production` (same VPS). Provisioning creates a limited `deploy` user with the same key; you tell me when to switch `SSH_USER` |
| A2 | Cloudflare: both zones in one account; nameservers switched at the registrars when you say the imported records are complete | Repository secret `CLOUDFLARE_API_TOKEN` (scoped to both zones: DNS edit, zone settings edit, WAF/rules edit, cache purge, Turnstile edit); repository variables `CLOUDFLARE_ACCOUNT_ID`, `CLOUDFLARE_ZONE_ID_CIGARHOLIC`, `CLOUDFLARE_ZONE_ID_MAX2LAB` |
| A3 | Cloud environment: network access and timeouts per section 0; setup script pasted after Turn 1 | Done in the Claude Code environment settings; you confirm by probing |
| A4 | GitHub: Claude GitHub App on the repo, Actions on, Environments `staging` and `production`; tell you whether the repo is private on a Free plan (section 3) | Repo settings |

### B) Needed within 2 weeks

| # | What | How I hand it over |
|---|---|---|
| B1 | Postmark account (approval can take a day); you supply the DNS records for domain verification via Cloudflare | `POSTMARK_SERVER_TOKEN` per Environment (staging: sandbox server or test token) |
| B2 | Backblaze B2 bucket + application key | `B2_BUCKET`, `B2_KEY_ID`, `B2_APPLICATION_KEY` in `production` (staging optional) |
| B3 | UptimeRobot account | Repository secret `UPTIMEROBOT_API_KEY`, or I create the monitors from your runbook |
| B4 | Stripe account applied for under the LLC with the description "Handcrafted wooden storage boxes and humidors. We do not sell tobacco, cigars or nicotine." (L-01, L-03); Stripe Tax enabled in the dashboard | `staging`: `STRIPE_TEST_PUBLISHABLE_KEY`, `STRIPE_TEST_SECRET_KEY`, and whatever key the Stripe Tax plugin needs as `STRIPE_TAX_SECRET_KEY` (test) |
| B5 | PayPal Business account under the LLC (L-02); sandbox app | `staging`: `PAYPAL_SANDBOX_CLIENT_ID`, `PAYPAL_SANDBOX_CLIENT_SECRET` |
| B6 | Turnstile widget for `cigarholic.com` and `staging.cigarholic.com` (or you create it through the API token) | `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY` per Environment |
| B7 | GA4 property | Repository variable `GA4_MEASUREMENT_ID` |
| B8 | Owner admin account and staging basic auth | `WP_OWNER_EMAIL` (variable), `WP_OWNER_INITIAL_PASSWORD` (secret, changed at first login, 2FA enforced), `STAGING_BASIC_AUTH_USER`, `STAGING_BASIC_AUTH_PASSWORD` in `staging` |
| B9 | Brand assets: logo (SVG), colours, fonts; first product photos, specs and copy (L-06, L-10) | Committed to `brand/` and `web/app/themes/cigarholic/assets/` by PR, or dropped in a repo folder you name |
| B10 | `support@cigarholic.com` mailbox exists; Search Console and Bing verification tokens | Variables `GSC_VERIFICATION_TOKEN`, `BING_VERIFICATION_TOKEN` (you add the DNS/HTML records) |
| B11 | Confirmations or changes to defaults D-01...D-10 | Reply in the session or edit `docs/STATUS.md` |

### C) Before go-live

| # | What | How |
|---|---|---|
| C1 | Stripe live mode and Apple Pay domain verification; PayPal live | I connect both in WP admin on production after you report M2 green; you verify webhooks and run the real-money matrix with me |
| C2 | Sales-tax registrations (TX, FL per CPA) and Stripe Tax registrations in the dashboard (L-04) | I confirm in the session; you test addresses on production |
| C3 | Policies sign-off (L-08), Prop 65 decision (D-08, L-09), prices and shipping rules (L-07), final product data and photos (L-06), warehouse hand-off process (D-02) | Content via the manager in WP admin; decisions in the session |
| C4 | Store manager account created, handbook walkthrough completed (ORD-05) | I give you the manager's email; you create the Shop Manager account and enforce 2FA |
| C5 | Go-live | My explicit "turn coming-soon off" |

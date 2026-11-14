# Merged pull requests

Every PR squash-merged to `main` with CI green, in order. Moved here from `docs/STATUS.md` on 2026-11-13 (project revision); new rows are added at the end of each session. What each release contains for users is in `CHANGELOG.md`.

| PR | Feature IDs | What |
|---|---|---|
| #3 | docs | FEATURES reconciled with the brief, OWNER-ACTIONS, this file, cloud setup script |
| #4 | FND-01, FND-09 | Bedrock scaffold: WordPress 7.1.4, WooCommerce 11.2.1, 11 launch plugins pinned, phars via `scripts/tools.sh` |
| #5 | FND-04, FND-10, FND-11 | AGENTS.md / CLAUDE.md, templates, changelog, plugin register, versions, ADRs 0001–0008 |
| #6 | FND-06 | CI: PHP 8.3 + 8.5 lint, PHPCS, PHPStan, audit, eslint, stylelint, shellcheck, actionlint |
| #7 | FND-02 | Compose runtime pinned to the VPS minors, `scripts/runtime-up.sh`, Makefile |
| #8 | FND-05, CNT-01, NAV-01..04, CAT-06, CHK-06 | Block theme `cigarholic` (13 templates, 10 patterns, coming-soon template) |
| #9 | FND-05, FND-03, FND-12, OPS-07 | `cigarholic-core` skeleton: features and toggles, CLI, provisioning runner, hardening, PHPUnit harness |
| #10 | FND-08, FND-07, OPS-01..09 | Server provisioning (Ubuntu 26.04), Deployer recipe, Cloudflare and backup scripts, deploy/provision workflows, runbooks |
| #11 | CNT-04, MKT-01, NAV-02 | Coming-soon email capture (Turnstile, REST, CSV), announcement bar, manager guide started |
| #12 | FND-12 | Playwright smoke suite and the CI runtime job (install, provision, PHPUnit, Playwright) |
| #13 | CNT-03 | Contact form block emailed to the support inbox, Turnstile, REST + no-JS fallback, settings page |
| #14 | FND-03, CHK-01/03/04/05, SEO-03, CNT-02, NAV-01, ORD-02/03/04/07, SHP-01/02, CMP-01, CNT-04 | Provisioning steps 10–80: WooCommerce settings incl. HPOS, checkout, permalinks, draft pages, navigation, Shop Manager role, shipping zones, tax, emails, whole-site coming-soon |
| #15 | FND-13 | Dependabot for Composer (daily, grouped), npm and GitHub Actions |
| #16–#20, #24–#26 | FND-13 | Dependabot updates merged after green CI (GitHub Actions majors, eslint 10, stylelint 17); #23 keeps `@playwright/test` pinned to the sandbox browser |
| #22 | docs | STATUS and OWNER-ACTIONS after the first Phase 1 check (host-key mismatch) |
| #27 | CAT-03, CAT-02, CAT-04, CAT-09, CAT-10, CMP-06, CMP-11, INV-01 | Catalog data model, spec table, CSV template, `wp cigarholic catalog check`, seed data; storefront smoke test active |
| #30 | docs | M0 status report |
| #31 | FND-08, OPS-01 | First real VPS runs: `LETSENCRYPT_EMAIL` as a secret, sudoers `requiretty` removed (Ubuntu 26.04 sudo rejects it), `status.sh` output on a clean server |
| #32 | OPS-04 | Staging deploys switched on (`infra/deploy/STAGING_ENABLED`) |
| #33 | OPS-06 | Monitoring runbook, `infra/monitoring/uptimerobot.sh` and `monitoring.yml` (UptimeRobot v2 API, dry run by default; waits for B3) |
| #34 | FND-08 | Server secrets alphanumeric (the old generator put backslashes in DB passwords, which broke the first deploy); bad `DB_PASSWORD` rotated by `site.sh`; WP-CLI wrapper on the server |
| #35 | FND-08 | `random_secret` without SIGPIPE (#34's version exited 141 under `pipefail`); `tests/infra/site-secret.sh` in CI |
| #36 | OPS-07, FND-08 | Security: staging basic-auth bypass closed, web installer denied on both sites; `tests/infra/nginx-staging-auth.sh` (31 cases) in CI |
| #37 | OPS-01 | `status` lists tables per database and counts web-installer requests from the access logs |
| #38 | docs | Phase 1 status, the staging incident write-up |
| #39 | FND-08 | Owner credentials and the Let's Encrypt contact from secrets only, never printed in the public logs |
| #40 | FND-08 | The deploy activates the plugins (WooCommerce first, then every plugin of the `--no-dev` release) before `wp wc update` |
| #41 | FND-03 | Search engine visibility follows `DISALLOW_INDEXING` (staging off, production on) |
| #42 | docs | Release notes `v0.1.0`, staging installed and seeded |
| #43 | OPS-07 | `/wp-login.php` and `/wp-admin[/...]` 301 into `/wp/` (both vhosts, Compose runtime with relative redirects); `tests/e2e/admin-urls.spec.js`; manager guide no longer claims two-factor is enforced |
| #44 | docs | Turn 3 handoff |
| #45 | OPS-07, FND-03, CNT-04 | After the production install: author archives 404 and no `?author=N` redirect, front-end author links to home, no users sitemap, site name instead of a login in feeds and oEmbed; provisioning trashes the untouched "Hello world!" post; the signups table waits for an installed WordPress |
| #46 | OPS-07, FND-03 | Two-factor required for administrators and shop managers (`Security\TwoFactorRequired`, 3-day grace, then profile-only); provisioning offers Authenticator App and Recovery Codes only; runbook `two-factor.md` |
| #47 | docs | M1 status update |
| #48 | docs | `v0.1.1` live, A4 and A8 done |
| #49 | SEO-01, OPS-07 | `/robots.txt` and the core and SEOPress sitemap names go to PHP ahead of the vhost's static regex (it answered 404 for every `.txt`/`.xml` without a file); `Seo\RobotsTxt` disallows everything while `blog_public` is 0; `nginx-staging-auth.sh` 69 cases (every hard deny, routing, template order), `robots.spec.js`, `RobotsTxtTest` |
| #50 | docs | `/robots.txt` and the sitemaps live on both sites |
| #51 | docs | CNT-01 brand plan (`docs/plans/CNT-01-brand-style.md`) |
| #52 | CNT-01, CNT-04, CMP-04, ORD-03 | Brand tokens, self-hosted fonts, the Forest section style, coming-soon page and email colours in the brand; ADR 0009; `ThemeContrastTest`, `brand.spec.js` |
| #53 | CNT-01, ORD-03 | Logo, site icon and email logo from the owner's artwork (provisioning step 25, `brand/`) |
| #54 | NAV-01, NAV-03 | Forest header and footer; provisioning step 85 reports theme files hidden by Site Editor copies |
| #55 | NAV-04 | Homepage in the brand layout (Forest hero, category cards, best sellers); step 85 also watches the front page |
| #56 | CNT-01, CHK-01, CMP-04, NAV-03 | WooCommerce pages in the brand (fields, quantity, tabs, gallery, shop cards, cart title, Forest checkout header, order-received card, brand error colour); the footer signup no longer pushes tablet pages sideways (Turnstile); step 85 watches `page-checkout` and `checkout-header`; decision 18 |
| #57 | docs | Turn 6 handoff (tagged `v0.2.0` by the owner) |
| #58 | FND-03 | Provisioning step 10 runs WooCommerce's new-store defaults (`WC_Install::newly_installed`) once, before its settings, so the first admin page view no longer turns email theme sync on and recolours the emails; `[0.2.0]` changelog section |
| #59 | SHP-03, CMP-05 | One ship-time setting (WooCommerce → Settings → Shipping, default 1–2 business days): `cigarholic/ship-time` block on the product page, "Ships in …" under in-stock cart items (cart, checkout summary, mini cart), trust bar and FAQ; nothing promised for backorders, out of stock or a quantity above the stock; manager guide delay procedure |
| #60 | CHK-06, SHP-03, CMP-05 | Order-received "What happens next" written from the order (ship time, backorders with the right to cancel, tracking email, account link, support with the order number, seasoning tip for humidors, payment check when on hold, nothing for unpaid orders; order key required); rendered as a real Card group |
| #61, #64 | docs | Turn 7 handoffs (`v0.3.0` tagged by the owner; A9 reset) |
| #62 | FND-13 | Dependabot: WooCommerce 11.2.2 (fix release, one DB update), Complianz 7.5.7, FluentSMTP 2.4.5; verified as an in-place upgrade of the Compose stack together with #63 |
| #63 | FND-13 | Dependabot: `php-stubs/woocommerce-stubs` 11.2.1 |
| #65 | FND-03, CNT-04, FND-13 | Staging stays in coming soon (owner, A9): `ProvisionTest::test_switch_to_live_in_wp_admin_is_undone`, manager guide, decision 22; versions and plugin register after #62/#63 |
| #66 | SEO-01, SEO-02, MKT-02 | Provisioning step `75-seo` (SEOPress titles and descriptions, `noindex` rules, sitemaps with product categories, cart/checkout/account `noindex` per page, `OnlineStore` knowledge graph, large cards, SEOPress GA module off); `Seo\WebsiteSchema` (WebSite name "Cigarholic"); PHPUnit bootstrap loads SEOPress; `seo.spec.js` |
| #67 | ORD-03, SHP-03, CMP-05 | "What happens next" in the customer's processing and on-hold emails (`Email\NextSteps`), from the same steps as the order-received card; `EmailNextStepsTest`; the checkout smoke test reads the processing email |
| #71 | SHP-04, ORD-03 | Tracking numbers on WooCommerce's own Order Fulfillments (step 50 switches it on, step 70 enables its three customer emails); `Shipping\Tracking`: the store's shipping email from templates in `cigarholic-core/templates/` (carrier name, tracking button, account link only for account holders; "has shipped" / "Part of" / "The rest of"), processing orders completed once every item has shipped without the duplicate "Completed order" email, "It has shipped." on the customer's order page; `ShippingTrackingTest`, `tracking.spec.js`; manager guide "Shipping an order" |
| #73 | CNT-01, NAV-04, FND-02 | Sample photos for the homepage and the seven sample products, rendered from code (`scripts/sample-photos/`) |
| #72, #74 | docs | Turn 9 and Turn 10 handoffs |
| #75 | OPS-07 | Two-factor not required on staging (`CIGARHOLIC_FEATURES=two-factor:off` written by the staging deploy) |

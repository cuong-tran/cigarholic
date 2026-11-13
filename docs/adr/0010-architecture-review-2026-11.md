# ADR 0010: Architecture review of 2026-11-13: keep the architecture, fix the gaps

Status: accepted, 2026-11-13. Feature IDs: FND-05, FND-08, OPS-04, OPS-05, OPS-07, OPS-15, OPS-16, CNT-03, CNT-04, CMP-12.

## Context

The owner asked for a revision of the whole project, including a reimplementation of the architecture or design if it were needed. Two weeks into the build the coming-soon page is live, 75 PRs are merged, and the store works end to end with sample data. The review read every document, every custom PHP file, every provisioning step, the infrastructure scripts, the workflows and every test (three independent read-only audits, then a check of each serious finding against the code). `docs/ARCHITECTURE.md` describes the system and lists the findings with file references.

## Decision

**The architecture stays.** Bedrock with Composer-pinned core and plugins, one mu-plugin for all custom PHP, a block theme for presentation, settings as code applied on every deploy, atomic releases, one secrets channel, and WooCommerce as the system of record are the right shape for a small store run by a non-technical manager and maintained by agents. Nothing found argues for another platform, a headless front end, a page builder, or a rewrite of the plugin or the theme. The design (ADR 0009) stays as well.

**What changes is listed, ordered and tracked.** The review found gaps that matter before real orders and real customer data, none of which needs a redesign:

1. **The production approval can be bypassed.** The WP-CLI workflow passes its command to a shell on the server, so a run against staging, which needs no approval, can act on production. The server-provisioning, backup and Cloudflare workflows act on production without the approval rule. The `deploy` user may run `certbot` as root with any arguments, which amounts to root. `main` has no protection, and staging deploys do not wait for CI.
2. **Nothing is backed up off the server, and the nightly backup fails every night** because it refuses to run without the Backblaze credentials. Only the pre-deploy database dumps exist, on the same disk; uploads are copied nowhere.
3. **Nothing drives scheduled jobs.** WordPress cron runs only when an uncached page view happens to trigger it; on staging it probably never runs.
4. **Outgoing mail is not wired.** The Postmark token is written to the server's `.env` and read by nothing. Handing over the token will not make mail work.
5. **The public forms are throttled by a header the visitor controls**, Turnstile passes when its keys are absent (they are), and a contact message is stored nowhere: if the mail is not delivered it is lost.
6. **Staging is weakly separated from production** on the shared server: one cache database, no `open_basedir`, readable logs, one PHP-FPM master.
7. **Feature toggles are not boundaries and nothing queues work.** Features call each other whatever their toggle says; no class uses Action Scheduler; there is no pattern yet for the integrations to come (payments follow-ups, email platform, 3PL).
8. **Business facts are written out about forty times** (addresses, phone, support email, ship-from city) across the plugin, the theme and provisioning, and provisioning resets a few values that belong to the manager.

Each has a work package in `docs/PLAN.md` (WP-01 to WP-14) and, where it was missing, a feature ID (OPS-15, OPS-16, FND-17, CMP-12).

Rules added by this review (also in `docs/DECISIONS.md`, DEC-34 to DEC-38):

- Every workflow that can change production carries `environment: production`; a workflow input never reaches a shell unquoted.
- A feature that is switched off prints nothing and sends nothing; a feature that needs another checks its toggle or says so in the registry.
- Work that leaves the request (mail to a third party, API calls, sync) goes through Action Scheduler with an idempotency key, from the first integration on.
- A fact about the business is stored once and read everywhere.
- A setting the manager is meant to change is provisioned once (created if missing), never reset on deploy.

## Consequences

- No rewrite, no migration, no change of URLs or data model. The work is a few weeks of ordered fixes that run beside the P0 features.
- The owner gets four new actions (protect `main`, switch on secret scanning, hand over the backup and mail accounts first, answer where the stock is); see `docs/OWNER-ACTIONS.md`.
- Until WP-01 is merged, the rule "production changes only with the owner's approval" holds by discipline, not by mechanism. Agents keep to it; the fix is first in the plan.
- This review is a reading of the code, not a penetration test, and the behaviour on the server was not observed (no SSH from the sandbox). Items marked "to confirm on the server" in `docs/ARCHITECTURE.md` are checked when their work package runs.

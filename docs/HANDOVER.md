# Handover

What a new agent (or a returning one after the sandbox expired) needs to continue without any memory of earlier sessions. Rules are in `AGENTS.md` and `CLAUDE.md`; state is in `docs/STATUS.md`; this file is the how.

Last updated: 2026-11-13 (project revision).

## 1. Read in this order

1. `CLAUDE.md` and `AGENTS.md`: the rules. Ten minutes.
2. `docs/STATUS.md`: where things stand and the open risks.
3. `docs/PLAN.md`: the first package that is open and not blocked is your work.
4. `docs/OWNER-ACTIONS.md`: what the owner has handed over since, and what is still missing.
5. As needed: `docs/ARCHITECTURE.md` (how it fits together), `docs/TESTING.md`, `docs/DECISIONS.md`, `docs/runbooks/sandbox.md` (what the sandbox can and cannot do).

Nothing lives only in a conversation. If something you need is not in the repository, that is a defect: write it down when you find out.

## 2. Start a session

About six minutes, most of it the PHP image build.

```bash
git status && git fetch origin main && git checkout -B "$(git branch --show-current)" origin/main
```

The harness names the session branch; it is the only branch you may push. After a squash merge GitHub deletes it, so the next PR starts again from `origin/main` with the same name.

```bash
nohup dockerd >/tmp/dockerd.log 2>&1 &
```

```bash
scripts/tools.sh && COMPOSER_ALLOW_SUPERUSER=1 composer install && npm ci
```

```bash
make up
```

`make up` ends with the URLs and `Sample catalog: 7 products`. Store at http://localhost:8080 (admin / admin), mail at http://localhost:8025.

Then look at what happened while nobody was working:

```bash
gh api 'repos/cuong-tran/cigarholic/actions/runs?per_page=15' --jq '.workflow_runs[] | [.id, .name, .head_branch, .event, .conclusion, .created_at] | @tsv'
```

```bash
gh api 'repos/cuong-tran/cigarholic/pulls?state=open' --jq '.[] | [.number, .title, .user.login] | @tsv'
```

Compare with the last rows of `docs/history/server-runs.md`. A production deploy you do not know means the owner tagged a release: read its log, run the outside checks below, record it.

## 3. Work

- One PR per feature ID, from the session branch. Fill in the PR template honestly.
- Before every push: `composer check`, the affected tests, then the `code-review` and `security-review` skills.
- GitHub from the sandbox: branch pushes work; `gh pr` and GraphQL do not; use the GitHub MCP tools for PRs, merges and `workflow_dispatch`, and `gh api repos/cuong-tran/cigarholic/...` for reading. Tags and branch deletion are refused: the owner creates tags.
- Server work is a workflow run, never SSH: `infra-provision.yml` (status, provision, site-staging, site-production), `wp-cli.yml`, `deploy-*.yml`, `backup-restore.yml`, `monitoring.yml`, `cloudflare.yml`. Read a run's log with `gh api repos/cuong-tran/cigarholic/actions/jobs/<id>/logs`.
- Merge your own PR when CI is green (DEC-16). The merge deploys staging. Production moves only with the owner's tag and approval.

### Until WP-01 is merged

The approval rule on production has holes (`docs/ARCHITECTURE.md`, H1 to H3). Keep to these by hand:

- `wp-cli.yml`: the command is WP-CLI arguments and nothing else. No `;`, `&&`, `|`, backticks, `$(`, redirections or `cd`.
- Do not dispatch `infra-provision.yml` with `provision`, `site-production` or `disable-root-login`, or `backup-restore.yml`, or `cloudflare.yml` without a dry run, unless the owner asked for that run in the session.

## 4. Check production from outside

After every production deploy, from the sandbox:

```bash
for u in / /shop/ '/?author=1' /feed/ /robots.txt /sitemaps.xml /wp-admin /wp/wp-admin/install.php /xmlrpc.php; do printf '%-28s ' "$u"; curl -sS -o /dev/null -w '%{http_code} %{redirect_url}\n' "https://cigarholic.com$u"; done
```

Expected while coming soon is on: 200 for `/`, `/shop/`, `/robots.txt`, `/sitemaps.xml`; 404 for `/?author=1`; 301 into `/wp/` for `/wp-admin`; 403 for the installer and `xmlrpc.php`.

```bash
for u in /cart/ /my-account/; do printf '%-14s ' "$u"; curl -sS "https://cigarholic.com$u" | grep -oE '<meta name=.robots.[^>]*>'; done; curl -sS https://cigarholic.com/page-sitemap1.xml | grep -oE '<loc>[^<]+'
```

Expected: `noindex` on both; the sitemap lists no cart, checkout or account page.

```bash
for h in www.cigarholic.com max2lab.com www.max2lab.com; do curl -sSI "https://$h/some/path?x=1" | grep -i '^location:'; done; curl -sSI http://cigarholic.com/ | grep -i '^location:'
```

Expected: one hop each to `https://cigarholic.com/some/path?x=1` (and to `https://cigarholic.com/`).

Staging answers 401 from the sandbox: its basic-auth credentials are secrets. Check staging through `wp-cli.yml` or a workflow.

## 5. Validate the current state locally

```bash
make check && make test && make e2e
```

```bash
make wp ARGS="cigarholic features" && make wp ARGS="cigarholic provision --dry-run"
```

Expected on `main` today: static checks clean, PHPUnit 123 tests, Playwright 35 of 35, 18 features on, provisioning dry run 0 changes.

## 6. Things that cost earlier sessions time

- The sandbox refuses anything that bypasses the proxy, force pushes, and any command containing the word `eval` (so no `wp eval`): put ad-hoc WordPress code in a PHPUnit test or a temporary CLI command.
- The container can restart mid-session. Files survive; the Docker daemon, background jobs and subagents do not. Commit and push early; `make up` brings the stack back.
- Do not use a bare `docker compose up` in the sandbox: `make up` mounts the gateway certificate the containers need for HTTPS.
- A branch cut from an unmerged branch shows duplicate commits after a squash merge. Never force-push to repair it; cut a new branch from `origin/main`.
- `merge_pull_request` with `expectedHeadSha` needs the full 40-character SHA.
- PHPUnit stays on 9.6 (the WordPress test suite needs it). PHPStan, PHPUnit and Deployer are phars from `scripts/tools.sh`, not Composer packages (ADR 0007).
- The WordPress test case restores hooks after each test: create WooCommerce's mailer in the bootstrap, not in a test. WooCommerce caches the first default email subject it computes; compare saved settings, not strings.
- After other tests, `$wp_rewrite` can hold a stale permalink structure in memory; provisioning tests reset it.
- WordPress gives each Card group its own `is-style-card--N` class; style the block style, not a fixed class.
- For screenshots wait for `load` plus a second, not `networkidle` (Turnstile and Query Monitor keep connections open).
- Server secrets must be alphanumeric: bash, MariaDB and PHP's dotenv read the same value differently otherwise.
- An uninstalled WordPress must never be reachable: on a new site, deploy and install back to back; the web installer is denied for good.
- The log's secret filter masks every occurrence of a secret's value, including "22" (the SSH port) inside other numbers.
- A theme file saved in the Site Editor wins over the theme. Provisioning step 85 warns in the deploy log; resetting one on production is the owner's call.
- Sourcery comments on every PR with a quota notice; it needs no answer.

## 7. Runbook sentences that do not match the code yet

Known on 2026-11-13; each is corrected by the work package named. Trust the code.

| Runbook | Says | Is | Fixed by |
|---|---|---|---|
| `backup-restore.md` | A nightly local dump is kept for three days | The nightly job exits before dumping while the Backblaze variables are missing; only pre-deploy snapshots exist | WP-07 |
| `deploy.md` | The health check wants HTTP 200 | It also accepts a redirect, and 401 on staging | WP-09 |
| `rollback.md` | (silent) | A rollback switches the code back; the database and plugin activation stay as the failed deploy left them | WP-09 |
| `server.md` | Site logs rotate daily, 14 kept | The logrotate stanza lacks the `su` line and is probably skipped | WP-13 |
| `server.md` | `certbot` under limited sudo | That right is root in effect | WP-01 |
| `secrets.md`, `wp-cli.yml` header | Production WP-CLI needs approval | True for `site=production`; a staging run can reach production | WP-01 |
| `.env.example` | The Postmark token is "set by provisioning" | No step reads it | WP-06 |

## 8. End a session

Section 4 of `docs/CHECKLIST.md`. The short version: push everything; update `docs/STATUS.md`, `docs/PLAN.md` and `docs/OWNER-ACTIONS.md`; add the session's rows to the three files in `docs/history/`; record decisions in `docs/DECISIONS.md`; end with the report (created / executed and verified / working / incomplete / decisions needed / next, and the commands to reproduce). Ending the turn is what notifies the owner.

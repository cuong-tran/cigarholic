# Status

Progress tracker for the Cigarholic build (`docs/KICKOFF-BRIEF.md` rule 6). Updated at the end of every session. A fresh agent, in a new cloud session or on a laptop, starts here after reading `CLAUDE.md` (which does not exist yet; until it does, read `docs/KICKOFF-BRIEF.md`).

Last updated: 2026-10-30, end of Turn 1 (Phase 0 steps 1 and 2).

## Where we are

| | |
|---|---|
| Current milestone | **Phase 0, Foundation** (sandbox only). M0 "Repo green" not reached |
| Live on production | Nothing. No server provisioned, domains on registrar hold (`docs/OWNER-ACTIONS.md` A5) |
| Live on staging | Nothing |
| In progress | Turn 1 deliverables: FEATURES reconciliation, `docs/OWNER-ACTIONS.md`, this file, `infra/cloud-setup.sh`, runbooks |
| Blocked | Phase 1 (M1) waits for OWNER-ACTIONS group A: A5 domain hold, A2 Cloudflare, A1 SSH secrets verified by a workflow run. Nothing in Phase 0 is blocked |

History: a first session scaffolded a Bedrock project in PR 1; the owner reverted it in PR 2 so that the build follows the kickoff brief. Only `infra/cloud-setup.sh` and two runbooks were kept from it.

## Environment report (sandbox, measured 2026-10-30)

**Works**

| Item | Finding |
|---|---|
| VM | Ubuntu 24.04.5, x86_64, 4 vCPU, 15 GB RAM, about 30 GB writable disk, root |
| Host PHP | 8.3.6 with mysqli, pdo_mysql, gd, intl, mbstring, xml, zip, curl, bcmath, redis, igbinary, sodium, OPcache (no imagick in this image). Used for lint, PHPCS, PHPStan and dependency resolution only |
| Composer | 2.8.12. `COMPOSER_ALLOW_SUPERUSER` is not set in the environment, so scripts export it |
| WP-CLI | present at `/usr/local/bin/wp`; needs `--allow-root` or `WP_CLI_ALLOW_ROOT=1` |
| Node | 22.22.0, npm 10.9.4 |
| Docker | 29.9.2, Compose v5.6.1. Daemon not running at start; `nohup dockerd &` works, ready in about 3 s. `/etc/docker/daemon.json` already points at `mirror.gcr.io`; `alpine:3.20` pulled in 2.8 s and ran. Manifests exist for `php:8.5-fpm`, `mariadb:11.8`, `valkey/valkey:8` and `:9`, `nginx:1.28`, `axllent/mailpit`, `ubuntu:26.04` |
| Playwright | 1.63.0 with Chromium 1243 under `/opt/pw-browsers`; `require('playwright')` works |
| Linters | shellcheck 0.9 and actionlint 1.7.12 present (installed by the previous setup script), Go 1.24.7 |
| Other | gh 2.89 (REST only), jq, ripgrep, Python 3.13, Redis 7.0.15 server binary (not running) |
| Timeouts | `BASH_DEFAULT_TIMEOUT_MS=600000`, `BASH_MAX_TIMEOUT_MS=1800000` set |
| Network | Effectively **Full**: `downloads.wordpress.org`, `api.wordpress.org` (reports WP 7.1.4 and WooCommerce 11.2.1 as latest), `wordpress.org`, `wpackagist.org`, `repo.packagist.org` (Bedrock 1.31.8 latest), `registry.npmjs.org`, `cdn.playwright.dev`, `getcomposer.org`, `deployer.org`, `raw.githubusercontent.com`, Docker Hub, `mirror.gcr.io`, `public.ecr.aws`, `api.cloudflare.com`, `api.postmarkapp.com`, `api.stripe.com`, `archive.ubuntu.com` (HTTP) all answer; so do unrelated hosts (`example.org`, `httpbin.org`, `pypi.org`). **Nothing needs allowlisting** |
| GitHub | Clone, push, PR creation (GitHub MCP), reading runs and job logs (`gh api`), `workflow_dispatch` through MCP (per the earlier session) work. PR merge through the API: see "Tests executed" below |

**Does not work, or absent, by design**

| Item | Finding | Consequence |
|---|---|---|
| SSH | No `ssh` binary and no raw TCP egress (port 22 to 1.1.1.1 refused) | Every server operation is a GitHub Actions `workflow_dispatch` job; the sandbox never talks to the VPS |
| MariaDB on the host | Not installed in this image (the earlier session's image had 10.11) | The store runtime is the Docker Compose stack, as the brief wants; host fallback would be `apt install mariadb-server` |
| `gh pr ...`, GraphQL | Blocked by the proxy; `gh auth status` even reports the token as invalid, yet `gh api repos/...` works | Use the GitHub MCP tools and REST |
| Environments and Actions-settings API | 403 "not permitted through this proxy" | The owner answers OWNER-ACTIONS A4 |
| `cigarholic.com`, `max2lab.com` | NXDOMAIN: both domains are on registrar `clientHold` (RDAP, iNET Software, registered 2026-10-03) | OWNER-ACTIONS A5; M1 impossible until lifted |

## Tests executed this session

- **Executed and passed:** the network probes above; Docker daemon start, image pull through the mirror and container run; `shellcheck -S warning infra/cloud-setup.sh`; FEATURES.md priority recount after the edits (P0 84, P1 39, P2 34, P3 32, P4 8, total 197, matching the summary table); RDAP and DNS-over-HTTPS checks of both domains.
- **Executed and failed:** none.
- **Could not run here:** anything on the VPS (no SSH by design); Environment and Actions settings checks (proxy 403); application tests (no code yet).
- **Not implemented:** all of Phase 0 steps 3 to 12.

## Decisions made this session, and why

1. **Turn 1 ships as one PR from the session branch** `claude/loving-keller-vzwwuu`, with the FEATURES edits in their own commit. The cloud session is pinned to that branch. From Turn 2 on, branches follow the brief: one `feat/<ID>-<slug>` branch and PR per feature ID (a scaffold PR may carry several IDs).
2. **`docs/FEATURES.md` reconciled** exactly as the brief's Phase 0 step 1 lists: SEO-03 URLs, SHO-02 to P1, CMP-11 added (P0, Custom), D-03 duty note, MKT-09 and SEO-09 rejection notes, PAY-03 Pay Later cost, FND-12 to P0, section 1 tracking sentence, OPS-01 note. The section 3 count (84 P0, 20 custom) and the section 8 totals were updated to match.
3. **No network fallback paths** are needed; the runtime is the Compose stack, not host PHP.
4. **`infra/cloud-setup.sh`** is time-boxed to about 290 s overall with per-step limits, so a slow step can never make the environment skip caching. It warms the Composer and npm caches as soon as the lock files exist, and installs the MariaDB client for ad-hoc access to the Compose database.
5. **Runtime image pins** are chosen in Phase 0 step 6, after checking which Valkey and Nginx versions the `ubuntu:26.04` image installs from the archive, so sandbox, CI and VPS run the same minors.
6. **Domain hold is the critical path for M1.** VPS provisioning can proceed without a domain (IP-based, staging first); TLS and the redirect zone need DNS.

## Open owner decisions and inputs

All in `docs/OWNER-ACTIONS.md`: A1 to A5 now, B1 to B12 within two weeks, C1 to C5 before go-live. Defaults D-01 to D-10 are in force.

## Next actions (Turn 2, Phase 0 to completion, in this order)

| # | Branch | Scope | Feature IDs |
|---|---|---|---|
| 1 | `feat/FND-01-bedrock-scaffold` | `composer.json` + lock (WordPress 7.1.4, WooCommerce 11.2.1, the plugins from brief section 4), `.env.example`, `config/`, `.gitignore`, `.editorconfig`, `README.md` | FND-01, FND-09, FND-11 |
| 2 | `feat/FND-04-agent-files` | `CLAUDE.md`, `AGENTS.md`, issue and PR templates, `CHANGELOG.md`, `docs/plugins.md`, `docs/versions.md`, ADRs 0001 to 0006 | FND-04, FND-10 |
| 3 | `feat/FND-06-ci` | `ci.yml`: PHP lint, PHPCS, PHPStan, `composer validate` + `audit`, JS/CSS lint; green | FND-06 |
| 4 | `feat/FND-02-runtime` | `docker-compose.yml`, `docker/`, `scripts/runtime-up.sh`, `Makefile`, seed script (6 to 8 `SAMPLE-` products, pages, menus) | FND-02 |
| 5 | `feat/FND-05-core-skeleton` | mu-plugin `cigarholic-core`: autoloader, feature registry, toggles, `wp cigarholic`, logging | FND-05 |
| 6 | `feat/FND-05-theme` | block theme `cigarholic`: `theme.json`, templates, parts, patterns | FND-05, CNT-01, NAV-01 to NAV-04 |
| 7 | `feat/FND-03-provision` | `provision/` scripts, coming-soon ON with email capture | FND-03, CNT-02, CNT-04 |
| 8 | `feat/CAT-03-catalog-data-model` | taxonomies, attributes, meta, spec-table block, CSV template, `catalog:check` | CAT-03, CAT-04, CAT-09, CAT-10, CMP-11, INV-01 |
| 9 | `feat/FND-08-infra` | Ubuntu 26.04 provisioning, Deployer, Cloudflare scripts, backup/restore, all workflows; dry runs | FND-08, OPS-01 to OPS-09 |
| 10 | `feat/FND-12-tests` | PHPUnit for the core plugin, Playwright smoke suite in CI | FND-12 |

Then publish M0 in this file. Phase 1 starts only when OWNER-ACTIONS group A is complete; otherwise the session ends with a report saying exactly what unblocks it.

## Reproduce and validate the current state

```bash
git clone https://github.com/cuong-tran/cigarholic.git && cd cigarholic
```

```bash
shellcheck -S warning infra/cloud-setup.sh
```

```bash
for d in downloads.wordpress.org api.wordpress.org wpackagist.org repo.packagist.org registry.npmjs.org cdn.playwright.dev; do printf '%-32s %s\n' "$d" "$(curl -sS -o /dev/null -m 15 -w '%{http_code}' "https://$d/")"; done
```

```bash
curl -sS https://rdap.verisign.com/com/v1/domain/cigarholic.com | jq -c .status
```

```bash
nohup dockerd >/tmp/dockerd.log 2>&1 & sleep 3; docker run --rm alpine:3.20 echo docker-ok
```

## If the session is reopened after the VM expired

`git status`, read this file, then continue from "Next actions". `scripts/runtime-up.sh` does not exist yet; once it does, run it before continuing.

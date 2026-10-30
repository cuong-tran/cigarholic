# Cigarholic: instructions for coding agents

WooCommerce store for handcrafted wooden cigar accessories, sold to US customers. Scope and
priorities: `docs/FEATURES.md` (feature IDs) and `docs/LAUNCH-PLAN.md`. Server setup and secrets:
`docs/runbooks/vps-setup.md`.

## Layout

| Path | What |
| --- | --- |
| `composer.json` | WordPress core, plugins and themes (Bedrock). Plugins: `composer require wp-plugin/<slug>` |
| `config/` | Bedrock config; per-environment overrides in `config/environments/` |
| `web/app/mu-plugins/cigarholic-core/` | All custom PHP. One feature per class in `src/`, named after its feature ID |
| `web/app/themes/cigarholic/` | Block theme (`theme.json`, `templates/`, `parts/`) |
| `provision/` | Idempotent WP-CLI settings scripts (`NN-name.sh`), run after every deploy and by `bin/dev setup` |
| `server/` | VPS scripts: `bootstrap.sh`, `add-site.sh`, `deploy-release.sh`, `status.sh` |
| `.github/workflows/` | `ci.yml`, `deploy.yml`, `wp.yml` (remote WP-CLI), `server.yml` (root admin) |
| `bin/` | `dev` (local site), `lint` (CI checks), `cloud-env-setup.sh` (cloud environment setup script) |

## Local development

```bash
bin/dev up       # MariaDB + WordPress at http://localhost:8080, admin / admin
bin/dev reset    # drop the local database and reinstall
bin/dev down
bin/lint         # composer validate, php -l, shellcheck, actionlint, composer audit
```

- Use `http://localhost:8080`, not `127.0.0.1`: the login cookies are set for `localhost`.
- Playwright and Chromium are installed in cloud sessions; use them to check pages and wp-admin.
- In cloud sessions Composer clones GitHub-hosted packages with git (zip downloads are blocked),
  so the first `composer install` of a new package is slow. That is expected.
- Running as root needs `COMPOSER_ALLOW_SUPERUSER=1` and `WP_CLI_ALLOW_ROOT=1`; `bin/dev` sets them.
- PHP is 8.5 everywhere: the VPS (Ubuntu 26.04), CI, the deploy build, and cloud sessions (the
  setup script installs it). Composer resolves for PHP 8.5.4 (`config.platform`).

## Environments

| Environment | URL / where | Linux user and path on the VPS | Deployed by |
| --- | --- | --- | --- |
| local | `http://localhost:8080` | this container | `bin/dev up` |
| staging | `https://staging.cigarholic.com` (basic auth, `noindex`, mail trapped) | `cigarholic-staging`, `/srv/www/cigarholic-staging` | every push to `main` |
| production | `https://cigarholic.com` | `cigarholic`, `/srv/www/cigarholic` | a published GitHub release, or the Deploy workflow with `target: production` |

Outside production, `MailGuard` (FND-07) traps `wp_mail()` unless every recipient is in `MAIL_ALLOWLIST`.

## Reaching the server from a cloud session

The cloud container has no SSH access; everything on the VPS runs through GitHub Actions. Use the
GitHub MCP tools: `actions_run_trigger` (`method: run_workflow`, `ref: main`), then
`actions_list` (`list_workflow_runs`, `list_workflow_jobs`) and `get_job_logs` to read the result.

| Workflow | Inputs | Use it to |
| --- | --- | --- |
| `deploy.yml` | `target` (staging/production), `action` (deploy/rollback) | ship the ref you run it on, or re-activate the previous release |
| `wp.yml` | `target`, `commands` (WP-CLI, one per line, no shell operators) | inspect or change site data and settings |
| `server.yml` | `action` (status/bootstrap/add-site), `site`, `domain`, `alias`, `email`, `harden_ssh` | server health and setup, as root |

To check staging from the container, request `https://staging.cigarholic.com/` with curl or Playwright
(the host must be in the environment's allowed network domains).
If the owner stored the staging basic-auth login as a network secret, the proxy adds it for you.

## Rules

- Every change goes through a branch and a PR that references a feature ID, e.g. `feat(CAT-04): spec table`.
  Use Conventional Commits. CI (`bin/lint`) must pass.
- Settings are code: put them in `provision/`, never only in wp-admin. Each step must be idempotent.
- Prefer Core, then Config, then Plugin, then Custom (see `docs/FEATURES.md`). New plugins come
  from Composer and need a one-line justification in the PR.
- No page builders. Don't edit WordPress core or third-party plugin files.
- Never commit secrets, `.env`, database dumps or uploads.
- Production: deploy, roll back or run WP-CLI commands that change data only when the owner asks
  for it in the conversation. Read-only commands (`option get`, `plugin list`, ...) are fine.
- Don't run `server.yml` `bootstrap` or `add-site` against a live production site without the owner's go-ahead.

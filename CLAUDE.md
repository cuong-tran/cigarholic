@AGENTS.md

# Claude Code notes

Everything above applies. These notes cover the Claude Code cloud sandbox specifically; the measured facts are in `docs/runbooks/sandbox.md`.

- **Start every session** with `git status`, `docs/STATUS.md`, then `scripts/tools.sh && composer install` and `make up` if the runtime is needed. The filesystem does not survive between sessions; the conversation does.
- **Docker:** the daemon is not running at start. `nohup dockerd >/tmp/dockerd.log 2>&1 &` (about 3 s). Images pull through the `mirror.gcr.io` mirror configured by `infra/cloud-setup.sh`. Containers that need HTTPS get the proxy through `HTTPS_PROXY` and the CA bundle at `/root/.ccr/ca-bundle.crt`; `scripts/runtime-up.sh` handles this.
- **Network:** all HTTPS goes through the agent proxy. Never bypass it (`--noproxy`, unsetting `HTTPS_PROXY`). GitHub API zipballs of other repositories are refused, so Composer clones GitHub packages from source; release downloads work, which is why PHPStan, PHPUnit and Deployer are phars (`scripts/tools.sh`). Under load the proxy times out some connections: lower `COMPOSER_MAX_PARALLEL_HTTP` and retry rather than switching sources.
- **No SSH, no raw TCP.** Server work runs as GitHub Actions `workflow_dispatch` jobs; read logs with `gh api repos/cuong-tran/cigarholic/actions/jobs/<id>/logs`.
- **GitHub:** `gh pr ...` and GraphQL are refused; use the GitHub MCP tools (create, read and merge PRs, trigger workflows) and `gh api repos/cuong-tran/cigarholic/...`. Branch pushes work, tag pushes and branch deletion do not. Secrets and Environments cannot be read or set from here.
- **Root shell:** export `COMPOSER_ALLOW_SUPERUSER=1` and `WP_CLI_ALLOW_ROOT=1` (the setup script writes them to `/etc/profile.d/cigarholic.sh`; scripts export them too).
- **Time limits:** a foreground command may run 10 minutes by default and 30 at most; run longer jobs in the background or split them. Push at least hourly.
- **Subagents:** use them for independent Phase 0 pieces (theme, core plugin, infra, provisioning) in their own worktrees, but integrate on one branch per PR and run `composer check` plus the tests before merging.
- **Reports:** end a session with what was created / executed and verified / working / incomplete / decisions needed / next, and the exact commands to reproduce. Ending the turn is the owner's notification.

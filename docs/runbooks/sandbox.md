# Cloud sandbox: verified facts

Measured in a Claude Code cloud session on 2026-10-30. The image and the environment's network
settings change, so re-check anything a task depends on. `infra/cloud-setup.sh` is the
environment's setup script and applies the fixes below.

## Image

- Ubuntu 24.04, 4 vCPU, 16 GB RAM, about 30 GB writable disk, running as root.
- Preinstalled: PHP 8.3 (mysqli, pdo_mysql, gd, imagick, intl, mbstring, xml, zip, curl, bcmath,
  redis, OPcache), Composer 2.8, WP-CLI 2.12, Node 22, Go 1.24, Docker 29 with compose, Playwright
  with Chromium (`require('playwright')` works from any directory), gh, jq.
- **MariaDB 10.11 is preinstalled**, contrary to `docs/KICKOFF-BRIEF.md` section 6. It runs at
  session start but is stopped after the VM is paused and restored; start it with `service mariadb start`.
- Not preinstalled: shellcheck (`apt-get install shellcheck` works), actionlint
  (`go install github.com/rhysd/actionlint/cmd/actionlint@v1.7.12` works; Go fetches the newer
  toolchain it needs), Nginx, Certbot.
- As root, Composer disables its plugins in non-interactive runs unless
  `COMPOSER_ALLOW_SUPERUSER=1` is set. `composer/installers` is one of those plugins, so without
  the variable WordPress and plugins land in `vendor/`. WP-CLI refuses to run as root unless
  `WP_CLI_ALLOW_ROOT=1` is set or `--allow-root` is passed. Both variables are set in the
  environment.

## Docker

- The daemon is not running at start. `service docker start` fails (`ulimit: Operation not
  permitted`), but starting the daemon directly works: `nohup dockerd >/tmp/dockerd.log 2>&1 &`.
- Docker Hub sometimes answers anonymous pulls with `429 Too Many Requests`, because sessions
  share their outgoing IP. With `"registry-mirrors": ["https://mirror.gcr.io"]` in
  `/etc/docker/daemon.json`, which `infra/cloud-setup.sh` writes, the same pulls succeed
  (`redis:7-alpine`, `mariadb:11.8` and `php:8.3-fpm-alpine` tested).
  `public.ecr.aws/docker/library/<image>` works too.
- For HTTPS from inside a container, run it with `--network host`, pass `HTTPS_PROXY`, and mount
  `/root/.ccr/ca-bundle.crt` into the container's trust store.
- Containers have no systemd, and `ulimit`, `swapon` and iptables (ufw) are not permitted.

## Network

- All outbound HTTPS goes through the agent proxy (CA bundle `/root/.ccr/ca-bundle.crt`). There
  is no SSH or other raw TCP egress, so server work has to run in GitHub Actions.
- Reachable on 2026-10-30: `wordpress.org`, `api.wordpress.org`, `downloads.wordpress.org`,
  `wpackagist.org`, `repo.packagist.org`, `packagist.org`, `repo.wp-packages.org`,
  `getcomposer.org`, `registry.npmjs.org`, `cdn.playwright.dev`,
  `playwright.download.prss.microsoft.com`, Docker Hub, `raw.githubusercontent.com`,
  `ppa.launchpadcontent.net`, `keyserver.ubuntu.com`, and `archive.ubuntu.com` (over HTTP).
- Blocked: the VPS IP ("Host not in allowlist"). To check the sites from the sandbox, add the
  VPS IP, `cigarholic.com` and `*.cigarholic.com` to the environment's allowed domains.
- GitHub zip downloads (`codeload.github.com`, `api.github.com/.../zipball`) return 403 for
  repositories not attached to the session, so Composer falls back to `git clone` for
  GitHub-hosted packages. That works, but the first install of each package is slower.

## GitHub from the sandbox

- Pushing branches works. According to the Claude Code docs, the proxy rejects tag pushes and
  branch deletions, so tags for production releases have to come from the owner or from a workflow.
- GraphQL is blocked, so `gh pr ...` and `gh issue ...` fail. REST through
  `gh api repos/cuong-tran/cigarholic/...` and the GitHub MCP tools work.
- Worked: creating a PR (GitHub MCP), triggering `workflow_dispatch` (MCP `actions_run_trigger`),
  listing runs and jobs, and reading job logs (`gh api repos/cuong-tran/cigarholic/actions/jobs/<id>/logs`).
- Refused with 403 ("not permitted through this proxy"): `repos/.../environments` and
  `repos/.../actions/permissions`. Secrets can be neither read nor set from the sandbox.
- A `workflow_dispatch` workflow can be triggered only once its file is on `main`.

## PHP 8.5 on the host (optional)

The VPS runs PHP 8.5.4. If the host needs PHP 8.5 too, the ondrej/php archive for noble works and
installs in about 25 seconds:

```bash
curl -fsSL 'https://keyserver.ubuntu.com/pks/lookup?op=get&search=0x14AA40EC0831756756D7F66C4F4EA0AAE5267A6C' | gpg --dearmor -o /usr/share/keyrings/ondrej-php.gpg
```

```bash
echo "deb [signed-by=/usr/share/keyrings/ondrej-php.gpg] https://ppa.launchpadcontent.net/ondrej/php/ubuntu noble main" > /etc/apt/sources.list.d/ondrej-php.list
```

```bash
apt-get update && apt-get install -y php8.5-cli php8.5-mysql php8.5-curl php8.5-gd php8.5-intl php8.5-mbstring php8.5-xml php8.5-zip php8.5-bcmath php8.5-imagick php8.5-redis
```

```bash
update-alternatives --set php /usr/bin/php8.5
```

WordPress 7.1.4, Composer 2.8 and WP-CLI 2.12 ran on PHP 8.5.12 without deprecation notices.

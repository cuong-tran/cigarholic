# Cloud sandbox: verified facts

Measured in Claude Code cloud sessions on 2026-10-30 (two sessions, two image builds). The image and
the environment's settings change, so re-check anything a task depends on. `infra/cloud-setup.sh` is
the environment's setup script and applies the fixes below.

## Image

- Ubuntu 24.04.5, x86_64, 4 vCPU, 15 GB RAM, about 30 GB writable disk (`df` shows the whole
  252 GB device; the per-session allowance is what counts), running as root.
- Preinstalled: PHP 8.3.6 (mysqli, pdo_mysql, pdo_pgsql, pdo_sqlite, gd, intl, mbstring, xml,
  zip, curl, bcmath, redis, igbinary, sodium, OPcache; **no imagick** in the second image, the first
  had it), Composer 2.8.12, WP-CLI, Node 22.22 with npm 10.9, Go 1.24.7, Docker 29.9.2 with
  Compose v5.6.1, Playwright 1.63.0 with Chromium 1243 under `/opt/pw-browsers`
  (`require('playwright')` works from any directory; never run `playwright install`), gh 2.89, jq,
  ripgrep, Python 3.13, the Redis 7.0 server binary (not running).
- **Varies by image:** the first session found MariaDB 10.11 preinstalled, the second found none.
  Do not rely on it; the store runtime is the Docker Compose stack.
- Not preinstalled: `ssh` (no binary at all), `mysql`/`mariadb` clients, Nginx, Certbot,
  Deployer. shellcheck and actionlint are present once the setup script has run.
- As root, Composer disables its plugins in non-interactive runs unless
  `COMPOSER_ALLOW_SUPERUSER=1` is set. `composer/installers` is one of those plugins, so without
  the variable WordPress and plugins land in `vendor/`. WP-CLI refuses to run as root unless
  `WP_CLI_ALLOW_ROOT=1` is set or `--allow-root` is passed. **Neither variable is set by the
  environment**; the setup script writes both to `/etc/profile.d/cigarholic.sh`, and every script in
  the repo exports them itself.
- `BASH_DEFAULT_TIMEOUT_MS=600000` and `BASH_MAX_TIMEOUT_MS=1800000` are set: a foreground command
  may run 10 minutes by default and 30 at most; background commands up to 30 minutes.

## Docker

- The daemon is not running at start. `service docker start` fails (`ulimit: Operation not
  permitted`), but starting the daemon directly works and is ready in about 3 s:
  `nohup dockerd >/tmp/dockerd.log 2>&1 &`.
- Docker Hub sometimes answers anonymous pulls with `429 Too Many Requests`, because sessions
  share their outgoing IP. With `"registry-mirrors": ["https://mirror.gcr.io"]` in
  `/etc/docker/daemon.json`, which `infra/cloud-setup.sh` writes, the same pulls succeed
  (`alpine:3.20` in 2.8 s; `redis:7-alpine`, `mariadb:11.8`, `php:8.3-fpm-alpine` tested earlier).
  `public.ecr.aws/docker/library/<image>` works too.
- Manifests verified on 2026-10-30: `php:8.5-fpm`, `php:8.5-fpm-bookworm`, `mariadb:11.8`,
  `valkey/valkey:8`, `valkey/valkey:9`, `nginx:1.28`, `axllent/mailpit:latest`, `ubuntu:26.04`.
- For HTTPS from inside a container, run it with `--network host`, pass `HTTPS_PROXY`, and mount
  `/root/.ccr/ca-bundle.crt` into the container's trust store.
- Containers have no systemd, and `ulimit`, `swapon` and iptables (ufw) are not permitted.

## Network

- All outbound HTTPS goes through the agent proxy (CA bundle `/root/.ccr/ca-bundle.crt`;
  `curl -sS "$HTTPS_PROXY/__agentproxy/status"` shows its state). There is no SSH or other raw TCP
  egress, so server work has to run in GitHub Actions.
- On 2026-10-30 the environment's network access was effectively **Full**: besides every host the
  brief lists (`wordpress.org`, `api.wordpress.org`, `downloads.wordpress.org`, `wpackagist.org`,
  `repo.packagist.org`, `packagist.org`, `getcomposer.org`, `registry.npmjs.org`,
  `cdn.playwright.dev`, `playwright.download.prss.microsoft.com`, Docker Hub, `mirror.gcr.io`,
  `public.ecr.aws`, `raw.githubusercontent.com`, `deployer.org`, `api.cloudflare.com`,
  `api.postmarkapp.com`, `api.stripe.com`, `ppa.launchpadcontent.net`, `keyserver.ubuntu.com`,
  `archive.ubuntu.com` over HTTP), unrelated hosts such as `example.org` and `pypi.org` answer too.
- `cigarholic.com` and `max2lab.com` fail with `CONNECT ... 502`: that is NXDOMAIN (both domains
  are on registrar hold, see `docs/runbooks/secrets.md`), not a policy denial. An earlier session,
  under a custom allowlist, saw the VPS IP refused with "Host not in allowlist"; under Full access
  it should be reachable (untested, the IP is only in the secrets).
- GitHub zip downloads (`codeload.github.com`, `api.github.com/.../zipball`) return 403 for
  repositories not attached to the session, so Composer falls back to `git clone` for
  GitHub-hosted packages. That works, but the first install of each package is slower.

## GitHub from the sandbox

- Pushing branches works. The proxy rejects tag pushes and branch deletions, so tags for production
  releases come from the owner or from a workflow.
- GraphQL is blocked, so `gh pr ...` and `gh issue ...` fail, and `gh auth status` even reports the
  token as invalid. REST through `gh api repos/cuong-tran/cigarholic/...` and the GitHub MCP tools
  work. Only repository-scoped endpoints are available (`users/...` and similar are refused).
- Worked: creating a PR (GitHub MCP), triggering `workflow_dispatch` (MCP `actions_run_trigger`),
  listing runs and jobs, reading job logs (`gh api repos/cuong-tran/cigarholic/actions/jobs/<id>/logs`),
  merging a PR (MCP `merge_pull_request`, squash; verified on the Turn 1 PR, 2026-10-30).
- Refused with 403 ("not permitted through this proxy"): `repos/.../environments`,
  `repos/.../actions/permissions`, `repos/.../actions/secrets`, `repos/.../actions/variables`.
  Secrets and variables can be neither read nor set from the sandbox.
- A `workflow_dispatch` workflow can be triggered only once its file is on `main`.

## PHP 8.5 on the host (optional)

The VPS runs PHP 8.5. The compose stack provides 8.5 for the store runtime, so the host normally
stays on 8.3. If the host ever needs 8.5 too (for example to run PHPStan with the 8.5 stubs), the
ondrej/php archive for noble works and installs in about 25 seconds:

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

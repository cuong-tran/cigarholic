# VPS and cloud environment setup

How the pieces connect, and the one-time steps only the owner can do. After this, Claude can
develop locally in its cloud session, deploy to staging and production, and configure the sites.

## How it works

```text
Claude cloud session ──git push / run workflow──▶ GitHub ──GitHub Actions over SSH──▶ VPS
 (bin/dev: local WP)                                                                 ├─ staging     /srv/www/cigarholic-staging
 (curl / Playwright ─────────────── HTTPS ────────────────────────────────────────▶ └─ production  /srv/www/cigarholic
```

The cloud session can't open SSH connections; its traffic goes through an HTTPS proxy. So every
server action runs in GitHub Actions, and the SSH keys live in GitHub secrets, not in the session.

| Workflow | Runs as | Does |
| --- | --- | --- |
| Server (`server.yml`) | root (`VPS_ROOT_KEY`) | `bootstrap`: Nginx, PHP 8.3-FPM, MariaDB, Redis, WP-CLI, Certbot, ufw, fail2ban, auto security updates. `add-site`: site user, database, `.env`, PHP pool, Nginx vhost, Let's Encrypt, WP-Cron. `status`: health report |
| Deploy (`deploy.yml`) | site user (`VPS_DEPLOY_KEY`) | Builds with Composer, uploads a release, backs up the database, switches atomically, migrates, runs `provision/`, smoke tests, rolls back by itself on failure |
| WP-CLI (`wp.yml`) | site user | Runs WP-CLI commands on staging or production |

Each site runs as its own Linux user with its own PHP pool, so staging can't touch production files.
Releases live in `releases/<timestamp>-<sha>/`; `current` points at the live one; the last 5
releases and 10 pre-deploy database dumps are kept.

## 1. VPS and DNS

1. A VPS running **Ubuntu 24.04** (2 vCPU / 4 GB or more) with root SSH access.
2. DNS records pointing at the VPS IP: `staging.<domain>`, `<domain>` and `www.<domain>`.
   With Cloudflare in front, set SSL/TLS mode to **Full (strict)**. "Flexible" causes a redirect loop.

## 2. Keys (on your computer)

Two dedicated keys: one for server administration as root, one for deploys.

```bash
ssh-keygen -t ed25519 -N '' -C cigarholic-actions-root -f ~/.ssh/cigarholic_root
```

```bash
ssh-keygen -t ed25519 -N '' -C cigarholic-actions-deploy -f ~/.ssh/cigarholic_deploy
```

```bash
ssh-copy-id -i ~/.ssh/cigarholic_root.pub root@VPS_IP
```

```bash
ssh-keyscan VPS_IP > ~/cigarholic_known_hosts
```

Only the root key goes on the server by hand. The Server workflow installs the deploy key for
the site users.

## 3. GitHub secrets

Repository secrets (Settings → Secrets and variables → Actions), or with the `gh` CLI:

```bash
gh secret set VPS_HOST --repo cuong-tran/cigarholic --body "VPS_IP"
```

```bash
gh secret set VPS_ROOT_KEY --repo cuong-tran/cigarholic < ~/.ssh/cigarholic_root
```

```bash
gh secret set VPS_DEPLOY_KEY --repo cuong-tran/cigarholic < ~/.ssh/cigarholic_deploy
```

```bash
gh secret set VPS_KNOWN_HOSTS --repo cuong-tran/cigarholic < ~/cigarholic_known_hosts
```

```bash
gh secret set STAGING_HTTP_AUTH --repo cuong-tran/cigarholic --body "cigarholic:CHOOSE-A-PASSWORD"
```

| Name | Kind | Required | Purpose |
| --- | --- | --- | --- |
| `VPS_HOST` | secret | yes | Server IP or hostname |
| `VPS_DEPLOY_KEY` | secret | yes | Deploys and WP-CLI as the site users |
| `VPS_ROOT_KEY` | secret | for the Server workflow | Root (or sudo user) administration. Delete it when you no longer want Actions to have root; deploys keep working |
| `VPS_KNOWN_HOSTS` | secret | recommended | Pins the server's host key. Without it the key is trusted on first use |
| `STAGING_HTTP_AUTH` | secret | recommended | `user:password` for the staging password prompt. Without it one is generated and saved on the server |
| `VPS_SSH_PORT` | variable | if not 22 | SSH port |
| `VPS_ADMIN_USER` | variable | if not root | A user with passwordless sudo, used instead of root |

These are repository-level on purpose: on GitHub Free, private repositories get no environment
secrets or required reviewers. The production gate is that only a published release or a manual
Deploy run with `target: production` reaches production, and `AGENTS.md` tells agents to do that
only when you ask.

## 4. Claude cloud environment (claude.ai/code → environment → Edit)

- **Setup script:** paste the contents of `bin/cloud-env-setup.sh`. It adds shellcheck and
  actionlint and warms the Composer cache. PHP 8.3, Composer, WP-CLI, MariaDB, Redis, Node and
  Playwright are already in the image.
- **Environment variables:**

  ```text
  COMPOSER_ALLOW_SUPERUSER=1
  WP_CLI_ALLOW_ROOT=1
  ```

- **Network access:** Full works as is. With Trusted or Custom, allow `wordpress.org`,
  `*.wordpress.org`, `repo.wp-packages.org`, `packagist.org`, `repo.packagist.org`,
  `getcomposer.org` and your own domain (so Claude can check staging and production).
- **Network secret (optional):** host `staging.<domain>`, type "Username and password", value from
  `STAGING_HTTP_AUTH`. Claude can then open staging without seeing the password.

## 5. First run

The workflows have to be on `main` before they can be run, so merge the setup PR first. Then
run these from the Actions tab (Run workflow), or ask Claude to run them:

1. **Server**, `action: bootstrap`.
2. **Server**, `action: add-site`, `site: staging`, `domain: staging.<domain>`, `email: <you>`.
3. **Server**, `action: add-site`, `site: production`, `domain: <domain>`, `alias: www.<domain>`, `email: <you>`.
4. **Deploy**, `target: staging`. After this, every push to `main` deploys staging.
5. Read the WordPress admin passwords on the server, log in, and change them:

   ```bash
   ssh -i ~/.ssh/cigarholic_root root@VPS_IP 'cat /root/wp-credentials/*.txt'
   ```

6. Once you can log in with the key, optionally run **Server**, `action: bootstrap`, `harden_ssh: true`
   to turn off SSH password logins.

Production goes live by publishing a GitHub release (e.g. tag `v0.1.0`), or a manual **Deploy**
run with `target: production`.

## Running the server scripts by hand

Everything the Server workflow does also works from a root shell on the VPS:

```bash
scp -r server root@VPS_IP:cigarholic-server
```

```bash
ssh root@VPS_IP 'bash cigarholic-server/bootstrap.sh'
```

```bash
ssh root@VPS_IP 'bash cigarholic-server/add-site.sh --help'
```

## Troubleshooting

| Symptom | Fix |
| --- | --- |
| `add-site` warns "certificate request failed" | DNS doesn't point at the VPS yet (or Cloudflare is in Flexible mode). Fix DNS, then run `add-site` again |
| Browser shows a certificate warning | Same as above; until the certificate is issued Nginx serves a self-signed one |
| Deploy failed with "rolled back" | The new release broke the site and the previous one is live again. Read the job log; the pre-deploy dump is in `shared/backups/` |
| Need to undo a deploy | **Deploy**, `action: rollback`. Code only; restore the database from `shared/backups/` if a migration must be undone |
| Deploy says secrets are not set | Add `VPS_HOST` and `VPS_DEPLOY_KEY` (section 3) |
| `Permission denied (publickey)` on deploy | Run **Server** `add-site` again after setting `VPS_DEPLOY_KEY`; it installs the key for the site user |

# Deploy

Deployer 8 recipe `infra/deploy/deploy.php`, run by GitHub Actions over SSH (OPS-04, OPS-05). Both
sites are atomic releases under `/var/www/<site>/{releases,shared,current}`; `shared/.env` and
`shared/web/app/uploads` survive deploys; five releases are kept.

## Staging: automatic

Every push to `main` that touches `composer.*`, `config/`, `web/`, `provision/` or the deploy tooling
runs `deploy-staging.yml` (Environment `staging`, concurrency group `deploy-staging`). Manual run:

```bash
gh workflow run deploy-staging.yml --ref main
gh run list --workflow=deploy-staging.yml --limit 3
gh run view <run-id> --log
```

Steps: upsert the application keys into `shared/.env` (`infra/server/env-upsert.sh`, see
`secrets.md`), then `dep deploy staging --revision=<sha>`: new release from the Git mirror on the
server, `composer install --no-dev --prefer-dist --no-interaction`, `wp core update-db`,
`wp wc update`, `wp cigarholic provision` (warning while the mu-plugin does not exist),
`wp redis enable`, symlink switch, PHP-FPM reload, FastCGI cache purge, `wp cache flush`, health check
(HTTP 200 from the site through Nginx on the server) with automatic rollback on failure.

## Production: from a tag, with approval

```bash
git tag -a v1.2.0 -m "Release 1.2.0" && git push origin v1.2.0   # the owner (tag pushes are refused from the sandbox)
```

`deploy-production.yml` starts, waits for the owner's approval on the `production` Environment, then
runs the same steps plus `infra/backup/snapshot.sh production` (database dump to
`shared/backups/pre-deploy-<timestamp>.sql.gz`) before the release switch, and the Cloudflare purge
when the token is set. Fallback the owner runs by hand:

```bash
gh workflow run deploy-production.yml -f tag=v1.2.0
```

A branch name is refused in the workflow and again in the recipe (`cigarholic:guard`).

## First deploy of a site

1. `infra-provision.yml` → `provision`, then `site-staging` / `site-production` (creates user,
   directories, `.env`, database, PHP-FPM pool, Nginx, certificate).
2. Deploy. WordPress is not installed yet, so the WP tasks are skipped with a warning.
3. Install WordPress with `wp-cli.yml` (site `staging`, command below). The workflow exports
   `WP_OWNER_EMAIL` and `WP_OWNER_INITIAL_PASSWORD` on the server for this one command, so the
   password never appears in the form or the log:

   ```bash
   core install --url=https://staging.cigarholic.com --title=Cigarholic --admin_user=owner --admin_email="$WP_OWNER_EMAIL" --admin_password="$WP_OWNER_INITIAL_PASSWORD" --skip-email
   ```

   The owner changes the password at first login; 2FA is enforced by the Two Factor plugin.
4. Deploy again: `update-db`, `wc update`, `provision` and the object cache now run.

## From a laptop or the VPS

```bash
scripts/tools.sh
DEPLOY_HOST=<ip> DEPLOY_USER=deploy php tools/deployer.phar --file=infra/deploy/deploy.php deploy staging --branch=main
DEPLOY_HOST=<ip> DEPLOY_USER=deploy php tools/deployer.phar --file=infra/deploy/deploy.php tree deploy
```

The recipe refuses to run without `DEPLOY_HOST`; `DEPLOY_USER=root` makes every command run as
`deploy` through `sudo`. Known hosts are pinned (`StrictHostKeyChecking=yes`).

## What a deploy changes on the server

| Path | Owner | Written by |
| --- | --- | --- |
| `/var/www/<site>/releases/<n>` | deploy | Deployer (git archive + composer) |
| `/var/www/<site>/current` | deploy | symlink switch |
| `/var/www/<site>/shared/.env` | deploy:cigarholic-<site> 0640 | `site.sh` once, `env-upsert.sh` each deploy |
| `/var/www/<site>/shared/web/app/uploads`, `shared/logs` | deploy + PHP-FPM user (ACL) | WordPress |
| `/var/www/<site>/shared/backups` | deploy | `snapshot.sh`, `backup.sh` |
| `/var/cache/nginx/cigarholic-<site>` | www-data | emptied by `cigarholic-cache-purge` |

Related: `rollback.md`, `health-check.md`, `troubleshooting.md`.

# Rollback

One command switches `current` back to the previous release (OPS-05). Five releases are kept, so up
to four steps back are possible. The database is not rolled back automatically: WordPress schema
updates are backward compatible for a point release, and production has a pre-deploy dump.

## Automatic

`cigarholic:health` runs right after the symlink switch: if the home page does not answer
200/30x through Nginx on the server, the recipe runs `rollback`, reloads PHP-FPM, purges the FastCGI
cache and fails the workflow with the HTTP code it saw. The failed release is marked `BAD_RELEASE`
and skipped by later rollbacks.

## Manual

From a laptop or the VPS (the same recipe the workflows use):

```bash
scripts/tools.sh
DEPLOY_HOST=<ip> DEPLOY_USER=deploy php tools/deployer.phar --file=infra/deploy/deploy.php rollback production
DEPLOY_HOST=<ip> DEPLOY_USER=deploy php tools/deployer.phar --file=infra/deploy/deploy.php releases production
```

Without Deployer, on the server as `deploy`:

```bash
cd /var/www/production && ls -1 releases/ && readlink current
ln -nfs --relative releases/<previous> current && sudo -n systemctl reload php8.5-fpm && sudo -n /usr/local/sbin/cigarholic-cache-purge production
```

From GitHub without SSH: re-run `deploy-production.yml` with the previous tag (`-f tag=vX.Y.Z`); it is
a forward deploy of the old code and goes through the same approval.

## Database

If a deploy changed the schema and the old code cannot read it (rare: WooCommerce and WordPress keep
compatibility within a minor), restore the pre-deploy dump taken before the switch:

```bash
ls -1t /var/www/production/shared/backups/pre-deploy-*.sql.gz | head -n 3
```

Then follow `backup-restore.md`, "Restore a local dump". This is a destructive operation on
production: the owner decides (AGENTS.md approval gates).

## After a rollback

- `health-check.md`: home, a product page, `/wp/wp-login.php`, checkout as a guest.
- Note the failed tag and the cause in `docs/STATUS.md`; the fix ships as a new tag, never by
  editing the server.

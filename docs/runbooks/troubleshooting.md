# Troubleshooting

Symptoms, causes and the command that fixes them. Everything runs through the workflows
(`infra-provision.yml`, `wp-cli.yml`, `backup-restore.yml`) or on the VPS; never edit a server file
by hand, change the template in `infra/` and re-run.

## Workflows

| Symptom | Cause | Fix |
| --- | --- | --- |
| `secret SSH_HOST is empty` | repository secret missing or misnamed | `docs/runbooks/secrets.md`; names are exact |
| `Host key verification failed` | `SSH_KNOWN_HOSTS` does not match the server (reinstall, new IP) | owner re-runs `ssh-keyscan -H -p 22 <ip>` and updates the secret; never set `StrictHostKeyChecking=no` |
| `Permission denied (publickey)` | key not installed for `SSH_USER`; or `SSH_USER=deploy` before `provision` ran | run `provision` as root first; check `SSH_PRIVATE_KEY` is the full key including BEGIN/END |
| `sudo: a password is required` | `--sudo` action while `SSH_USER=deploy` | provisioning actions need `SSH_USER=root` (`server.md`, Users) |
| `DEPLOY_HOST is not set` | recipe run without the env | export `DEPLOY_HOST` (the `SSH_HOST` value) |
| `shared/.env is missing` | site not provisioned | `infra-provision.yml` → `site-staging` / `site-production` |
| `Production deploys only from a release tag` | branch or wrong tag format | tag `vX.Y.Z` and push it (owner) |
| Deploy waits forever | `production` Environment approval pending | owner approves in Actions → the run |
| `WordPress is not installed in this database yet` | first deploy | `wp-cli.yml` → `core install ...` (`deploy.md`) |

## Deploy and site

| Symptom | Cause | Fix |
| --- | --- | --- |
| Health check fails, automatic rollback | PHP fatal in the new release, missing plugin after `composer install`, `.env` key missing | `wp-cli.yml` → `eval 'echo 1;'`; read `shared/logs/php-error.log` and `/var/log/nginx/cigarholic-<site>.error.log` (status action shows line counts); fix in a PR, deploy again |
| 502 Bad Gateway | PHP-FPM pool down or socket name mismatch | `site-<site>` re-renders the pool and vhost; `systemctl status php8.5-fpm` |
| 503 / slow | pool saturated (`pm.max_children` reached, see `php-slow.log`), or MariaDB down | status action; raise `PM_MAX` in `site.sh` by PR; check `innodb_buffer_pool_size` fits |
| Stale page after a change | FastCGI cache | the deploy purges it; by hand `sudo -n /usr/local/sbin/cigarholic-cache-purge <site>`; `X-Cache: BYPASS` is expected on cart/checkout/account/logged-in |
| Logged-in user sees cached page | cookie not in the bypass map | add it to `cigarholic-common.conf` (`$cigarholic_skip_cookie`) |
| 429 Too Many Requests on login or checkout | Nginx `limit_req` (10/min login, 1/s checkout) or Cloudflare rate limit | expected under brute force; for a real user raise the burst in `site.conf.tpl` by PR |
| `wp redis enable` failed warning | Valkey down or `php8.5-redis` missing | `systemctl status valkey-server`; `php -m | grep redis` |
| Object cache mixes sites | `WP_REDIS_PREFIX` identical | `.env` has `cigarholic_staging` / `cigarholic_production`; `env-upsert.sh <site> WP_REDIS_PREFIX=...` |
| Mail not sent on staging | `POSTMARK_SERVER_TOKEN` empty in `.env` | set `STAGING_POSTMARK_SERVER_TOKEN`, re-run the staging deploy (upsert happens first) |
| Staging asks for a password on webhooks | path not in the `$cigarholic_staging_auth` map | add the exact path to `cigarholic-common.conf`; Stripe uses `?wc-api=wc_stripe`, PayPal `/wp-json/paypal/v1/incoming` |

## TLS and DNS

| Symptom | Cause | Fix |
| --- | --- | --- |
| Browser warns about the certificate | self-signed placeholder: DNS did not point at the VPS when `site.sh` ran | create the A records (`server.md`), wait for propagation, re-run `site-<site>` |
| `certbot failed` in the site action | port 80 blocked, DNS still propagating, or rate limit | `curl -sI http://<host>/.well-known/acme-challenge/test` must reach Nginx (404 from the site is fine); retry later |
| Renewal failed email | `certbot.timer` could not reach ACME or the webroot moved | `sudo certbot renew --dry-run`; the renewal hook reloads Nginx |
| `www` or `max2lab` shows a certificate error | these hosts were not resolving when the production certificate was issued | after DNS, `site-production` again (`--expand` adds them) |

## Backups

| Symptom | Cause | Fix |
| --- | --- | --- |
| `cigarholic-backup.service` failed with exit 2 | B2 credentials not on the server yet | run `backup-restore.yml` → `backup-production` once as root (writes `/etc/cigarholic/backup.env`) |
| `upload verification failed` | B2 key restricted to another bucket, wrong endpoint | check `B2_S3_ENDPOINT` matches the bucket's region; key must have list/read/write on the bucket |
| Restore drill: images missing on staging | `RESTORE_SKIP_UPLOADS=1`, or the uploads tar was large and the run timed out | re-run `restore-production-to-staging`; the workflow has a 90-minute limit |
| Restore drill: staging charges real cards | live keys restored with the database | `restore.sh` deletes them and forces test mode; verify under WooCommerce → Payments before testing |

## Server

| Symptom | Cause | Fix |
| --- | --- | --- |
| Locked out after `disable-root-login` | `SSH_USER` still `root` | set `SSH_USER=deploy`; if the deploy key is broken, use the provider console as root and run `provision.sh --sshd-only` |
| Own IP banned by fail2ban | repeated failed logins | `sudo fail2ban-client set <jail> unbanip <ip>` from the console; `ignoreip` is not used on purpose |
| Disk full | releases or local dumps | `deploy:cleanup` keeps 5 releases; `shared/backups/nightly-*` keeps 3 days; `du -sh /var/www/*/shared/backups /var/cache/nginx` |
| `apt` wants a reboot after a kernel update | unattended-upgrades never reboots | owner schedules `sudo reboot` off-hours; services are enabled and come back |

## Where the logs are

- Nginx: `/var/log/nginx/cigarholic-<site>.access.log` (format: combined + `cache= rt=`), `.error.log`
- PHP: `/var/www/<site>/shared/logs/php-error.log`, `php-slow.log`
- WordPress (staging only): `/var/www/<site>/shared/logs/debug.log`; WooCommerce → Status → Logs (`cigarholic` source)
- Deploys: GitHub Actions run logs (`gh run view <id> --log`); `/var/www/<site>/.dep/releases_log`
- Backups: `journalctl -u cigarholic-backup`; `/var/www/production/shared/backups/last-backup.txt`
- fail2ban: `/var/log/fail2ban.log`; MariaDB slow queries: `/var/log/mysql/mariadb-slow.log`

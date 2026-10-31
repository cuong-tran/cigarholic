# Server

One Hostinger VPS (US), Ubuntu 26.04 LTS, 16 GB RAM, provisioned only by `infra/server/` scripts run
from `infra-provision.yml` (OPS-01; ADR 0001). No panel. Staging and production are two sites on it
with separate system user, database, database user, PHP-FPM pool, `.env`, Nginx vhost and cache.

## What runs where

| Component | Package (Ubuntu 26.04 archive) | Config written by | Notes |
| --- | --- | --- | --- |
| Nginx 1.28 | `nginx` | `provision.sh` (common, default catch-all), `site.sh` (vhosts) | `/etc/nginx/conf.d/cigarholic-*.conf`, `sites-available/cigarholic-<site>`, snippets `cigarholic-*.conf`; FastCGI cache in `/var/cache/nginx/cigarholic-<site>` |
| PHP 8.5-FPM | `php8.5-fpm` + mysql, curl, gd, intl, mbstring, xml, zip, bcmath, imagick, redis, soap (opcache in php-common) | `provision.sh` (ini), `site.sh` (pool) | pool `cigarholic-<site>`, socket `/run/php/cigarholic-<site>.sock`, user `cigarholic-<site>`; production 24 workers, staging 10 |
| MariaDB 11.8 | `mariadb-server` | `provision.sh` | `127.0.0.1` only, root via unix socket, `cigarholic_<site>` database and user, 2 GB buffer pool |
| Valkey 9 | `valkey-server` (service `valkey-server`) | `provision.sh` | `127.0.0.1:6379`, 1 GB LRU, no persistence; `WP_REDIS_PREFIX` per site |
| certbot | `certbot` | `site.sh` (HTTP-01 webroot), renewal hook reloads Nginx | certificate name `cigarholic-<site>`; self-signed placeholder until DNS resolves |
| ufw | `ufw` | `provision.sh` | allow `SSH_PORT`, 80, 443; deny the rest |
| fail2ban | `fail2ban` | `provision.sh` | jails sshd, nginx-http-auth, nginx-botsearch, nginx-limit-req, nginx-bad-request, wp-login (`infra/server/fail2ban/`) |
| unattended-upgrades | `unattended-upgrades` | `provision.sh` | security pockets only, no automatic reboot |
| rclone | `rclone` | — | B2 uploads (`infra/backup/backup.sh`) |
| WP-CLI 2.12, Composer 2.8 | phars in `/usr/local/bin` | `provision.sh` | checksum verified |
| Backup timer | `cigarholic-backup.timer` 03:17 UTC | `provision.sh` | `/usr/local/bin/cigarholic-backup production` |

Paths: `/var/www/<site>/{releases,shared,current,letsencrypt}`; logs in `/var/log/nginx/cigarholic-<site>.*.log`
(fail2ban reads these) and `/var/www/<site>/shared/logs/` (WordPress `debug.log`, `php-error.log`,
`php-slow.log`, rotated daily, 14 kept).

## Users

| User | Purpose | Can |
| --- | --- | --- |
| `root` | provisioning (`provision`, `site-*`, `disable-root-login` actions) | everything; key only |
| `deploy` | Deployer, WP-CLI, backups, `env-upsert.sh` | owns `/var/www/<site>`; `sudo` only for `systemctl reload php8.5-fpm|nginx`, `nginx -t`, `certbot`, `cigarholic-cache-purge <site>` |
| `cigarholic-staging`, `cigarholic-production` | PHP-FPM pool | read the release, write `shared/web/app/uploads`, `shared/logs`, `shared/sessions` (ACLs) |
| `www-data` | Nginx | read the release, write its cache |

`SSH_USER` stays `root` until the owner has verified a deploy as `deploy`; `disable-root-login` then
closes root (it refuses while the deploy user cannot log in). Provisioning actions need root, so run
`provision` and `site-*` before closing it; afterwards they need the provider console.

## Firewall and edge

- Inbound: `SSH_PORT`, 80, 443. Everything else dropped by ufw. Outbound open (apt, GitHub, Packagist,
  wordpress.org, Postmark, Stripe, PayPal, B2).
- Nginx: `xmlrpc.php` denied, `wp-login.php` limited to 10 requests/minute per IP (burst 5),
  `/checkout/` and the Store API checkout route to 1 request/second (burst 20), unknown hosts get
  444, HTTP → HTTPS, `www.cigarholic.com`, `max2lab.com`, `www.max2lab.com` → `https://cigarholic.com`
  in one 301 hop, security headers, FastCGI cache bypassed for cart, checkout, account, logged-in
  users, REST and `wc-api`.
- Staging: HTTP basic auth (`STAGING_BASIC_AUTH_*`) except `/.well-known/`, `/wc-api/`,
  `?wc-api=` (Stripe webhooks), `/wp-json/paypal/v1/` (PayPal webhooks), `/wp-json/wc-stripe/` and
  `/wp-json/wc/v3/webhooks` (precaution); `X-Robots-Tag: noindex` on every response.
- Cloudflare is additive (ADR 0008): when it arrives, `cloudflare.yml` adds the same rules at the edge.

## DNS records the owner creates at the registrar (while Cloudflare is deferred)

Enter these at iNET once `clientHold` is lifted (`docs/OWNER-ACTIONS.md` A5), replacing `<VPS_IP>`
with the address in the `SSH_HOST` secret. TTL 300 while things settle.

| Zone | Type | Name | Value |
| --- | --- | --- | --- |
| cigarholic.com | A | `@` | `<VPS_IP>` |
| cigarholic.com | A | `www` | `<VPS_IP>` |
| cigarholic.com | A | `staging` | `<VPS_IP>` |
| max2lab.com | A | `@` | `<VPS_IP>` |
| max2lab.com | A | `www` | `<VPS_IP>` |

No AAAA records (the vhosts listen on IPv6 but the VPS address is IPv4; add one only if the provider
assigns a v6 address). Postmark, Search Console and Bing records are added later (`secrets.md`).
After the records resolve, run `infra-provision.yml` → `site-staging` and `site-production` again:
`site.sh` sees the hostnames pointing at the server and requests the Let's Encrypt certificates
(production: one certificate for all four hosts).

## Day-to-day

```bash
gh workflow run infra-provision.yml -f action=status          # versions, services, disk, certificates, last deploy per site
gh workflow run infra-provision.yml -f action=provision       # re-run any time; idempotent
gh workflow run infra-provision.yml -f action=site-staging    # after DNS changes (certificate) or template changes
gh workflow run wp-cli.yml -f site=staging -f command="plugin list --status=active"
```

Changing a template under `infra/server/` is a PR; the workflow uploads `infra/` fresh on every run.

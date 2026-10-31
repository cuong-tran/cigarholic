# Health check

What "the site is up" means, and how to check it after a deploy, a rollback, a restore or an alert.
The deploy itself checks the home page through Nginx on the server and rolls back on failure
(`rollback.md`); this is the fuller check.

## From anywhere (once DNS resolves)

```bash
curl -sS -o /dev/null -w '%{http_code} %{time_total}s cache=%{header_json}\n' https://cigarholic.com/
curl -sS -u "$STAGING_BASIC_AUTH_USER:$STAGING_BASIC_AUTH_PASSWORD" -o /dev/null -w '%{http_code}\n' https://staging.cigarholic.com/
curl -sSI https://www.cigarholic.com/some/path?x=1 | grep -i '^location:'      # one hop to https://cigarholic.com/some/path?x=1
curl -sSI https://max2lab.com/ | grep -i '^location:'
curl -sSI http://cigarholic.com/ | grep -i '^location:'                         # https
curl -sS -o /dev/null -w '%{http_code}\n' https://cigarholic.com/xmlrpc.php    # 403
curl -sS -o /dev/null -w '%{http_code}\n' https://cigarholic.com/wp-json/wc/store/v1/products   # 200, Store API alive
echo | openssl s_client -servername cigarholic.com -connect cigarholic.com:443 2>/dev/null | openssl x509 -noout -enddate
```

Expected: 200 on the home pages, `X-Cache: HIT` on the second request of a public page, `MISS` or
`BYPASS` on cart/checkout/account, 301 for the redirect hosts, 403 for `xmlrpc.php`, certificate
valid for more than 14 days.

## From the server (works before DNS, through the workflows)

```bash
gh workflow run infra-provision.yml -f action=status
gh workflow run wp-cli.yml -f site=staging -f command="core verify-checksums"
gh workflow run wp-cli.yml -f site=staging -f command="wc --user=1 tool list"     # WooCommerce status tools
gh workflow run wp-cli.yml -f site=staging -f command="cron event list"
gh workflow run wp-cli.yml -f site=staging -f command="redis status"
```

On the VPS directly:

```bash
curl -sS -o /dev/null -w '%{http_code}\n' --resolve staging.cigarholic.com:443:127.0.0.1 -k https://staging.cigarholic.com/
sudo systemctl status nginx php8.5-fpm mariadb valkey-server fail2ban --no-pager
sudo tail -n 50 /var/log/nginx/cigarholic-production.error.log /var/www/production/shared/logs/php-error.log
sudo fail2ban-client status wp-login
```

## Store-level checks (staging after each deploy, production after each tag)

1. Home page renders, no PHP notices in `shared/logs/debug.log` (staging has `WP_DEBUG_LOG`).
2. A product page, add to cart, cart, checkout as guest with a Stripe test card (staging) or until
   the payment step (production, coming-soon still on): no 429 from the rate limits for one user.
3. `/wp/wp-login.php` loads; a wrong password returns to the form; 2FA prompt for admins.
4. WooCommerce → Status: no red rows; Action Scheduler has no stuck past-due actions.
5. Order emails arrive (Postmark activity on production, sandbox server on staging).
6. `wp cigarholic catalog:check` passes on production (no `SAMPLE-` SKUs).

## Monitoring (UptimeRobot, owner account; B3)

Monitors: `https://cigarholic.com/` (keyword from the coming-soon page, then the home page),
`https://cigarholic.com/checkout/` (expects 200), SSL expiry on `cigarholic.com` and
`staging.cigarholic.com` (staging needs the basic auth user in the monitor URL). Alerts go to the
owner and the agent channel.

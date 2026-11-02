# Monitoring (OPS-06)

External uptime, checkout-endpoint and certificate-expiry monitoring with UptimeRobot (owner
account, `docs/OWNER-ACTIONS.md` B3), plus the signals the server already produces. Alerts reach
the owner only: the agent has no inbox, so the owner relays an alert into the session, and the
agent looks at the site through the workflows below.

What the monitors prove: that the site answers from the public internet, on its real hostname,
with a valid certificate, with the right content on the home page, and that the checkout page and
the Store API answer at all. They do not prove that an order can be placed; that is the Playwright
smoke suite (`make e2e`) and the store-level checks in `health-check.md`.

## What is monitored and why

| Monitor (friendly name) | Type | URL | Up means | Why |
| --- | --- | --- | --- | --- |
| `cigarholic-production-home` | Keyword, keyword must exist | `https://cigarholic.com/` | 2xx and the body contains `Handcrafted in Vietnam` | Catches a down site, a wrong vhost (Nginx's default server answers 444 to unknown hosts, so an unknown host is "down"), a PHP error page and a hijacked or placeholder page, not only 5xx. Also carries the **SSL expiry reminder** for `cigarholic.com` |
| `cigarholic-production-checkout` | HTTP(s) | `https://cigarholic.com/checkout/` | 2xx or 3xx | Checkout is the page that earns money. It is served without the FastCGI cache (`cigarholic-common.conf` bypass map) and sits behind the Nginx rate limit, so it exercises PHP-FPM and WooCommerce routing on every check; the home page can be served from cache while PHP is dead |
| `cigarholic-production-store-api` | HTTP(s) | `https://cigarholic.com/wp-json/wc/store/v1/products` | 2xx | The block cart and checkout, the mini cart and the product grids talk to the Store API; a broken REST layer (permalinks, a plugin fatal on `rest_api_init`, a WAF rule) breaks checkout while the pages still render |
| `cigarholic-staging-home` | Keyword, HTTP basic auth | `https://staging.cigarholic.com/` | 2xx with the keyword, through basic auth | Staging is where every deploy lands first; a dead staging blocks the release train. Carries the **SSL expiry reminder** for `staging.cigarholic.com` |

SSL expiry is not a monitor of its own: UptimeRobot checks the certificate on every HTTPS monitor
(a certificate error is "down") and sends **expiry reminders** when it is about to run out. The two
home monitors cover both hostnames. Let's Encrypt stopped sending its own expiry emails in 2025, so
these reminders are the only external warning that `certbot.timer` on the VPS has stopped renewing.

### The keyword and the coming-soon phase

Until the owner says "turn coming-soon off" (`OWNER-ACTIONS.md` C5), every front-end request on
production renders the coming-soon template (WooCommerce's whole-site coming-soon mode, provisioning
step `FND-03`), so the keyword must be on that page **and** on the real home page afterwards:

- `web/app/themes/cigarholic/templates/coming-soon.html` has a footer paragraph starting with
  "Handcrafted in Vietnam, shipped from Texas."
- `web/app/themes/cigarholic/parts/footer.html`, used by `front-page.html` and the other templates,
  has the same sentence.

So the keyword is `Handcrafted in Vietnam`, matched case-insensitively. It changes only if both
files change; whoever edits the footer wording updates `KEYWORD` in
`infra/monitoring/uptimerobot.sh` and this page in the same PR, then re-runs the workflow.

Verified on the Compose stack with coming-soon on (`make up`, 2026-11-02): `/`, `/shop/` and
`/cart/` answer 200 with the coming-soon template and the keyword once in the footer;
`/checkout/` answers **302 to `/cart/`** (WooCommerce's empty-cart redirect runs on
`template_redirect`, before the coming-soon template is chosen) and the final page is 200, both
before and after launch; `/wp-json/wc/store/v1/products` answers 200 with the product list even
while coming-soon is on (REST requests never reach `template_include`, where the coming-soon
handler lives). UptimeRobot follows redirects and treats the final 2xx as up, so the checkout
monitor is "up" in both phases; it turns red on a 5xx, a timeout or a certificate error.

## Interval, timeout, confirmation

- Every **5 minutes** (300 s, the Free plan minimum; `UPTIMEROBOT_INTERVAL` overrides it on a paid
  plan), timeout 30 s, method GET.
- UptimeRobot re-checks a failing monitor from another location before it alerts, so a single
  slow response does not page the owner. Expect a "down" alert roughly 5 to 10 minutes after the
  site really stops answering, and an "up" alert when it recovers.
- The checks come from UptimeRobot's published addresses
  (`https://uptimerobot.com/inc/files/ips/IPv4.txt`). They stay well under the Nginx limits
  (`/checkout/` 1 request/second per IP). If Cloudflare's WAF ever challenges them (ADR 0008, later),
  allow that list in `infra/cloudflare/waf.sh`.

## Alert routing

- **Owner:** email, plus the UptimeRobot mobile app's push notifications (free) or SMS / voice
  (paid credits). Add the contacts under *Alert contacts* in the dashboard and **confirm the email**
  there; an unconfirmed contact (status "not activated") receives nothing.
- By default the script attaches **every active alert contact on the account** to every monitor.
  To restrict that, set the repository variable `UPTIMEROBOT_ALERT_CONTACTS` to the contact ids,
  `123-456` (the workflow's *list* run prints the ids; `docs/runbooks/secrets.md`).
- **Agent:** nothing. There is no inbox or chat channel the sandbox can read. When an alert fires,
  the owner pastes it into the session (monitor name, time, reason text), or runs the first
  check below themselves.
- The nightly backup, certbot and fail2ban do not alert anybody by themselves; they are read with
  `infra-provision.yml` → `status` (see "Server-side signals").

## When an alert fires

Always first: open the alert's reason (UptimeRobot shows the HTTP status, "keyword not found",
"connection timeout" or the certificate error), then run the external checks in
`health-check.md` ("From anywhere") and `infra-provision.yml` → `status`. Then:

| Alert | Likely causes | What to do |
| --- | --- | --- |
| `*-home` **down, HTTP 5xx** | PHP-FPM pool down, MariaDB down, a fatal in the current release | `troubleshooting.md` "Deploy and site" (502 / 503 rows). If it started right after a deploy: `rollback.md`; the deploy itself already rolls back when its own health check fails |
| `*-home` **down, connection timeout / refused** | VPS down or rebooting, ufw change, Nginx not running, DNS change | Provider console for the VPS state, then `status`; `troubleshooting.md` "Server". With Cloudflare in front, check the Cloudflare status page first |
| `*-home` **keyword not found** (site answers, text missing) | Wrong site served (vhost or DNS pointing elsewhere), WordPress error page or maintenance screen with 200, the footer wording changed, basic auth page on staging | `curl -s https://cigarholic.com/ \| grep -c 'Handcrafted in Vietnam'`; `troubleshooting.md` "TLS and DNS" and "Deploy and site"; if the wording changed on purpose, update `KEYWORD` and re-run the workflow |
| `*-checkout` **down** while `*-home` is up | PHP-FPM saturated (`pm.max_children`, `php-slow.log`) while the home page comes from the FastCGI cache; a rewrite or permalink problem; `429` from the rate limit if something hammers checkout | `troubleshooting.md` "503 / slow" and "429" rows; `wp-cli.yml` → `rewrite list`; `health-check.md` store-level checks 2 |
| `*-store-api` **down** | REST disabled by a plugin, permalinks reset to plain, a fatal in `rest_api_init`, a WAF rule blocking `/wp-json/` | `curl -sS https://cigarholic.com/wp-json/wc/store/v1/products -o /dev/null -w '%{http_code}'`; `wp-cli.yml` → `plugin list`, `rewrite flush`; `troubleshooting.md` "Deploy and site" |
| **SSL expiry reminder** (certificate expires in < 30 days) | `certbot.timer` stopped or renewal failed (port 80 blocked, DNS moved, rate limit) | `status` shows the certificate dates and the timer; `troubleshooting.md` "TLS and DNS" ("Renewal failed" row). Let's Encrypt certificates last 90 days and certbot renews at 30 days left, so a reminder that arrives and is **not** followed by a renewed certificate within a day means the timer failed |
| **SSL error** (certificate invalid, "down" with a certificate reason) | Expired certificate, the self-signed placeholder still in place after a re-provision, wrong hostname on the certificate (`www`, `max2lab` not yet on it) | `infra-provision.yml` → `site-production` (or `site-staging`) requests or expands the certificate; `troubleshooting.md` "TLS and DNS" |
| `cigarholic-staging-home` **down with 401** | The staging basic-auth credentials changed (`STAGING_BASIC_AUTH_*`) and the monitor still has the old ones | Re-run the workflow for `staging` (not a dry run); it rewrites the monitor's credentials. Repeated 401s also trip fail2ban's `nginx-http-auth` jail for UptimeRobot's addresses: `sudo fail2ban-client set nginx-http-auth unbanip <ip>` on the VPS after fixing the credentials |
| Alerts during a **restore drill or a planned maintenance** | Expected | Pause the monitors in the dashboard first (the script never pauses or resumes anything), resume them afterwards |

After every incident: add the cause to `troubleshooting.md` if it was new, and note it in
`docs/STATUS.md`.

## Server-side signals that already exist

| Signal | Where | Read it with |
| --- | --- | --- |
| Services, failed units, certificate dates, last deploy, last backup, disk, fail2ban bans | VPS | `infra-provision.yml` → `status` (`infra/server/status.sh`, read-only) |
| Certificate renewal | `certbot.timer` (renewal hook reloads Nginx) | `status` ("Certificates"); `sudo certbot renew --dry-run` on the VPS |
| Nightly production backup to B2 | `cigarholic-backup.timer` (03:17 UTC, `OPS-07`) | `status` ("last B2"), `journalctl -u cigarholic-backup`; `backup-restore.md` |
| Brute force and abuse | fail2ban jails `sshd`, `nginx-http-auth`, `nginx-botsearch`, `nginx-limit-req`, `nginx-bad-request`, `wp-login` | `status` ("fail2ban"); `/var/log/fail2ban.log` |
| Application errors | `/var/www/<site>/shared/logs/php-error.log`, `php-slow.log`, `debug.log` (staging); `/var/log/nginx/cigarholic-<site>.error.log` | `status` prints the line counts; `troubleshooting.md` "Where the logs are" |
| Deploy health | The Deployer recipe requests the home page through Nginx after the symlink switch and rolls back on failure | The deploy workflow's log; `rollback.md` |

Error aggregation (Sentry or similar) and WooCommerce log review with alerts on payment and
webhook failures are `OPS-11` (P1), not part of this runbook.

## Setup

### A. Automated: the workflow (preferred)

1. Owner: create the UptimeRobot account (Free plan is enough: 50 monitors, 5-minute checks), add
   and confirm the alert contacts (email; install the mobile app for push), then copy the **Main
   API key** (*Integrations & API* → *API*) into the repository secret `UPTIMEROBOT_API_KEY`
   (`OWNER-ACTIONS.md` B3). Optional: variable `UPTIMEROBOT_ALERT_CONTACTS`.
2. Anyone: Actions → **Monitoring** → *Run workflow*: `environment: production`, `dry_run: true`.
   The log shows the three monitors it would create and whether the key is present.
3. Run again with `dry_run: false`. Then `environment: staging`, `dry_run: false` (needs the
   `STAGING_BASIC_AUTH_*` secrets, B8). The run creates missing monitors and updates existing ones
   (matched by name); it never deletes, pauses or resumes a monitor and never changes a monitor's
   type (it warns and skips if a name exists with another type: delete that one in the dashboard).
4. `list: true` prints the monitors (id, status, type, interval, name, URL, certificate expiry and
   whether expiry reminders are off) and the alert contacts (id, status, type, name; addresses are
   never printed because the Actions log of a public repository is public).
5. Owner, in the dashboard, once: open each HTTPS monitor and confirm that the **SSL expiry
   reminder** (and, if offered, the domain expiry reminder) is on. The v2 API has no field for it;
   the *list* run shows "expiry reminders OFF" if it is not.

Re-run the workflow after the staging credentials rotate, after the footer wording changes, or
after a monitor was deleted by accident. A run makes at most 2 + number-of-monitors API requests
(five for production, three for staging); the Free plan allows ten per minute, so wait a minute
between the two runs.

### B. Manual: the UptimeRobot dashboard

If the owner prefers not to hand over an API key, create the same monitors by hand. Field names
follow the dashboard as of 2026; the exact labels may differ slightly.

1. *Alert contacts*: add your email and confirm it; install the UptimeRobot app and enable push;
   optionally SMS.
2. *New monitor* → **Keyword**: URL `https://cigarholic.com/`, friendly name
   `cigarholic-production-home`, keyword `Handcrafted in Vietnam`, alert when the keyword **does not
   exist**, case-insensitive, interval 5 minutes, timeout 30 s, HTTP method GET. Under the advanced
   or SSL settings: **SSL expiry reminder on**, domain expiry reminder on. Select all alert contacts.
3. *New monitor* → **HTTP(s)**: URL `https://cigarholic.com/checkout/`, friendly name
   `cigarholic-production-checkout`, interval 5 minutes, timeout 30 s, GET, all alert contacts.
4. *New monitor* → **HTTP(s)**: URL `https://cigarholic.com/wp-json/wc/store/v1/products`, friendly
   name `cigarholic-production-store-api`, same settings.
5. *New monitor* → **Keyword**: URL `https://staging.cigarholic.com/`, friendly name
   `cigarholic-staging-home`, keyword `Handcrafted in Vietnam`, alert when it does not exist,
   **HTTP authentication: Basic** with the `STAGING_BASIC_AUTH_USER` / `STAGING_BASIC_AUTH_PASSWORD`
   values (type them into the dashboard, never into the session or a document), SSL expiry
   reminder on, all alert contacts.
6. Wait for the first checks (status "up"), then test the routing once: pause the staging monitor
   or use the dashboard's test-notification button, and confirm the email and the push arrive.

Keep the friendly names exactly as above: if the API key arrives later, the workflow then updates
these monitors instead of creating duplicates.

## The script

`infra/monitoring/uptimerobot.sh <staging|production> [--dry-run] [--list]`; it refuses to run
without the environment argument. Needs `curl` and `jq` (present on the GitHub runners). Reads
`UPTIMEROBOT_API_KEY` (required unless `--dry-run`), `UPTIMEROBOT_ALERT_CONTACTS` (optional),
`UPTIMEROBOT_INTERVAL` (optional, seconds) and, for staging, `STAGING_BASIC_AUTH_USER` /
`STAGING_BASIC_AUTH_PASSWORD`. The key and the credentials travel from the environment into the
request body through `jq` and `curl`'s stdin, so they are on no command line, in no file and in
no output; API errors are printed as type and message only (an invalid-key error would echo the
key back otherwise). Exit codes: 0 done, 1 usage or configuration, 2 API or network error.

From a laptop:

```bash
infra/monitoring/uptimerobot.sh production --dry-run
UPTIMEROBOT_API_KEY=<key> infra/monitoring/uptimerobot.sh production
UPTIMEROBOT_API_KEY=<key> infra/monitoring/uptimerobot.sh production --list
```

**API version.** The script uses the UptimeRobot **v2** API (`https://api.uptimerobot.com/v2/`,
reference `https://uptimerobot.com/api/legacy/`): form-encoded POST with `api_key` and
`format=json`, methods `getAlertContacts`, `getMonitors` (`search=cigarholic-<env>-`, `ssl=1`),
`newMonitor` and `editMonitor`; type codes 1 HTTP(s) and 2 keyword, `keyword_type` 1 (exists),
`keyword_case_type` 1 (case-insensitive), `http_auth_type` 1 (basic), `alert_contacts` as
`id_0_0-id_0_0`. UptimeRobot released a REST **v3** (`https://uptimerobot.com/api/v3/`, bearer
token, `POST /monitors`, `PATCH /monitors/{id}`) in 2025 and describes v2 as remaining available
without further updates. v2 was chosen because its reference is a plain page the sandbox could
read, its Main API key works on the Free plan and the rate limit (10 requests per minute on Free)
is enough for a run. If v2 is switched off, `ur_api()` and the field names in `monitor_fields()`
are the only places to change. The API calls themselves have not been executed from the sandbox
(no key by design, `AGENTS.md`); the first real workflow run verifies them, and its log is the
place to look if a field name has drifted.

## Not covered on purpose

- Monitoring from inside the VPS (a self-check cannot report that the VPS is unreachable).
- A public status page; UptimeRobot can publish one later if the owner wants it.
- Response-time budgets and Core Web Vitals: those are checked by Lighthouse / PageSpeed per
  release (`FND-12`), not by uptime monitoring.

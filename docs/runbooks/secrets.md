# Secrets, variables, server access and DNS inventory

Names and purposes only, never values. The names are the contract between the owner, the GitHub
Environments and the workflows (`docs/KICKOFF-BRIEF.md` Appendix A). Every addition is recorded
here. Hand-over instructions live in `docs/OWNER-ACTIONS.md`.

## Where things live

- **GitHub Free, public repository (owner, 2026-10-30):** the owner put every key in as a
  **repository secret or variable** (Settings → Secrets and variables → Actions) while the
  repository was private; it was then made public. Workflows read `secrets.NAME`, which resolves an
  Environment secret of that name first and the repository secret otherwise, so the keys can stay
  where they are. Jobs declare `environment: staging` or `environment: production` for deployment
  history and for the `production` required-reviewer rule (available on public repositories).
- Items that differ per site carry a `STAGING_` or `PRODUCTION_` prefix. Items shared by both sites
  (the VPS is one machine) or used by one site only keep the brief's name.
- Production deploys run from a tag and wait for the owner's approval on the `production`
  Environment; a `workflow_dispatch` with a `tag` input that only the owner runs is the fallback.
- The repository is public: names and purposes only, never values, hostnames of internal services
  or anything an attacker could use.
- The sandbox cannot read, list or set any of these; the first workflow that uses one verifies it.

## Inventory

| Name | Kind | Where | Purpose | Status |
| --- | --- | --- | --- | --- |
| `SSH_HOST` | secret | repository | VPS public IP (Hostinger, US) | Set by the owner 2026-10-30, unverified |
| `SSH_PORT` | secret | repository | `22` | Same |
| `SSH_USER` | secret | repository | `root` until provisioning creates the limited `deploy` user | Same |
| `SSH_PRIVATE_KEY` | secret | repository | ed25519 private key, installed for `root` (later also for `deploy`) | Same |
| `SSH_KNOWN_HOSTS` | secret | repository | `ssh-keyscan -H <host>` output; pins the host key | Same |
| `STAGING_BASIC_AUTH_USER` | secret | repository | Staging HTTP basic auth user | Same |
| `STAGING_BASIC_AUTH_PASSWORD` | secret | repository | Staging HTTP basic auth password | Same |
| `CLOUDFLARE_API_TOKEN` | secret | repository | Token scoped to both zones: DNS, zone settings, SSL, cache purge, WAF, redirects; account: Turnstile | Not set |
| `CLOUDFLARE_ACCOUNT_ID` | variable | repository | Cloudflare account id | Not set |
| `CLOUDFLARE_ZONE_ID_CIGARHOLIC` | variable | repository | Zone id of `cigarholic.com` | Not set |
| `CLOUDFLARE_ZONE_ID_MAX2LAB` | variable | repository | Zone id of `max2lab.com` | Not set |
| `STAGING_POSTMARK_SERVER_TOKEN` | secret | repository | FluentSMTP transport on staging: sandbox server token or `POSTMARK_API_TEST` | Not set |
| `PRODUCTION_POSTMARK_SERVER_TOKEN` | secret | repository | FluentSMTP transport on production: live server token | Not set |
| `B2_BUCKET` | secret | repository | Backup bucket name (production backups; the restore drill reads it) | Not set |
| `B2_KEY_ID` | secret | repository | Backblaze application key id, restricted to the bucket | Not set |
| `B2_APPLICATION_KEY` | secret | repository | Backblaze application key | Not set |
| `B2_S3_ENDPOINT` | variable | repository | S3 endpoint of the bucket's region (added 2026-10-30; the S3-compatible client needs it) | Not set |
| `UPTIMEROBOT_API_KEY` | secret | repository | Creates the monitors; optional if the owner creates them by hand | Not set |
| `STRIPE_TEST_PUBLISHABLE_KEY` | secret | repository | Stripe test mode, written to staging only | Not set |
| `STRIPE_TEST_SECRET_KEY` | secret | repository | Stripe test mode, written to staging only | Not set |
| `STRIPE_TAX_SECRET_KEY` | secret | repository | Only if the Stripe Tax plugin accepts a key instead of its Connect flow | Not set |
| `PAYPAL_SANDBOX_CLIENT_ID` | secret | repository | PayPal sandbox app, staging only | Not set |
| `PAYPAL_SANDBOX_CLIENT_SECRET` | secret | repository | PayPal sandbox app, staging only | Not set |
| `STAGING_TURNSTILE_SITE_KEY`, `PRODUCTION_TURNSTILE_SITE_KEY` | secret | repository | Turnstile widget per site; sandbox and CI use Cloudflare's public always-pass test keys | Not set |
| `STAGING_TURNSTILE_SECRET_KEY`, `PRODUCTION_TURNSTILE_SECRET_KEY` | secret | repository | Turnstile server-side verification per site | Not set |
| `GA4_MEASUREMENT_ID` | variable | repository | GA4 web stream id | Not set |
| `WP_OWNER_EMAIL` | variable | repository | Owner admin account | Not set |
| `WP_OWNER_INITIAL_PASSWORD` | secret | repository | Initial owner password on both sites, changed at first login | Not set |
| `GSC_VERIFICATION_TOKEN` | variable | repository | Search Console DNS verification | Not set |
| `BING_VERIFICATION_TOKEN` | variable | repository | Bing Webmaster verification | Not set |
| `LETSENCRYPT_EMAIL` | variable | repository | Optional (added 2026-10-30, FND-08): contact for Let's Encrypt expiry notices; without it `site.sh` registers without an email | Not set |

## Which workflow reads what (FND-08)

| Workflow | Reads | Writes on the server |
| --- | --- | --- |
| `infra-provision.yml` | `SSH_*`; `site-staging`: `STAGING_BASIC_AUTH_USER`, `STAGING_BASIC_AUTH_PASSWORD`, `LETSENCRYPT_EMAIL`; `provision`: the public half of `SSH_PRIVATE_KEY` becomes the `deploy` user's authorized key | `shared/.env` (database credentials, salts; once), Nginx `htpasswd` |
| `deploy-staging.yml` | `SSH_*`, `STAGING_POSTMARK_SERVER_TOKEN` → `POSTMARK_SERVER_TOKEN`, `STAGING_TURNSTILE_SITE_KEY` → `TURNSTILE_SITE_KEY`, `STAGING_TURNSTILE_SECRET_KEY` → `TURNSTILE_SECRET_KEY`, `STRIPE_TEST_PUBLISHABLE_KEY`, `STRIPE_TEST_SECRET_KEY`, `STRIPE_TAX_SECRET_KEY`, `PAYPAL_SANDBOX_CLIENT_ID`, `PAYPAL_SANDBOX_CLIENT_SECRET`, `GA4_MEASUREMENT_ID` (same names in `.env`); `STAGING_BASIC_AUTH_*` for the health check | `shared/.env` keys (empty secrets are skipped) |
| `deploy-production.yml` | `SSH_*`, `PRODUCTION_POSTMARK_SERVER_TOKEN` → `POSTMARK_SERVER_TOKEN`, `PRODUCTION_TURNSTILE_SITE_KEY` → `TURNSTILE_SITE_KEY`, `PRODUCTION_TURNSTILE_SECRET_KEY` → `TURNSTILE_SECRET_KEY`, `GA4_MEASUREMENT_ID`; `CLOUDFLARE_API_TOKEN`, `CLOUDFLARE_ZONE_ID_CIGARHOLIC` (purge, optional) | `shared/.env` keys; never Stripe or PayPal live credentials |
| `cloudflare.yml` | `CLOUDFLARE_API_TOKEN`, `CLOUDFLARE_ACCOUNT_ID`, `CLOUDFLARE_ZONE_ID_CIGARHOLIC`, `CLOUDFLARE_ZONE_ID_MAX2LAB`, `SSH_HOST` as the origin address | nothing on the server |
| `backup-restore.yml` | `SSH_*`, `B2_BUCKET`, `B2_KEY_ID`, `B2_APPLICATION_KEY`, `B2_S3_ENDPOINT` | `/etc/cigarholic/backup.env` (root only) so the nightly timer can upload |
| `wp-cli.yml` | `SSH_*`, `WP_OWNER_EMAIL`, `WP_OWNER_INITIAL_PASSWORD` (exported for the one command, for `wp core install`) | whatever the WP-CLI command does |

Secrets travel to the server inside the streamed script (`.github/actions/ssh/ssh-run`), never on a
command line.

Live Stripe and PayPal credentials are never stored anywhere in GitHub; the owner connects them in
WP admin on production (`docs/OWNER-ACTIONS.md` C1).

Names from an earlier session that are to be deleted if they still exist: `VPS_HOST`,
`VPS_KNOWN_HOSTS`, `VPS_ROOT_KEY`, `VPS_DEPLOY_KEY`, `STAGING_HTTP_AUTH`.

## VPS (as reported, 2026-10-30)

- Ubuntu 26.04 LTS, 16 GB RAM, 193 GB disk, SSH on port 22.
- The owner reinstalled the OS on 2026-10-30 and confirmed a clean install with the root key and
  `SSH_KNOWN_HOSTS` made after the reinstall. Not yet checked by a workflow run.
- Hostinger, region shown as America / Houston (owner, 2026-10-30). The earlier session saw the IP
  in a LACNIC block; the panel's region is what counts for latency.

## Domains (verified by RDAP on 2026-10-30)

| Domain | Registrar | Registered | Expires | Status | Nameservers | DNS |
| --- | --- | --- | --- | --- | --- | --- |
| `cigarholic.com` | iNET Software Company Limited | 2026-10-03 | 2029-10-03 | `clientHold`, `clientTransferProhibited` | `ns`, `ns1`, `ns2.viettelidc.com.vn` | NXDOMAIN |
| `max2lab.com` | iNET Software Company Limited | 2026-10-03 | 2029-10-03 | `clientHold`, `clientTransferProhibited` | same | NXDOMAIN |

`clientHold` removes the domain from the zone: no DNS records, certificates or email work until the
registrar lifts it (`docs/OWNER-ACTIONS.md` A5). Because there are no records, moving both zones to
Cloudflare needs no import step.

Re-check:

```bash
curl -sS https://rdap.verisign.com/com/v1/domain/cigarholic.com | jq -c '{status, ns: [.nameservers[].ldhName]}'
```

# Secrets, variables, server access and DNS inventory

Names and purposes only, never values. The names are the contract between the owner, the GitHub
Environments and the workflows (`docs/KICKOFF-BRIEF.md` Appendix A). Every addition is recorded
here. Hand-over instructions live in `docs/OWNER-ACTIONS.md`.

## Where things live

- **Environment secrets and variables** (`staging`, `production`): everything that differs per
  site or must never reach the other site.
- **Repository secrets and variables:** account-wide items (Cloudflare, GA4, verification tokens).
- On GitHub Free, private repositories may not get Environment secrets. If the Environments page
  offers no secrets for this repository, the same names go in as repository secrets instead
  (`docs/OWNER-ACTIONS.md` A4), and production deploy becomes a `workflow_dispatch` with a `tag`
  input that only the owner runs.
- The sandbox cannot read, list or set any of these; the first workflow that uses one verifies it.

## Inventory

| Name | Kind | Where | Purpose | Status |
| --- | --- | --- | --- | --- |
| `SSH_HOST` | secret | `staging`, `production` | VPS public IP | Set by the owner 2026-10-30, unverified |
| `SSH_PORT` | secret | `staging`, `production` | `22` | Same |
| `SSH_USER` | secret | `staging`, `production` | `root` until provisioning creates the limited `deploy` user | Same |
| `SSH_PRIVATE_KEY` | secret | `staging`, `production` | ed25519 private key, installed for `root` (later also for `deploy`) | Same |
| `SSH_KNOWN_HOSTS` | secret | `staging`, `production` | `ssh-keyscan -H <host>` output; pins the host key | Same |
| `STAGING_BASIC_AUTH_USER` | secret | `staging` | Staging HTTP basic auth user | Same |
| `STAGING_BASIC_AUTH_PASSWORD` | secret | `staging` | Staging HTTP basic auth password | Same |
| `CLOUDFLARE_API_TOKEN` | secret | repository | Token scoped to both zones: DNS, zone settings, SSL, cache purge, WAF, redirects; account: Turnstile | Not set |
| `CLOUDFLARE_ACCOUNT_ID` | variable | repository | Cloudflare account id | Not set |
| `CLOUDFLARE_ZONE_ID_CIGARHOLIC` | variable | repository | Zone id of `cigarholic.com` | Not set |
| `CLOUDFLARE_ZONE_ID_MAX2LAB` | variable | repository | Zone id of `max2lab.com` | Not set |
| `POSTMARK_SERVER_TOKEN` | secret | `staging` (sandbox server or `POSTMARK_API_TEST`), `production` (live server) | FluentSMTP transport | Not set |
| `B2_BUCKET` | secret | `production` (staging optional) | Backup bucket name | Not set |
| `B2_KEY_ID` | secret | `production` | Backblaze application key id, restricted to the bucket | Not set |
| `B2_APPLICATION_KEY` | secret | `production` | Backblaze application key | Not set |
| `B2_S3_ENDPOINT` | variable | `production` | S3 endpoint of the bucket's region (added 2026-10-30; the S3-compatible client needs it) | Not set |
| `UPTIMEROBOT_API_KEY` | secret | repository | Creates the monitors; optional if the owner creates them by hand | Not set |
| `STRIPE_TEST_PUBLISHABLE_KEY` | secret | `staging` | Stripe test mode | Not set |
| `STRIPE_TEST_SECRET_KEY` | secret | `staging` | Stripe test mode | Not set |
| `STRIPE_TAX_SECRET_KEY` | secret | `staging` | Only if the Stripe Tax plugin accepts a key instead of its Connect flow | Not set |
| `PAYPAL_SANDBOX_CLIENT_ID` | secret | `staging` | PayPal sandbox app | Not set |
| `PAYPAL_SANDBOX_CLIENT_SECRET` | secret | `staging` | PayPal sandbox app | Not set |
| `TURNSTILE_SITE_KEY` | secret | `staging`, `production` | Turnstile widget; sandbox and CI use Cloudflare's public always-pass test keys | Not set |
| `TURNSTILE_SECRET_KEY` | secret | `staging`, `production` | Turnstile server-side verification | Not set |
| `GA4_MEASUREMENT_ID` | variable | repository | GA4 web stream id | Not set |
| `WP_OWNER_EMAIL` | variable | repository | Owner admin account | Not set |
| `WP_OWNER_INITIAL_PASSWORD` | secret | `staging`, `production` | Initial owner password, changed at first login | Not set |
| `GSC_VERIFICATION_TOKEN` | variable | repository | Search Console DNS verification | Not set |
| `BING_VERIFICATION_TOKEN` | variable | repository | Bing Webmaster verification | Not set |

Live Stripe and PayPal credentials are never stored anywhere in GitHub; the owner connects them in
WP admin on production (`docs/OWNER-ACTIONS.md` C1).

Names from an earlier session that are to be deleted if they still exist: `VPS_HOST`,
`VPS_KNOWN_HOSTS`, `VPS_ROOT_KEY`, `VPS_DEPLOY_KEY`, `STAGING_HTTP_AUTH`.

## VPS (as reported, 2026-10-30)

- Ubuntu 26.04 LTS, 16 GB RAM, 193 GB disk, SSH on port 22.
- The owner reinstalled the OS on 2026-10-30, after an earlier session had installed a server
  stack on it. It should be clean; this has not been checked from the sandbox (no SSH there, by
  design). Because of the reinstall, the root public key and `SSH_KNOWN_HOSTS` must be the ones
  made after it.
- Region not confirmed: the IP belongs to a Latin American (LACNIC) address block, while the
  launch plan calls for a US region (`docs/OWNER-ACTIONS.md` A1).

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

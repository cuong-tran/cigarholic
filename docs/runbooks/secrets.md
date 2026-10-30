# Secrets, server access and DNS inventory

Names and purposes only, never values. Verified 2026-10-30.

## Secrets for server access (contract: `docs/KICKOFF-BRIEF.md` Appendix A1 and B8)

| Name | Where | Purpose |
| --- | --- | --- |
| `SSH_HOST` | Environments `staging` and `production` | VPS public IP |
| `SSH_PORT` | Environments `staging` and `production` | `22` |
| `SSH_USER` | Environments `staging` and `production` | `root` until provisioning creates the limited `deploy` user |
| `SSH_PRIVATE_KEY` | Environments `staging` and `production` | ed25519 private key, installed for `root` (later also for `deploy`) |
| `SSH_KNOWN_HOSTS` | Environments `staging` and `production` | `ssh-keyscan -H <host>` output; pins the host key |
| `STAGING_BASIC_AUTH_USER` | Environment `staging` | Staging HTTP basic auth user |
| `STAGING_BASIC_AUTH_PASSWORD` | Environment `staging` | Staging HTTP basic auth password |

On GitHub Free, private repositories get no Environment secrets. If the Environments page offers
no secrets for this repository, the same names go in as repository secrets instead.

Status on 2026-10-30: the owner is re-creating the secrets under these names. An earlier session
had them set under other names, and those are to be deleted: `VPS_HOST`, `VPS_KNOWN_HOSTS`,
`VPS_ROOT_KEY`, `VPS_DEPLOY_KEY` and `STAGING_HTTP_AUTH`. Under the old names, a `workflow_dispatch`
job logged in to the VPS as root with a pinned host key. The new names have not been verified yet.

## VPS (as of 2026-10-30)

- Ubuntu 26.04.1 LTS, 16 GB RAM, 193 GB disk. Root logs in with a key; SSH password login is
  still enabled.
- Region not confirmed: the IP belongs to a Latin American (LACNIC) address block, while the
  launch plan calls for a US region.
- **Not a clean install.** An earlier session ran a bootstrap script that installed and configured:
  - Nginx 1.28: default site removed; `conf.d/00-cigarholic.conf` with catch-all default servers
    (port 80 returns 444, port 443 rejects the TLS handshake); `conf.d/01-cloudflare-realip.conf`;
    `server_tokens off` in `nginx.conf`
  - PHP 8.5-FPM with mysql, curl, gd, intl, mbstring, xml, zip, bcmath, imagick and redis;
    `conf.d/90-cigarholic.ini` (memory, upload and OPcache limits)
  - MariaDB 11.8, Redis 8 (`maxmemory 256mb`), WP-CLI in `/usr/local/bin`
  - Certbot with a deploy hook that reloads Nginx
  - ufw enabled (allows `22/tcp` and `Nginx Full`), fail2ban, unattended security upgrades,
    a 2 GB `/swapfile`

  It created no sites, databases or site users. Reinstalling the OS from the provider's panel
  returns the server to clean; afterwards, re-add the root public key and regenerate
  `SSH_KNOWN_HOSTS`, because the host key changes.

## Domains (as of 2026-10-30)

`cigarholic.com` and `max2lab.com` were both registered on 2026-10-03 at iNET Software
(nameservers `ns.viettelidc.com.vn`, `ns1.`, `ns2.`). Both have had registrar status
`clientHold` since registration, so they return NXDOMAIN: no DNS records, certificates or email
work until the owner gets the hold lifted.

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

- Ubuntu 26.04 LTS, 16 GB RAM, 193 GB disk, SSH on port 22.
- The owner reinstalled the OS on 2026-10-30, after an earlier session had installed a server
  stack on it. It should be clean; this hasn't been checked from the sandbox. Because of the
  reinstall, the root public key and `SSH_KNOWN_HOSTS` must be the ones made after it.
- Region not confirmed: the IP belongs to a Latin American (LACNIC) address block, while the
  launch plan calls for a US region.

## Domains (as of 2026-10-30)

`cigarholic.com` and `max2lab.com` were both registered on 2026-10-03 at iNET Software
(nameservers `ns.viettelidc.com.vn`, `ns1.`, `ns2.`). Both have had registrar status
`clientHold` since registration, so they return NXDOMAIN: no DNS records, certificates or email
work until the owner gets the hold lifted.

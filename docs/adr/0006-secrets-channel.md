# ADR 0006: One secrets channel

Status: accepted, 2026-10-30 (updated the same day for GitHub Free and the public repository). Feature IDs: FND-09, FND-08, OPS-07.

## Context

Agents work from a sandbox that cannot hold or read secrets and has no SSH. The owner must never edit a server file by hand, and no secret may ever appear in the repository, in a doc or in a chat. The repository is public.

## Decision

- **GitHub secrets and variables are the only channel.** The owner is on GitHub Free and placed every key as a *repository* secret; names are the contract (`docs/runbooks/secrets.md`). Items that differ per site carry a `STAGING_` or `PRODUCTION_` prefix. Workflows read `secrets.NAME`; Environments `staging` and `production` are used for deployment history and the `production` required-reviewer rule (available because the repository is public), and an Environment secret of the same name would take precedence if the owner ever moves one.
- **The provisioning workflow generates the server `.env`** (database credentials, salts) once per site; **the deploy workflow upserts the application keys** from GitHub into it on every deploy. Nothing is typed on the server.
- **Live payment credentials are the exception:** the owner connects Stripe and PayPal in WP admin on production (plugin onboarding) after staging is green. They never pass through GitHub or an agent.
- **The sandbox needs no secrets.** If a later phase benefits from an API call from the sandbox, it is requested as a cloud-environment network secret that the proxy attaches without exposing the value.

## Consequences

- Every new secret or variable gets a row in `docs/runbooks/secrets.md` (name, kind, purpose, status) before a workflow reads it.
- A secret rotation is: change it in GitHub, re-run the deploy (or provisioning) workflow.
- `composer audit`, a secrets grep over the repo and its history, and the PR checklist guard the public repository.

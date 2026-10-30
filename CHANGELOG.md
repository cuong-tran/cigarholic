# Changelog

All notable changes to Cigarholic. Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); entries name their feature IDs from `docs/FEATURES.md`. Releases are Git tags deployed to production after the owner's approval.

## [Unreleased]

### Added

- FND-01: Bedrock scaffold with WordPress 7.1.4, WooCommerce 11.2.1 and the launch plugin set pinned in `composer.lock`; environment configs; `.env.example`; `scripts/tools.sh` for the pinned PHPStan, PHPUnit and Deployer phars (#4).
- FND-04: `AGENTS.md`, `CLAUDE.md`, issue and PR templates, this changelog, `docs/plugins.md`, `docs/versions.md`, ADRs 0001 to 0008.
- Docs: `docs/OWNER-ACTIONS.md`, `docs/STATUS.md`, runbooks for the sandbox and the secrets inventory; `docs/FEATURES.md` reconciled with the kickoff brief (#3).

### Not yet implemented

Everything that serves a page: runtime (FND-02), CI (FND-06), the `cigarholic-core` mu-plugin (FND-05), the theme, provisioning, catalog model, infrastructure and tests. See `docs/STATUS.md`.

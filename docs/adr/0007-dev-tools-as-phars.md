# ADR 0007: PHPStan, PHPUnit and Deployer as pinned phars

Status: accepted, 2026-10-30. Feature IDs: FND-06, FND-08, FND-12.

## Context

The Claude Code sandbox reaches the internet through a proxy that refuses GitHub API zipball and archive downloads for repositories not attached to the session, so Composer falls back to `git clone --mirror` for every GitHub-hosted package. For `phpstan/phpstan` (a repository of committed phars) and `phpunit/phpunit` with its `sebastian/*` dependencies that meant gigabytes of mirrors and more than ten minutes per fresh session, which also broke the five-minute budget of the environment setup script. Release downloads (`github.com/<owner>/<repo>/releases/download/...`, `phar.phpunit.de`, `deployer.org`) work everywhere in seconds.

## Decision

- `scripts/tools.sh` installs PHPStan, PHPUnit and Deployer as phars into `tools/` (gitignored), each pinned by version and sha256; `--check` verifies them. CI, the sandbox and laptops run the same script.
- `composer.json` keeps the libraries those tools load (PHPStan WordPress extension, WordPress and WooCommerce stubs, wp-phpunit, PHPUnit polyfills, WPCS and PHPCompatibility) and **replaces** `phpstan/phpstan` and `phpunit/phpunit` at the pinned versions so the dependency graph resolves without them.
- Composer scripts (`composer phpstan`, `composer test`) and the `Makefile` call the phars, so nobody needs to know the difference.

## Consequences

- A tool upgrade is a one-line change in `scripts/tools.sh` plus `docs/versions.md`.
- `vendor/bin/phpstan` and `vendor/bin/phpunit` do not exist; use `tools/*.phar` or the Composer scripts.
- PHP_CodeSniffer stays a Composer dependency because WPCS depends on it and its repository is small.

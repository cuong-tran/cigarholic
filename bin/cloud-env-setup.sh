#!/bin/bash
# Setup script for the Claude Code cloud environment used with this repo.
#
# Paste this whole file into claude.ai/code -> your environment -> Edit -> Setup script.
# It runs as root on Ubuntu 24.04 before Claude starts, and the resulting disk is cached
# for later sessions. It must exit 0 and finish in about five minutes, so every step is
# allowed to fail without blocking the session.
#
# The base image already has PHP 8.3 (with mysqli, gd, intl, imagick, redis, zip), Composer,
# WP-CLI, MariaDB, Redis, Node, Docker and Playwright with Chromium. This adds the rest.

export DEBIAN_FRONTEND=noninteractive

# Linters used by `make lint` and CI: shellcheck for bin/, provision/ and server/ scripts,
# actionlint for .github/workflows/.
(apt-get update -qq && apt-get install -y -qq --no-install-recommends shellcheck) || true
if command -v go >/dev/null 2>&1; then
  (cd /tmp && GOBIN=/usr/local/bin go install github.com/rhysd/actionlint/cmd/actionlint@v1.7.12) || true
fi

# Claude runs as root in the cloud; let Composer run its plugins and WP-CLI run as root.
for f in /root/.bashrc /root/.profile; do
  grep -q 'WP_CLI_ALLOW_ROOT' "$f" 2>/dev/null ||
    printf '\nexport COMPOSER_ALLOW_SUPERUSER=1\nexport WP_CLI_ALLOW_ROOT=1\n' >>"$f"
done

# Warm the Composer cache when the repo is already checked out, so `bin/dev up` is fast.
# (Composer has to clone GitHub-hosted packages with git in the cloud; zip downloads are blocked.)
for dir in /home/user/*/; do
  if [ -f "$dir/composer.lock" ] && [ -f "$dir/wp-cli.yml" ]; then
    (cd "$dir" && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --no-progress) || true
  fi
done

exit 0

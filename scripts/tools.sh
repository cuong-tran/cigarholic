#!/usr/bin/env bash
# Installs the pinned development tools that run as phars: PHPStan, PHPUnit and Deployer.
#
# They are not Composer dependencies on purpose: their upstream Git repositories are huge and the
# sandbox can only fetch GitHub packages by cloning them (docs/runbooks/sandbox.md), whereas release
# downloads are fast everywhere. composer.json "replaces" phpstan/phpstan and phpunit/phpunit so the
# PHPStan WordPress extension and the PHPUnit polyfills still resolve.
#
# Usage: scripts/tools.sh            installs into tools/ (gitignored), skips files that already match
#        scripts/tools.sh --check    only verifies what is installed, exit 1 if anything is missing
#
# To upgrade a tool: change its version and checksum below, run the script, update docs/versions.md.
set -euo pipefail

cd "$(dirname "$0")/.."
mkdir -p tools

# name|version|url|sha256
TOOLS=(
  "phpstan.phar|2.3.4|https://github.com/phpstan/phpstan/releases/download/2.3.4/phpstan.phar|a961d12cc71941a31ad6560e6a330347d487612628c3202a718f71a59de21a7c"
  "phpunit.phar|9.6.38|https://phar.phpunit.de/phpunit-9.6.38.phar|6b5235f282c6ccab3674ac27c5b1ddbd1da838e74bf07002ddcfd030d31298d7"
  "deployer.phar|8.0.5|https://github.com/deployphp/deployer/releases/download/v8.0.5/deployer.phar|0026dfa73626a8ccde06222fe5fccf03e6d4a19e765914bb87d7d2cc21f3e57d"
)

check_only=0
[ "${1:-}" = "--check" ] && check_only=1
status=0

for entry in "${TOOLS[@]}"; do
  IFS='|' read -r name version url sha <<<"$entry"
  target="tools/$name"
  if [ -f "$target" ] && [ "$(sha256sum "$target" | cut -d' ' -f1)" = "$sha" ]; then
    echo "ok       $name $version"
    continue
  fi
  if [ "$check_only" = 1 ]; then
    echo "missing  $name $version"
    status=1
    continue
  fi
  echo "download $name $version"
  curl -fsSL --retry 3 --retry-delay 2 -o "$target.tmp" "$url"
  actual=$(sha256sum "$target.tmp" | cut -d' ' -f1)
  if [ "$actual" != "$sha" ]; then
    rm -f "$target.tmp"
    echo "checksum mismatch for $name: expected $sha, got $actual" >&2
    exit 1
  fi
  chmod +x "$target.tmp"
  mv "$target.tmp" "$target"
done

exit "$status"

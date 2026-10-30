#!/usr/bin/env bash
# FND-03: idempotent site configuration, applied after every deploy and by `bin/dev setup`.
# Run from the project (or release) root, where wp-cli.yml lives. Each provision/NN-*.sh step
# must be safe to run any number of times and must only use WP-CLI.
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

if [ "$(id -u)" -eq 0 ]; then
  export WP_CLI_ALLOW_ROOT=1
fi

WP_ENV="$(wp eval 'echo WP_ENV;')"
export WP_ENV

for step in provision/[0-9][0-9]-*.sh; do
  echo "provision: $step ($WP_ENV)"
  # shellcheck source=/dev/null
  source "$step"
done

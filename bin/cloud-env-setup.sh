#!/bin/bash
# Setup script for the Claude Code cloud environment used with this repo.
#
# Paste this whole file into claude.ai/code -> your environment -> Edit -> Setup script.
# It runs as root on Ubuntu 24.04 before Claude starts, and the resulting disk is cached
# for later sessions. It must exit 0 and finish in about five minutes, so every step is
# allowed to fail without blocking the session.
#
# The base image (Ubuntu 24.04) already has PHP 8.3, Composer, WP-CLI, MariaDB, Redis, Node, Docker
# and Playwright with Chromium. This adds PHP 8.5 to match the VPS (Ubuntu 26.04) and the linters.

export DEBIAN_FRONTEND=noninteractive

# PHP 8.5 from the ondrej/php archive, made the default `php`. If this fails, PHP 8.3 stays.
PHP=8.5
if [ ! -x "/usr/bin/php$PHP" ]; then
  { curl -fsSL 'https://keyserver.ubuntu.com/pks/lookup?op=get&search=0x14AA40EC0831756756D7F66C4F4EA0AAE5267A6C' |
    gpg --dearmor --yes -o /usr/share/keyrings/ondrej-php.gpg &&
    echo "deb [signed-by=/usr/share/keyrings/ondrej-php.gpg] https://ppa.launchpadcontent.net/ondrej/php/ubuntu noble main" \
      >/etc/apt/sources.list.d/ondrej-php.list &&
    apt-get update -qq &&
    apt-get install -y -qq --no-install-recommends "php$PHP-cli" "php$PHP-mysql" "php$PHP-curl" "php$PHP-gd" \
      "php$PHP-intl" "php$PHP-mbstring" "php$PHP-xml" "php$PHP-zip" "php$PHP-bcmath" "php$PHP-imagick" "php$PHP-redis"; } ||
    echo "PHP $PHP install failed; keeping the image's PHP" >&2
fi
[ -x "/usr/bin/php$PHP" ] && update-alternatives --set php "/usr/bin/php$PHP"

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

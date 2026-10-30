#!/bin/bash
# Setup script for the Claude Code cloud environment (docs/KICKOFF-BRIEF.md section 6).
#
# Paste this whole file into claude.ai/code -> environment -> Edit -> Setup script. It runs as root on
# Ubuntu 24.04 before Claude starts; the resulting disk (files, not running processes) is cached for
# later sessions. The cache is only kept when the script finishes in about five minutes, so the whole
# run is time-boxed (BUDGET below), every step is time-boxed again, and no step can fail the script.
# Facts behind it: docs/runbooks/sandbox.md.
#
# Already in the image, so not installed here: PHP 8.3 + Composer, WP-CLI, Node 22, Docker with
# compose, Playwright with Chromium under /opt/pw-browsers (never run `playwright install`), gh, jq.
#
#   bash infra/cloud-setup.sh [repo-dir ...]   (default: every checkout under /home/user)
#
# Idempotent: safe to re-run inside a session, for example to warm the caches after a dependency
# change. Progress is logged to /var/log/cigarholic-cloud-setup.log.

export DEBIAN_FRONTEND=noninteractive COMPOSER_ALLOW_SUPERUSER=1 WP_CLI_ALLOW_ROOT=1
BUDGET=${CLOUD_SETUP_BUDGET:-290}
START=$(date +%s)
LOG=/var/log/cigarholic-cloud-setup.log

log() { printf '[cloud-setup +%4ss] %s\n' "$(( $(date +%s) - START ))" "$*" | tee -a "$LOG" >&2; }
left() { echo $(( BUDGET - ($(date +%s) - START) )); }
# boxed <max-seconds> <label> <command...>: runs the command under the smaller of max-seconds and the
# remaining budget, logs the outcome, and never fails the script.
boxed() {
  local max=$1 label=$2 remaining status
  shift 2
  remaining=$(left)
  if [ "$remaining" -le 10 ]; then
    log "skip  $label (budget exhausted)"
    return 0
  fi
  [ "$max" -lt "$remaining" ] || max=$remaining
  if timeout --foreground "$max" "$@" >>"$LOG" 2>&1; then
    log "ok    $label"
  else
    status=$?
    log "fail  $label (exit $status, see $LOG)"
  fi
  return 0
}

log "start, budget ${BUDGET}s"

# 1. Shell defaults for every later shell: Composer plugins as root, WP-CLI as root.
printf 'export COMPOSER_ALLOW_SUPERUSER=1\nexport WP_CLI_ALLOW_ROOT=1\n' >/etc/profile.d/cigarholic.sh

# 2. OS packages: shellcheck (shell lint) and the MariaDB client (ad-hoc access to the compose database).
if ! command -v shellcheck >/dev/null 2>&1 || ! command -v mariadb >/dev/null 2>&1; then
  boxed 90 "apt: shellcheck, mariadb-client" bash -c \
    'apt-get update -qq && apt-get install -y -qq --no-install-recommends shellcheck mariadb-client'
fi

# 3. actionlint (GitHub workflow lint), built with the image's Go toolchain.
if ! command -v actionlint >/dev/null 2>&1 && command -v go >/dev/null 2>&1; then
  boxed 120 "actionlint" env GOBIN=/usr/local/bin go install github.com/rhysd/actionlint/cmd/actionlint@v1.7.12
fi

# 4. WP-CLI on the host. The image has it; this covers an image that doesn't.
if ! command -v wp >/dev/null 2>&1; then
  boxed 60 "wp-cli" bash -c \
    'curl -fsSL -o /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && chmod 755 /usr/local/bin/wp'
fi

# 5. Docker Hub answers anonymous pulls from this shared egress with 429 at times; pull through
# Google's mirror instead (Docker falls back to Docker Hub itself when the mirror misses).
if [ ! -f /etc/docker/daemon.json ]; then
  mkdir -p /etc/docker
  printf '{\n  "registry-mirrors": ["https://mirror.gcr.io"]\n}\n' >/etc/docker/daemon.json
fi

# 6. The checkouts to warm up.
repo_dirs=("$@")
[ ${#repo_dirs[@]} -gt 0 ] || repo_dirs=(/home/user/*/)

# 7. Compose images first: building the PHP image is the slowest step and gains most from the cache.
compose_files=()
for dir in "${repo_dirs[@]}"; do
  for name in compose.yaml compose.yml docker-compose.yaml docker-compose.yml; do
    [ -f "$dir/$name" ] && compose_files+=("$dir/$name")
  done
done
if [ ${#compose_files[@]} -gt 0 ]; then
  started_dockerd=""
  if ! docker info >/dev/null 2>&1; then
    # `service docker start` fails in this VM (ulimit is not permitted); dockerd itself works.
    nohup dockerd >>/var/log/dockerd-setup.log 2>&1 &
    started_dockerd=$!
    for _ in $(seq 1 30); do docker info >/dev/null 2>&1 && break; sleep 1; done
  fi
  for file in "${compose_files[@]}"; do
    boxed 120 "compose pull $file" docker compose -f "$file" pull --ignore-buildable --quiet
    boxed 200 "compose build $file" docker compose -f "$file" build --quiet
  done
  # Stop the daemon we started so the cached disk holds a cleanly shut down image store.
  if [ -n "$started_dockerd" ]; then
    kill "$started_dockerd" 2>/dev/null
    wait "$started_dockerd" 2>/dev/null
  fi
fi

# 8. Composer and npm caches, so `composer install` and `npm ci` in a session take seconds, plus the
# OS libraries Chromium needs when the project has a Playwright suite (the browser itself is in the image).
for dir in "${repo_dirs[@]}"; do
  if [ -f "$dir/composer.lock" ]; then
    boxed 180 "composer install $dir" composer --working-dir="$dir" install --no-interaction --no-progress --prefer-dist --no-scripts
  fi
  if [ -f "$dir/package-lock.json" ]; then
    boxed 90 "npm ci $dir" npm --prefix "$dir" ci --no-audit --no-fund --loglevel=error
  fi
  if [ -f "$dir/playwright.config.ts" ] || [ -f "$dir/playwright.config.js" ]; then
    boxed 90 "playwright OS deps $dir" bash -c "cd '$dir' && npx --yes playwright install-deps chromium"
  fi
done

log "done"
exit 0

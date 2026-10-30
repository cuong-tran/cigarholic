#!/bin/bash
# Setup script for the Claude Code cloud environment (docs/KICKOFF-BRIEF.md section 6).
#
# Paste this whole file into claude.ai/code -> environment -> Edit -> Setup script. It runs as root
# on Ubuntu 24.04 before Claude starts, and the resulting disk (files only, not running processes)
# is cached for later sessions. It must exit 0 and finish in about five minutes, so every step is
# allowed to fail without blocking the session. Facts behind it: docs/runbooks/sandbox.md.
#
# Already in the image, so not installed here: PHP 8.3 + Composer, WP-CLI, MariaDB, Redis, Node 22,
# Docker with compose, and Playwright with Chromium (don't run `playwright install`).
#
#   bash infra/cloud-setup.sh [repo-dir ...]   (default: every checkout under /home/user)

export DEBIAN_FRONTEND=noninteractive

# Linters: shellcheck for shell scripts, actionlint for GitHub workflows.
(apt-get update -qq && apt-get install -y -qq --no-install-recommends shellcheck) || true
if command -v go >/dev/null 2>&1; then
  (cd /tmp && GOBIN=/usr/local/bin go install github.com/rhysd/actionlint/cmd/actionlint@v1.7.12) || true
fi

# WP-CLI on the host. The image has it; this covers an image that doesn't.
if ! command -v wp >/dev/null 2>&1; then
  (curl -fsSL -o /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar &&
    chmod 755 /usr/local/bin/wp) || true
fi

# Docker Hub answers anonymous pulls from this shared egress with 429 Too Many Requests at times;
# pull Docker Hub images through Google's mirror instead (Docker falls back to Docker Hub itself).
if [ ! -f /etc/docker/daemon.json ]; then
  mkdir -p /etc/docker
  printf '{\n  "registry-mirrors": ["https://mirror.gcr.io"]\n}\n' >/etc/docker/daemon.json
fi

# Pull and build the Docker Compose images of each checkout that has a compose file, so sessions
# start with them on disk. Skipped when the repo isn't checked out yet or has no compose file.
repo_dirs=("$@")
[ ${#repo_dirs[@]} -gt 0 ] || repo_dirs=(/home/user/*/)
compose_files=()
for dir in "${repo_dirs[@]}"; do
  for name in compose.yaml compose.yml docker-compose.yaml docker-compose.yml \
    infra/compose.yaml infra/compose.yml infra/docker-compose.yaml infra/docker-compose.yml; do
    [ -f "$dir/$name" ] && compose_files+=("$dir/$name")
  done
done

if [ ${#compose_files[@]} -gt 0 ]; then
  started_dockerd=""
  if ! docker info >/dev/null 2>&1; then
    # `service docker start` fails in this VM (ulimit is not permitted); dockerd itself works.
    nohup dockerd >/var/log/dockerd-setup.log 2>&1 &
    started_dockerd=$!
    for _ in $(seq 1 30); do docker info >/dev/null 2>&1 && break; sleep 1; done
  fi
  for file in "${compose_files[@]}"; do
    docker compose -f "$file" pull --ignore-buildable || true
    docker compose -f "$file" build || true
  done
  # Stop the daemon we started so the cached disk holds a cleanly shut down image store.
  if [ -n "$started_dockerd" ]; then
    kill "$started_dockerd" 2>/dev/null
    wait "$started_dockerd" 2>/dev/null
  fi
fi

exit 0

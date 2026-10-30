#!/usr/bin/env bash
# FND-08: runs on the server as the site user (called by .github/workflows/deploy.yml over SSH).
#
#   deploy-release.sh activate RELEASE_ID   link shared files, back up the database, switch the
#                                           'current' symlink, migrate, provision, smoke test
#   deploy-release.sh rollback              point 'current' at the previous release
#   deploy-release.sh list                  show releases, newest first
#
# The database is not rolled back automatically; a dump taken before each deploy is kept in
# shared/backups/.
set -euo pipefail

SITE_ROOT="${SITE_ROOT:-$HOME}"
KEEP_RELEASES="${KEEP_RELEASES:-5}"
KEEP_BACKUPS="${KEEP_BACKUPS:-10}"

log() { printf '==> %s\n' "$*"; }
die() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

SELF="$(cd "$(dirname "$0")" && pwd)/$(basename "$0")"
cd "$SITE_ROOT"

env_value() {
  sed -n "s/^$1=['\"]\{0,1\}\([^'\"]*\)['\"]\{0,1\}$/\1/p" "$SITE_ROOT/shared/.env" | head -n1
}

releases_newest_first() { find releases -mindepth 1 -maxdepth 1 -type d -printf '%f\n' | sort -r; }
current_release() { basename "$(readlink current 2>/dev/null || echo none)"; }

switch_to() {
  ln -sfn "releases/$1" current.next
  mv -Tf current.next current
  log "current -> releases/$1"
}

smoke_test() {
  local host code
  host="$(env_value WP_HOME)"
  host="${host#https://}"
  host="${host%%/*}"
  code="$(curl -sk -o /dev/null -w '%{http_code}' --max-time 30 --resolve "$host:443:127.0.0.1" "https://$host/")"
  log "smoke test https://$host/ -> HTTP $code"
  [[ "$code" =~ ^(200|301|302)$ ]]
}

activate() {
  local id="$1" release="releases/$1" previous prefix
  [ -d "$release" ] || die "no such release: $release"
  [ -f shared/.env ] || die "shared/.env is missing; run server/add-site.sh as root first"
  previous="$(current_release)"

  # A failed release is deleted, so releases/ only holds releases that went live (rollback targets).
  fail() { rm -rf "${SITE_ROOT:?}/$release"; die "$@"; }

  log "Linking shared files into $release"
  ln -sfn "$SITE_ROOT/shared/.env" "$release/.env"
  rm -rf "$release/web/app/uploads"
  ln -sfn "$SITE_ROOT/shared/web/app/uploads" "$release/web/app/uploads"

  cd "$release"
  # Check the database directly: `wp core is-installed` also fails when the new code is broken.
  prefix="$(env_value DB_PREFIX)"
  if [ -n "$(wp db query "SHOW TABLES LIKE '${prefix:-wp_}options'" --skip-column-names)" ]; then
    wp core is-installed || fail "WordPress fails to load in this release (see above); 'current' was not switched"
    log "Backing up the database"
    wp db export - | gzip >"$SITE_ROOT/shared/backups/$id-predeploy.sql.gz"
  elif [ -f "$SITE_ROOT/shared/.wp-install" ]; then
    log "First deploy: installing WordPress"
    # shellcheck source=/dev/null
    . "$SITE_ROOT/shared/.wp-install"
    wp core install --url="$(env_value WP_HOME)" --title="$TITLE" \
      --admin_user="$ADMIN_USER" --admin_password="$ADMIN_PASSWORD" --admin_email="$ADMIN_EMAIL" --skip-email
    rm -f "$SITE_ROOT/shared/.wp-install"
  else
    fail "WordPress is not installed and shared/.wp-install is missing"
  fi
  cd "$SITE_ROOT"

  switch_to "$id"
  if ! (cd current && log "Database migrations" && wp core update-db &&
    log "Provisioning" && bash provision/run.sh && wp cache flush) || ! smoke_test; then
    if [ "$previous" != none ] && [ -d "releases/$previous" ]; then
      switch_to "$previous"
      fail "deploy failed; rolled back to $previous (database backup: shared/backups/$id-predeploy.sql.gz)"
    fi
    die "deploy failed and there is no previous release to roll back to"
  fi

  log "Pruning old releases and backups"
  releases_newest_first | tail -n +"$((KEEP_RELEASES + 1))" | while read -r old; do
    [ "$old" = "$id" ] || rm -rf "releases/$old"
  done
  find shared/backups -name '*.sql.gz' -printf '%f\n' | sort -r | tail -n +"$((KEEP_BACKUPS + 1))" |
    while read -r old; do rm -f "shared/backups/$old"; done

  log "Deployed $id"
}

rollback() {
  local current target
  current="$(current_release)"
  target="$(releases_newest_first | awk -v cur="$current" 'found { print; exit } $0 == cur { found = 1 }')"
  [ -n "$target" ] || die "no release older than $current"
  switch_to "$target"
  (cd current && wp cache flush)
  smoke_test || die "smoke test failed after rollback"
}

case "${1:-}" in
  activate) [ -n "${2:-}" ] || die "usage: $0 activate RELEASE_ID"; activate "$2" ;;
  rollback) rollback ;;
  list) current="$(current_release)"; releases_newest_first | sed "s/^$current\$/& (current)/" ;;
  *) awk 'NR > 1 && !/^#/ { exit } NR > 1 { sub(/^# ?/, ""); print }' "$SELF"; exit 1 ;;
esac

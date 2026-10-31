#!/usr/bin/env bash
# Restores a production backup from B2 into a site (OPS-07). The normal use is the restore drill
# into staging: database import, `wp search-replace` of the home URL, uploads sync, test-mode payment
# settings re-asserted. Restoring into production is refused unless the long flag is given.
#
# Usage: restore.sh <backup-timestamp|latest> --to staging
#        restore.sh <backup-timestamp|latest> --to production --i-know-this-overwrites-production
#
#   B2_BUCKET, B2_KEY_ID, B2_APPLICATION_KEY, B2_S3_ENDPOINT   (or /etc/cigarholic/backup.env as root)
#   RESTORE_SOURCE_SITE   site whose backups are read (default production)
#   RESTORE_SKIP_UPLOADS=1   database only
set -euo pipefail

BACKUP="${1:-}"; shift || true
TARGET=""; FORCE_PRODUCTION=0
while [ $# -gt 0 ]; do
  case "$1" in
    --to) TARGET="${2:-}"; shift ;;
    --i-know-this-overwrites-production) FORCE_PRODUCTION=1 ;;
    *) echo "restore: unknown argument $1" >&2; exit 2 ;;
  esac
  shift
done
[ -n "$BACKUP" ] && [ -n "$TARGET" ] || { echo "usage: restore.sh <timestamp|latest> --to <staging|production> [--i-know-this-overwrites-production]" >&2; exit 2; }
case "$TARGET" in
  staging) ;;
  production)
    [ "$FORCE_PRODUCTION" = 1 ] || { echo "restore: refusing to overwrite production without --i-know-this-overwrites-production" >&2; exit 3; } ;;
  *) echo "restore: --to must be staging or production" >&2; exit 2 ;;
esac
SOURCE_SITE="${RESTORE_SOURCE_SITE:-production}"

log() { printf '[restore:%s] %s\n' "$TARGET" "$*"; }
die() { printf '[restore:%s] ERROR: %s\n' "$TARGET" "$*" >&2; exit 1; }

# --- B2 ------------------------------------------------------------------------------------------
if [ -z "${B2_BUCKET:-}" ] && [ -r /etc/cigarholic/backup.env ]; then
  set -a
  # shellcheck source=/dev/null
  . /etc/cigarholic/backup.env
  set +a
fi
for v in B2_BUCKET B2_KEY_ID B2_APPLICATION_KEY B2_S3_ENDPOINT; do
  [ -n "${!v:-}" ] || die "$v is not set"
done
export RCLONE_CONFIG_B2_TYPE=s3 RCLONE_CONFIG_B2_PROVIDER=Other
export RCLONE_CONFIG_B2_ACCESS_KEY_ID="$B2_KEY_ID" RCLONE_CONFIG_B2_SECRET_ACCESS_KEY="$B2_APPLICATION_KEY"
export RCLONE_CONFIG_B2_ENDPOINT="$B2_S3_ENDPOINT"
REMOTE="B2:$B2_BUCKET/$SOURCE_SITE"

if [ "$BACKUP" = latest ]; then
  BACKUP=$(rclone lsd "$REMOTE" | awk '{print $NF}' | sort | tail -n1)
  [ -n "$BACKUP" ] || die "no backups found in $REMOTE"
fi
log "source $REMOTE/$BACKUP/"

# --- target site ---------------------------------------------------------------------------------
ROOT="/var/www/$TARGET"
ENV_FILE="$ROOT/shared/.env"
[ -r "$ENV_FILE" ] || die "cannot read $ENV_FILE"
[ -L "$ROOT/current" ] || die "$TARGET has no current release; deploy first"
env_value() { sed -n "s/^$1=['\"]\{0,1\}\([^'\"]*\)['\"]\{0,1\}$/\1/p" "$ENV_FILE" | head -n1; }
DB_NAME=$(env_value DB_NAME); DB_USER=$(env_value DB_USER); DB_PASSWORD=$(env_value DB_PASSWORD); DB_HOST=$(env_value DB_HOST)
TARGET_HOME=$(env_value WP_HOME)
case "$SOURCE_SITE" in production) SOURCE_HOME="https://cigarholic.com" ;; staging) SOURCE_HOME="https://staging.cigarholic.com" ;; *) die "unknown source site $SOURCE_SITE" ;; esac

run_wp() { # WP-CLI as the deploy user when we are root
  if [ "$(id -u)" -eq 0 ]; then sudo -u deploy -H wp --path="$ROOT/current/web/wp" "$@"; else wp --path="$ROOT/current/web/wp" "$@"; fi
}

work="$ROOT/shared/backups/restore-$BACKUP"
mkdir -p "$work"
log "downloading"
includes=(--include db.sql.gz --include MANIFEST.txt)
[ "${RESTORE_SKIP_UPLOADS:-0}" = 1 ] || includes+=(--include uploads.tar.gz)
rclone copy "$REMOTE/$BACKUP/" "$work/" "${includes[@]}"
[ -s "$work/db.sql.gz" ] || die "db.sql.gz missing in $REMOTE/$BACKUP/"
[ -f "$work/MANIFEST.txt" ] && sed 's/^/  /' "$work/MANIFEST.txt"

# Safety net: snapshot the target database before overwriting it.
if [ -x "$ROOT/current/infra/backup/snapshot.sh" ]; then
  bash "$ROOT/current/infra/backup/snapshot.sh" "$TARGET"
fi

creds=$(mktemp); trap 'rm -f "$creds"' EXIT
printf '[client]\nuser=%s\npassword=%s\nhost=%s\n' "$DB_USER" "$DB_PASSWORD" "${DB_HOST:-localhost}" >"$creds"; chmod 600 "$creds"

log "importing database into $DB_NAME"
# Drop everything first so tables that no longer exist in the backup do not linger.
mariadb --defaults-extra-file="$creds" --batch --skip-column-names -e "SELECT table_name FROM information_schema.tables WHERE table_schema='$DB_NAME'" "$DB_NAME" \
  | sed 's/.*/DROP TABLE IF EXISTS `&`;/' | { echo "SET FOREIGN_KEY_CHECKS=0;"; cat; } | mariadb --defaults-extra-file="$creds" "$DB_NAME"
gunzip -c "$work/db.sql.gz" | mariadb --defaults-extra-file="$creds" --default-character-set=utf8mb4 "$DB_NAME"

if [ "$TARGET_HOME" != "$SOURCE_HOME" ]; then
  log "search-replace $SOURCE_HOME -> $TARGET_HOME"
  run_wp search-replace "$SOURCE_HOME" "$TARGET_HOME" --all-tables --skip-columns=guid --report-changed-only
  run_wp search-replace "${SOURCE_HOME#https://}" "${TARGET_HOME#https://}" --all-tables --skip-columns=guid --report-changed-only
fi

if [ "${RESTORE_SKIP_UPLOADS:-0}" != 1 ] && [ -s "$work/uploads.tar.gz" ]; then
  log "syncing uploads"
  mkdir -p "$work/uploads-extract"
  tar -C "$work/uploads-extract" -xzf "$work/uploads.tar.gz"
  rsync -a --delete "$work/uploads-extract/uploads/" "$ROOT/shared/web/app/uploads/"
  rm -rf "$work/uploads-extract"
fi

if [ "$TARGET" = staging ]; then
  log "re-asserting staging safety: no indexing, payment gateways in test/sandbox mode without live keys"
  run_wp option update blog_public 0 >/dev/null || true
  # Stripe: live keys live in wp_options when the owner connected the account; staging must never hold them.
  if run_wp option get woocommerce_stripe_settings --format=json >/dev/null 2>&1; then
    run_wp option patch update woocommerce_stripe_settings testmode yes >/dev/null || true
    for k in secret_key publishable_key webhook_secret account_id; do run_wp option patch delete woocommerce_stripe_settings "$k" >/dev/null 2>&1 || true; done
  fi
  if run_wp option get woocommerce-ppcp-settings --format=json >/dev/null 2>&1; then
    run_wp option patch update woocommerce-ppcp-settings sandbox_on 1 >/dev/null || true
    for k in client_id client_secret merchant_id merchant_email; do run_wp option patch delete woocommerce-ppcp-settings "$k" >/dev/null 2>&1 || true; done
  fi
  run_wp cigarholic provision >/dev/null 2>&1 || log "wp cigarholic provision not available yet; staging settings not re-applied by it"
fi

run_wp cache flush >/dev/null 2>&1 || true
run_wp rewrite flush --hard >/dev/null 2>&1 || true
if [ "$(id -u)" -eq 0 ]; then /usr/local/sbin/cigarholic-cache-purge "$TARGET" >/dev/null 2>&1 || true; else sudo -n /usr/local/sbin/cigarholic-cache-purge "$TARGET" >/dev/null 2>&1 || true; fi

rm -f "$work/db.sql.gz" "$work/uploads.tar.gz"
log "done: $SOURCE_SITE backup $BACKUP restored into $TARGET ($TARGET_HOME). Check the site, then docs/runbooks/health-check.md."

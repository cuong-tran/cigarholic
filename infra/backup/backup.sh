#!/usr/bin/env bash
# Off-site backup of one site to Backblaze B2 (OPS-07): database dump (--single-transaction) and a
# tar of shared/web/app/uploads, uploaded with rclone's S3 backend under <bucket>/<site>/<timestamp>/.
# Retention: 30 days by bucket lifecycle (owner, docs/runbooks/backup-restore.md) plus
# `rclone delete --min-age 30d` here. Installed as /usr/local/bin/cigarholic-backup for the nightly
# systemd timer; also streamed by backup-restore.yml. Runs as root or deploy.
#
# Usage: backup.sh <staging|production> [--list] [--local-only]
#
#   B2_BUCKET, B2_KEY_ID, B2_APPLICATION_KEY, B2_S3_ENDPOINT   from the environment, or from
#   /etc/cigarholic/backup.env (written here when running as root with the variables set, so the
#   timer can use them). Exit 2 when they are missing, so a misconfigured timer shows as failed.
set -euo pipefail

SITE="${1:-}"
case "$SITE" in staging|production) ;; *) echo "usage: backup.sh <staging|production> [--list] [--local-only]" >&2; exit 2 ;; esac
shift
LIST=0; LOCAL_ONLY=0
for arg in "$@"; do
  case "$arg" in
    --list) LIST=1 ;;
    --local-only) LOCAL_ONLY=1 ;;
    *) echo "backup: unknown argument $arg" >&2; exit 2 ;;
  esac
done

ROOT="/var/www/$SITE"
ENV_FILE="$ROOT/shared/.env"
BACKUP_DIR="$ROOT/shared/backups"
CRED_FILE=/etc/cigarholic/backup.env
KEEP_LOCAL_DAYS=3
RETENTION="30d"

log() { printf '[backup:%s] %s\n' "$SITE" "$*"; }
die() { printf '[backup:%s] ERROR: %s\n' "$SITE" "$*" >&2; exit "${2:-1}"; }

# --- B2 credentials: environment first, then the root-only file ------------------------------
if [ -z "${B2_BUCKET:-}" ] && [ -r "$CRED_FILE" ]; then
  set -a
  # shellcheck source=/dev/null
  . "$CRED_FILE"
  set +a
fi
have_b2=1
for v in B2_BUCKET B2_KEY_ID B2_APPLICATION_KEY B2_S3_ENDPOINT; do
  [ -n "${!v:-}" ] || have_b2=0
done
if [ "$have_b2" = 1 ] && [ "$(id -u)" -eq 0 ]; then
  install -d -m 0700 /etc/cigarholic
  umask 077
  printf 'B2_BUCKET=%q\nB2_KEY_ID=%q\nB2_APPLICATION_KEY=%q\nB2_S3_ENDPOINT=%q\n' \
    "$B2_BUCKET" "$B2_KEY_ID" "$B2_APPLICATION_KEY" "$B2_S3_ENDPOINT" >"$CRED_FILE.tmp"
  mv -f "$CRED_FILE.tmp" "$CRED_FILE"
  umask 022
fi
if [ "$have_b2" = 1 ]; then
  export RCLONE_CONFIG_B2_TYPE=s3 RCLONE_CONFIG_B2_PROVIDER=Other
  export RCLONE_CONFIG_B2_ACCESS_KEY_ID="$B2_KEY_ID" RCLONE_CONFIG_B2_SECRET_ACCESS_KEY="$B2_APPLICATION_KEY"
  export RCLONE_CONFIG_B2_ENDPOINT="$B2_S3_ENDPOINT" RCLONE_CONFIG_B2_ACL=private
  REMOTE="B2:$B2_BUCKET/$SITE"
fi

if [ "$LIST" = 1 ]; then
  [ "$have_b2" = 1 ] || die "B2_* variables are not set" 2
  log "backups in $REMOTE (newest last):"
  rclone lsd "$REMOTE" 2>/dev/null | awk '{print "  " $NF}' | sort || log "(none yet)"
  exit 0
fi
if [ "$have_b2" = 0 ] && [ "$LOCAL_ONLY" = 0 ]; then
  die "B2_BUCKET, B2_KEY_ID, B2_APPLICATION_KEY and B2_S3_ENDPOINT are not set: nothing uploaded (run backup-restore.yml once with the secrets, as root, to store them in $CRED_FILE)" 2
fi

# --- database credentials from .env ------------------------------------------------------------
[ -r "$ENV_FILE" ] || die "cannot read $ENV_FILE"
env_value() { sed -n "s/^$1=['\"]\{0,1\}\([^'\"]*\)['\"]\{0,1\}$/\1/p" "$ENV_FILE" | head -n1; }
DB_NAME=$(env_value DB_NAME); DB_USER=$(env_value DB_USER); DB_PASSWORD=$(env_value DB_PASSWORD); DB_HOST=$(env_value DB_HOST)
[ -n "$DB_NAME" ] && [ -n "$DB_USER" ] || die "DB_NAME/DB_USER missing in $ENV_FILE"

mkdir -p "$BACKUP_DIR"
stamp=$(date -u +%Y-%m-%dT%H%M%SZ)
work="$BACKUP_DIR/nightly-$stamp"
mkdir -p "$work"
creds=$(mktemp)
cleanup() { rm -f "$creds"; }
trap cleanup EXIT
printf '[client]\nuser=%s\npassword=%s\nhost=%s\n' "$DB_USER" "$DB_PASSWORD" "${DB_HOST:-localhost}" >"$creds"
chmod 600 "$creds"

log "dumping database $DB_NAME"
mariadb-dump --defaults-extra-file="$creds" --single-transaction --quick --routines --triggers --events \
  --default-character-set=utf8mb4 --no-tablespaces "$DB_NAME" | gzip -6 >"$work/db.sql.gz"

log "archiving uploads"
tar -C "$ROOT/shared/web/app" -czf "$work/uploads.tar.gz" uploads
# The .env is never backed up: site.sh regenerates it and the deploy workflow re-upserts the keys.
cat >"$work/MANIFEST.txt" <<EOF
site=$SITE
timestamp=$stamp
host=$(hostname)
release=$(readlink "$ROOT/current" 2>/dev/null || echo none)
revision=$(cat "$ROOT/current/REVISION" 2>/dev/null || echo none)
db=$DB_NAME
db_size=$(du -h "$work/db.sql.gz" | cut -f1)
uploads_size=$(du -h "$work/uploads.tar.gz" | cut -f1)
EOF
log "local: $work ($(du -sh "$work" | cut -f1))"

if [ "$LOCAL_ONLY" = 0 ]; then
  log "uploading to $REMOTE/$stamp/"
  rclone copy --s3-no-check-bucket --transfers 4 --checksum "$work" "$REMOTE/$stamp/"
  rclone check --one-way "$work" "$REMOTE/$stamp/" >/dev/null 2>&1 || die "upload verification failed for $REMOTE/$stamp/"
  log "applying retention ($RETENTION) in $REMOTE"
  rclone delete --min-age "$RETENTION" "$REMOTE" || log "retention delete reported an error (bucket lifecycle still applies)"
  rclone rmdirs --leave-root "$REMOTE" 2>/dev/null || true
  echo "$stamp $REMOTE/$stamp/ db=$(du -h "$work/db.sql.gz" | cut -f1) uploads=$(du -h "$work/uploads.tar.gz" | cut -f1)" >"$BACKUP_DIR/last-backup.txt"
fi

# Local copies: keep a few days of nightly dumps (without the uploads tar, which is large).
rm -f "$work/uploads.tar.gz"
find "$BACKUP_DIR" -maxdepth 1 -type d -name 'nightly-*' -mtime "+$KEEP_LOCAL_DAYS" -exec rm -rf {} + 2>/dev/null || true
log "done"

#!/usr/bin/env bash
# Local database snapshot of one site before a deploy (OPS-07): mariadb-dump --single-transaction to
# /var/www/<site>/shared/backups/pre-deploy-<timestamp>.sql.gz, keeping the last 10. Called by the
# Deployer recipe on production; safe to run by hand as deploy or root.
# Usage: snapshot.sh <staging|production>
set -euo pipefail

SITE="${1:-}"
case "$SITE" in staging|production) ;; *) echo "usage: snapshot.sh <staging|production>" >&2; exit 2 ;; esac
ROOT="/var/www/$SITE"
ENV_FILE="$ROOT/shared/.env"
BACKUP_DIR="$ROOT/shared/backups"
KEEP=10

[ -r "$ENV_FILE" ] || { echo "snapshot: cannot read $ENV_FILE" >&2; exit 1; }
env_value() { sed -n "s/^$1=['\"]\{0,1\}\([^'\"]*\)['\"]\{0,1\}$/\1/p" "$ENV_FILE" | head -n1; }
DB_NAME=$(env_value DB_NAME); DB_USER=$(env_value DB_USER); DB_PASSWORD=$(env_value DB_PASSWORD); DB_HOST=$(env_value DB_HOST)
[ -n "$DB_NAME" ] && [ -n "$DB_USER" ] || { echo "snapshot: DB_NAME/DB_USER missing in $ENV_FILE" >&2; exit 1; }

mkdir -p "$BACKUP_DIR"
stamp=$(date -u +%Y-%m-%dT%H%M%SZ)
target="$BACKUP_DIR/pre-deploy-$stamp.sql.gz"
# Credentials go through a temporary defaults file, never the command line.
creds=$(mktemp)
trap 'rm -f "$creds"' EXIT
printf '[client]\nuser=%s\npassword=%s\nhost=%s\n' "$DB_USER" "$DB_PASSWORD" "${DB_HOST:-localhost}" >"$creds"
chmod 600 "$creds"

mariadb-dump --defaults-extra-file="$creds" --single-transaction --quick --routines --triggers --events \
  --default-character-set=utf8mb4 --no-tablespaces "$DB_NAME" | gzip -6 >"$target.tmp"
mv -f "$target.tmp" "$target"
echo "snapshot: $target ($(du -h "$target" | cut -f1))"

# Keep the newest $KEEP pre-deploy dumps.
ls -1t "$BACKUP_DIR"/pre-deploy-*.sql.gz 2>/dev/null | tail -n +"$((KEEP + 1))" | while read -r old; do
  rm -f "$old" && echo "snapshot: removed $old"
done

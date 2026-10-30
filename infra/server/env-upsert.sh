#!/usr/bin/env bash
# Upserts variables into a site's shared/.env atomically (FND-09, OPS-07). Used by the deploy
# workflows to push application keys from GitHub into the server: STAGING_ / PRODUCTION_-prefixed
# secrets become unprefixed .env keys. Runs as root or as the deploy user (owner of .env).
#
# Usage:
#   env-upsert.sh <staging|production> KEY=VALUE [KEY=VALUE ...]
#   env-upsert.sh <staging|production> KEY=@ENV_NAME ...     value taken from the environment
#                                                          variable ENV_NAME; skipped when unset
#                                                          or empty (keeps the current value)
#   env-upsert.sh <staging|production> --unset KEY ...       removes keys
#
# Values are written single-quoted (literal for phpdotenv); a value containing a single quote is
# written double-quoted with \\, \" and \$ escaped. The file keeps its owner, group and mode.
set -euo pipefail

SITE="${1:-}"
case "$SITE" in
  staging|production) ;;
  *) echo "usage: env-upsert.sh <staging|production> KEY=VALUE|KEY=@ENV_NAME ... | --unset KEY ..." >&2; exit 2 ;;
esac
shift
ENV_FILE="${ENV_FILE:-/var/www/$SITE/shared/.env}"
[ -f "$ENV_FILE" ] || { echo "env-upsert: $ENV_FILE does not exist (run site.sh $SITE first)" >&2; exit 1; }
[ -w "$ENV_FILE" ] || { echo "env-upsert: $ENV_FILE is not writable by $(id -un)" >&2; exit 1; }

declare -A SET=()
declare -a ORDER=()
declare -A UNSET=()
mode="set"
for arg in "$@"; do
  if [ "$arg" = "--unset" ]; then mode="unset"; continue; fi
  if [ "$mode" = unset ]; then
    [[ "$arg" =~ ^[A-Z][A-Z0-9_]*$ ]] || { echo "env-upsert: invalid key '$arg'" >&2; exit 2; }
    UNSET[$arg]=1
    continue
  fi
  [[ "$arg" =~ ^([A-Z][A-Z0-9_]*)=(.*)$ ]] || { echo "env-upsert: expected KEY=VALUE, got '$arg'" >&2; exit 2; }
  key="${BASH_REMATCH[1]}"; value="${BASH_REMATCH[2]}"
  if [[ "$value" == @* ]]; then
    src="${value#@}"
    [[ "$src" =~ ^[A-Za-z_][A-Za-z0-9_]*$ ]] || { echo "env-upsert: invalid variable name '$src'" >&2; exit 2; }
    if [ -z "${!src:-}" ]; then
      echo "env-upsert: $key skipped ($src is not set)"
      continue
    fi
    value="${!src}"
  fi
  [ -n "${SET[$key]+x}" ] || ORDER+=("$key")
  SET[$key]="$value"
done

quote() { # dotenv quoting
  local v=$1
  if [[ "$v" != *"'"* ]]; then
    printf "'%s'" "$v"
  else
    v=${v//\\/\\\\}; v=${v//\"/\\\"}; v=${v//\$/\\\$}
    printf '"%s"' "$v"
  fi
}

tmp=$(mktemp "$(dirname "$ENV_FILE")/.env.XXXXXX")
trap 'rm -f "$tmp"' EXIT
declare -A SEEN=()
changed=0; added=0; removed=0
while IFS= read -r line || [ -n "$line" ]; do
  if [[ "$line" =~ ^([A-Z][A-Z0-9_]*)=(.*)$ ]]; then
    key="${BASH_REMATCH[1]}"
    if [ -n "${UNSET[$key]+x}" ]; then removed=$((removed + 1)); continue; fi
    if [ -n "${SET[$key]+x}" ]; then
      SEEN[$key]=1
      new="$key=$(quote "${SET[$key]}")"
      [ "$new" = "$line" ] || changed=$((changed + 1))
      printf '%s\n' "$new"
      continue
    fi
  fi
  printf '%s\n' "$line"
done <"$ENV_FILE" >"$tmp"
for key in "${ORDER[@]}"; do
  [ -n "${SEEN[$key]+x}" ] && continue
  printf '%s=%s\n' "$key" "$(quote "${SET[$key]}")" >>"$tmp"
  added=$((added + 1))
done

# Keep owner, group and mode of the original (chown needs root; the group is shared with deploy).
chmod --reference="$ENV_FILE" "$tmp"
chgrp --reference="$ENV_FILE" "$tmp" 2>/dev/null || true
if [ "$(id -u)" -eq 0 ]; then chown --reference="$ENV_FILE" "$tmp"; fi
mv -f "$tmp" "$ENV_FILE"
trap - EXIT
echo "env-upsert: $ENV_FILE updated ($changed changed, $added added, $removed removed; keys: ${ORDER[*]:-none})"

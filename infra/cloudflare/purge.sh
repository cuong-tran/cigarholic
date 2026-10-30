#!/usr/bin/env bash
# Purges the Cloudflare cache of cigarholic.com (OPS-04). Called by the Deployer recipe after a
# production deploy when the token and zone id are present; otherwise harmless to skip (ADR 0008).
# Usage: purge.sh [--dry-run] [url ...]   (no urls = purge everything)
set -euo pipefail
# shellcheck source=infra/cloudflare/lib.sh
source "$(dirname "$(readlink -f "$0")")/lib.sh"

urls=()
for arg in "$@"; do
  [ "$arg" = "--dry-run" ] || urls+=("$arg")
done

if [ ${#urls[@]} -eq 0 ]; then
  cf_api POST "/zones/$ZONE_CIGARHOLIC/purge_cache" '{"purge_everything":true}' >/dev/null
  cf_log "purged everything on cigarholic.com"
else
  body=$(printf '%s\n' "${urls[@]}" | jq -R . | jq -sc '{files: .}')
  cf_api POST "/zones/$ZONE_CIGARHOLIC/purge_cache" "$body" >/dev/null
  cf_log "purged ${#urls[@]} url(s) on cigarholic.com"
fi

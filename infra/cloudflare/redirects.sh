#!/usr/bin/env bash
# Cloudflare Single Redirects (OPS-03, ADR 0008): www.cigarholic.com, max2lab.com and www.max2lab.com
# -> https://cigarholic.com, path and query preserved, 301. One dynamic redirect rule per zone in the
# http_request_dynamic_redirect phase; the Nginx server blocks stay as the fallback.
# Usage: redirects.sh [--dry-run]
set -euo pipefail
# shellcheck source=infra/cloudflare/lib.sh
source "$(dirname "$(readlink -f "$0")")/lib.sh"

# redirect_rule <description> <expression>
redirect_rule() {
  jq -nc --arg d "$1" --arg e "$2" '{
    description: $d,
    expression: $e,
    action: "redirect",
    action_parameters: {
      from_value: {
        status_code: 301,
        preserve_query_string: true,
        target_url: { expression: "concat(\"https://cigarholic.com\", http.request.uri.path)" }
      }
    },
    enabled: true
  }'
}

cf_put_ruleset "$ZONE_CIGARHOLIC" http_request_dynamic_redirect "Cigarholic canonical host" \
  "[$(redirect_rule "www.cigarholic.com -> cigarholic.com" '(http.host eq "www.cigarholic.com")')]"

cf_put_ruleset "$ZONE_MAX2LAB" http_request_dynamic_redirect "max2lab.com -> cigarholic.com" \
  "[$(redirect_rule "max2lab.com and www.max2lab.com -> cigarholic.com" '(http.host in {"max2lab.com" "www.max2lab.com"})')]"

cf_log "redirects done"

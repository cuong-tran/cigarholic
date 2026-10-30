#!/usr/bin/env bash
# Cloudflare WAF for cigarholic.com (OPS-09, ADR 0008): a custom rule that blocks xmlrpc.php and a
# rate-limiting rule for wp-login.php posts and checkout. Free-plan compatible (ends_with/starts_with
# instead of regex; one rate-limiting rule; 10 s period). Nginx enforces the same at the origin.
# Usage: waf.sh [--dry-run]
set -euo pipefail
# shellcheck source=infra/cloudflare/lib.sh
source "$(dirname "$(readlink -f "$0")")/lib.sh"

CUSTOM_RULES=$(jq -nc '[
  {
    description: "Block xmlrpc.php (Bedrock: /xmlrpc.php and /wp/xmlrpc.php)",
    expression: "(ends_with(http.request.uri.path, \"/xmlrpc.php\"))",
    action: "block",
    enabled: true
  }
]')

RATELIMIT_RULES=$(jq -nc '[
  {
    description: "Rate limit login posts and checkout per IP",
    expression: "(ends_with(http.request.uri.path, \"/wp-login.php\") and http.request.method eq \"POST\") or (starts_with(http.request.uri.path, \"/checkout\")) or (starts_with(http.request.uri.path, \"/wp-json/wc/store/v1/checkout\"))",
    action: "block",
    ratelimit: {
      characteristics: ["cf.colo.id", "ip.src"],
      period: 10,
      requests_per_period: 20,
      mitigation_timeout: 10
    },
    enabled: true
  }
]')

cf_put_ruleset "$ZONE_CIGARHOLIC" http_request_firewall_custom "Cigarholic custom rules" "$CUSTOM_RULES"
cf_put_ruleset "$ZONE_CIGARHOLIC" http_ratelimit "Cigarholic rate limits" "$RATELIMIT_RULES"
cf_log "waf done"

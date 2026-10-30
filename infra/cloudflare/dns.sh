#!/usr/bin/env bash
# Cloudflare DNS for both zones (OPS-02). Idempotent upsert of proxied A records:
#   cigarholic.com zone: apex, www, staging -> ORIGIN_IP
#   max2lab.com zone:    apex, www          -> ORIGIN_IP (placeholder so the redirect rule fires;
#                                              Nginx also redirects them, ADR 0008)
# Usage: ORIGIN_IP=<vps ip> dns.sh [--dry-run]
set -euo pipefail
# shellcheck source=infra/cloudflare/lib.sh
source "$(dirname "$(readlink -f "$0")")/lib.sh"

ORIGIN_IP="${ORIGIN_IP:-}"
if [ -z "$ORIGIN_IP" ]; then
  [ "$DRY_RUN" = 1 ] && ORIGIN_IP="203.0.113.10" || cf_die "ORIGIN_IP is not set (the VPS address, from the SSH_HOST secret)"
fi
TTL=1  # automatic (proxied records ignore TTL)

# upsert_a <zone> <fqdn>
upsert_a() {
  local zone=$1 name=$2 existing id content proxied body
  body=$(jq -nc --arg name "$name" --arg ip "$ORIGIN_IP" --argjson ttl "$TTL" \
    '{type: "A", name: $name, content: $ip, ttl: $ttl, proxied: true, comment: "managed by infra/cloudflare/dns.sh"}')
  existing=$(cf_api GET "/zones/$zone/dns_records?type=A&name=$name")
  id=$(jq -r '.result[0].id // empty' <<<"$existing")
  if [ -z "$id" ]; then
    cf_api POST "/zones/$zone/dns_records" "$body" >/dev/null
    cf_log "$name: A $ORIGIN_IP proxied (created)"
    return 0
  fi
  content=$(jq -r '.result[0].content' <<<"$existing")
  proxied=$(jq -r '.result[0].proxied' <<<"$existing")
  if [ "$content" = "$ORIGIN_IP" ] && [ "$proxied" = "true" ]; then
    cf_log "$name: A $ORIGIN_IP proxied (unchanged)"
    return 0
  fi
  cf_api PATCH "/zones/$zone/dns_records/$id" "$body" >/dev/null
  cf_log "$name: A $content proxied=$proxied -> $ORIGIN_IP proxied (updated)"
}

upsert_a "$ZONE_CIGARHOLIC" "cigarholic.com"
upsert_a "$ZONE_CIGARHOLIC" "www.cigarholic.com"
upsert_a "$ZONE_CIGARHOLIC" "staging.cigarholic.com"
upsert_a "$ZONE_MAX2LAB" "max2lab.com"
upsert_a "$ZONE_MAX2LAB" "www.max2lab.com"
cf_log "dns done"

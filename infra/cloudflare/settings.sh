#!/usr/bin/env bash
# Cloudflare zone settings for both zones (OPS-02, OPS-03): SSL Full (strict), Always Use HTTPS,
# minimum TLS 1.2, Automatic HTTPS Rewrites. Idempotent: only differing values are patched.
# Full (strict) needs a valid certificate on the origin (Let's Encrypt from site.sh, or an Origin CA
# certificate once proxied; ADR 0008).
# Usage: settings.sh [--dry-run]
set -euo pipefail
# shellcheck source=infra/cloudflare/lib.sh
source "$(dirname "$(readlink -f "$0")")/lib.sh"

for zone in "$ZONE_CIGARHOLIC" "$ZONE_MAX2LAB"; do
  cf_zone_setting "$zone" ssl strict
  cf_zone_setting "$zone" always_use_https on
  cf_zone_setting "$zone" min_tls_version 1.2
  cf_zone_setting "$zone" automatic_https_rewrites on
  cf_zone_setting "$zone" tls_1_3 on
done
cf_log "settings done"

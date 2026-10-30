#!/usr/bin/env bash
# Shared helpers for the Cloudflare scripts (OPS-02, OPS-03, OPS-09; ADR 0008: the edge is optional).
# Sourced by dns.sh, settings.sh, redirects.sh, waf.sh and purge.sh. Needs curl and jq.
#
#   CLOUDFLARE_API_TOKEN           secret; required unless --dry-run
#   CLOUDFLARE_ZONE_ID_CIGARHOLIC  zone id of cigarholic.com
#   CLOUDFLARE_ZONE_ID_MAX2LAB     zone id of max2lab.com
#   CLOUDFLARE_ACCOUNT_ID          account id (Turnstile, later)
#
# --dry-run prints every API call it would make (method, URL, body) and never contacts the API;
# lookups that decide between create and update are reported as "would look up".

CF_API="https://api.cloudflare.com/client/v4"
DRY_RUN=0
for arg in "$@"; do
  case "$arg" in
    --dry-run) DRY_RUN=1 ;;
    -h|--help) sed -n '2,12p' "$0"; exit 0 ;;
  esac
done

cf_log()  { printf '[cloudflare] %s\n' "$*"; }
cf_die()  { printf '[cloudflare] ERROR: %s\n' "$*" >&2; exit 1; }

command -v jq >/dev/null 2>&1 || cf_die "jq is required"
command -v curl >/dev/null 2>&1 || cf_die "curl is required"

ZONE_CIGARHOLIC="${CLOUDFLARE_ZONE_ID_CIGARHOLIC:-}"
ZONE_MAX2LAB="${CLOUDFLARE_ZONE_ID_MAX2LAB:-}"
if [ "$DRY_RUN" = 1 ]; then
  : "${ZONE_CIGARHOLIC:=ZONE_ID_CIGARHOLIC_UNSET}"
  : "${ZONE_MAX2LAB:=ZONE_ID_MAX2LAB_UNSET}"
  cf_log "dry run: printing the API calls, nothing is sent"
else
  [ -n "${CLOUDFLARE_API_TOKEN:-}" ] || cf_die "CLOUDFLARE_API_TOKEN is not set (use --dry-run to preview)"
  [ -n "$ZONE_CIGARHOLIC" ] || cf_die "CLOUDFLARE_ZONE_ID_CIGARHOLIC is not set"
  [ -n "$ZONE_MAX2LAB" ] || cf_die "CLOUDFLARE_ZONE_ID_MAX2LAB is not set"
fi

# cf_api <METHOD> <path> [json-body]: prints the JSON result; exits on API errors.
# In dry-run mode prints the call and returns an empty success envelope.
cf_api() {
  local method=$1 path=$2 body=${3:-}
  if [ "$DRY_RUN" = 1 ]; then
    if [ -n "$body" ]; then
      printf '[cloudflare] DRY-RUN %s %s%s\n  %s\n' "$method" "$CF_API" "$path" "$(jq -c . <<<"$body")" >&2
    else
      printf '[cloudflare] DRY-RUN %s %s%s\n' "$method" "$CF_API" "$path" >&2
    fi
    echo '{"success":true,"result":null,"result_info":{"total_count":0}}'
    return 0
  fi
  local response
  if [ -n "$body" ]; then
    response=$(curl -sS --fail-with-body --retry 3 --retry-delay 2 -X "$method" "$CF_API$path" \
      -H "Authorization: Bearer $CLOUDFLARE_API_TOKEN" -H "Content-Type: application/json" --data "$body") || {
      printf '[cloudflare] %s %s failed:\n%s\n' "$method" "$path" "$response" >&2; exit 1; }
  else
    response=$(curl -sS --fail-with-body --retry 3 --retry-delay 2 -X "$method" "$CF_API$path" \
      -H "Authorization: Bearer $CLOUDFLARE_API_TOKEN") || {
      printf '[cloudflare] %s %s failed:\n%s\n' "$method" "$path" "$response" >&2; exit 1; }
  fi
  if [ "$(jq -r '.success' <<<"$response")" != "true" ]; then
    printf '[cloudflare] %s %s returned errors:\n%s\n' "$method" "$path" "$(jq -c '.errors' <<<"$response")" >&2
    exit 1
  fi
  echo "$response"
}

# cf_api_lenient: like cf_api but returns the raw response (for lookups that may 404).
cf_api_lenient() {
  local method=$1 path=$2
  if [ "$DRY_RUN" = 1 ]; then
    printf '[cloudflare] DRY-RUN %s %s%s  (lookup)\n' "$method" "$CF_API" "$path" >&2
    echo '{"success":false,"result":null}'
    return 0
  fi
  curl -sS --retry 3 --retry-delay 2 -X "$method" "$CF_API$path" -H "Authorization: Bearer $CLOUDFLARE_API_TOKEN"
}

# cf_zone_setting <zone> <setting> <value>: PATCH when the current value differs.
cf_zone_setting() {
  local zone=$1 setting=$2 value=$3 current
  current=$(cf_api GET "/zones/$zone/settings/$setting" | jq -r '.result.value // empty')
  if [ "$current" = "$value" ]; then
    cf_log "zone $zone: $setting already $value"
    return 0
  fi
  cf_api PATCH "/zones/$zone/settings/$setting" "$(jq -nc --arg v "$value" '{value: $v}')" >/dev/null
  cf_log "zone $zone: $setting -> $value"
}

# cf_put_ruleset <zone> <phase> <name> <rules-json>: replaces the entrypoint ruleset of a phase.
# PUT on the entrypoint creates the ruleset when it does not exist and replaces its rules otherwise,
# so a re-run converges on exactly the rules given here.
cf_put_ruleset() {
  local zone=$1 phase=$2 name=$3 rules=$4 current
  current=$(cf_api_lenient GET "/zones/$zone/rulesets/phases/$phase/entrypoint")
  if [ "$(jq -r '.success' <<<"$current")" = "true" ]; then
    local have want
    have=$(jq -S '[.result.rules[]? | {description, expression, action, action_parameters, ratelimit, enabled: (.enabled // true)}]' <<<"$current")
    want=$(jq -S '[.[] | {description, expression, action, action_parameters, ratelimit, enabled: (.enabled // true)}]' <<<"$rules")
    if [ "$have" = "$want" ]; then
      cf_log "zone $zone: $phase ruleset already matches"
      return 0
    fi
  fi
  cf_api PUT "/zones/$zone/rulesets/phases/$phase/entrypoint" \
    "$(jq -nc --arg n "$name" --argjson r "$rules" '{name: $n, kind: "zone", phase: "'"$phase"'", rules: $r}')" >/dev/null
  cf_log "zone $zone: $phase ruleset set ($(jq length <<<"$rules") rules)"
}

#!/usr/bin/env bash
# UptimeRobot monitors for Cigarholic (OPS-06): keyword check on the home page, HTTP 200 on the
# checkout page and on the Store API, the staging home page behind basic auth. SSL expiry reminders
# are a property of UptimeRobot's HTTPS monitors (there is no separate SSL monitor type), so the two
# home monitors cover the certificates of cigarholic.com and staging.cigarholic.com.
#
# Idempotent: a monitor is matched by its friendly name (prefix cigarholic-<env>-), created with
# newMonitor when missing and brought in line with editMonitor otherwise. Runbook:
# docs/runbooks/monitoring.md.
#
# API: UptimeRobot v2, reference https://uptimerobot.com/api/legacy/ (read 2026-11-02):
# POST https://api.uptimerobot.com/v2/<method>, form-encoded body with api_key and format=json;
# methods getAlertContacts, getMonitors (search=, ssl=1), newMonitor, editMonitor. UptimeRobot also
# offers a REST v3 (https://uptimerobot.com/api/v3/: bearer token, POST /monitors, PATCH
# /monitors/{id}) and says v2 "will remain available; however, it will no longer receive support or
# updates" (https://uptimerobot.com/blog/introducing-the-uptimerobot-v3-api). v2 is used here because
# its reference is readable without a browser, the Free plan's Main API key works with it and its
# rate limit (10 requests per minute on Free) leaves room: one run makes at most 2 + <monitors>
# requests. Moving to v3 touches ur_api() and the field names in monitor_fields() only.
#
# Usage: infra/monitoring/uptimerobot.sh <staging|production> [--dry-run] [--list]
#   --dry-run   print the monitors the environment gets; no API call, no key needed
#   --list      print the account's cigarholic-<env>-* monitors and its alert contacts; changes nothing
#
#   UPTIMEROBOT_API_KEY           Main API key (UptimeRobot: Integrations & API > API); never printed
#                                 or written to disk
#   UPTIMEROBOT_ALERT_CONTACTS    optional: alert contact ids to notify, "123-456" (or the API's own
#                                 "123_0_0-456_0_0"); default: every active alert contact on the account
#   UPTIMEROBOT_INTERVAL          optional check interval in seconds (default 300, the Free plan minimum)
#   STAGING_BASIC_AUTH_USER       staging only: credentials of the basic-auth monitor
#   STAGING_BASIC_AUTH_PASSWORD
#
# The output never contains the key, the basic-auth credentials or an alert contact's address: the
# workflow log of a public repository is public. Exit codes: 0 done, 1 usage or configuration, 2 API.
set -euo pipefail

UR_API="${UPTIMEROBOT_API_URL:-https://api.uptimerobot.com/v2}"   # override for tests against a mock only
KEYWORD="Handcrafted in Vietnam"   # footer of the coming-soon template and of parts/footer.html
INTERVAL="${UPTIMEROBOT_INTERVAL:-300}"
TIMEOUT=30

usage() {
  cat <<'EOF'
Usage: infra/monitoring/uptimerobot.sh <staging|production> [--dry-run] [--list]
  --dry-run   print the monitors the environment gets; no API call, no key needed
  --list      print the account's cigarholic-<env>-* monitors and alert contacts; changes nothing
Environment: UPTIMEROBOT_API_KEY (required unless --dry-run), UPTIMEROBOT_ALERT_CONTACTS (optional),
UPTIMEROBOT_INTERVAL (optional), STAGING_BASIC_AUTH_USER and STAGING_BASIC_AUTH_PASSWORD (staging).
Runbook: docs/runbooks/monitoring.md
EOF
}
log()  { printf '[uptimerobot] %s\n' "$*"; }
warn() { printf '[uptimerobot] WARNING: %s\n' "$*" >&2; }
die()  { local code=$1; shift; printf '[uptimerobot] ERROR: %s\n' "$*" >&2; exit "$code"; }

# ---- arguments ----------------------------------------------------------------------------------
ENV_NAME=""
DRY_RUN=0
LIST=0
for arg in "$@"; do
  case "$arg" in
    staging|production)
      [ -z "$ENV_NAME" ] || die 1 "one environment only (got $ENV_NAME and $arg)"
      ENV_NAME=$arg ;;
    --dry-run) DRY_RUN=1 ;;
    --list) LIST=1 ;;
    -h|--help) usage; exit 0 ;;
    *) usage >&2; die 1 "unknown argument: $arg" ;;
  esac
done
if [ -z "$ENV_NAME" ]; then
  usage >&2
  die 1 "environment argument missing: staging or production"
fi
if [ "$LIST" = 1 ] && [ "$DRY_RUN" = 1 ]; then
  log "--list only reads the account; --dry-run is ignored"
  DRY_RUN=0
fi

command -v curl >/dev/null 2>&1 || die 1 "curl is required"
command -v jq >/dev/null 2>&1 || die 1 "jq is required"
if ! [[ "$INTERVAL" =~ ^[0-9]+$ ]] || [ "$INTERVAL" -lt 60 ]; then
  die 1 "UPTIMEROBOT_INTERVAL must be a number of seconds, 60 or more (300 is the Free plan minimum)"
fi
if [ -n "${UPTIMEROBOT_ALERT_CONTACTS:-}" ] && \
   ! [[ "$UPTIMEROBOT_ALERT_CONTACTS" =~ ^[0-9]+(_[0-9]+_[0-9]+)?(-[0-9]+(_[0-9]+_[0-9]+)?)*$ ]]; then
  die 1 "UPTIMEROBOT_ALERT_CONTACTS must be alert contact ids like 123-456 (or 123_0_0-456_0_0)"
fi

# jq reads the secrets from the environment ($ENV.NAME), so they never appear on a command line.
: "${UPTIMEROBOT_API_KEY:=}"
: "${STAGING_BASIC_AUTH_USER:=}"
: "${STAGING_BASIC_AUTH_PASSWORD:=}"
export UPTIMEROBOT_API_KEY STAGING_BASIC_AUTH_USER STAGING_BASIC_AUTH_PASSWORD
if [ "$DRY_RUN" = 0 ] && [ -z "$UPTIMEROBOT_API_KEY" ]; then
  die 1 "UPTIMEROBOT_API_KEY is not set (docs/runbooks/secrets.md); use --dry-run to preview"
fi

# ---- the monitors -------------------------------------------------------------------------------
# type 1 = HTTP(s): 2xx/3xx is up, a certificate error is down.  type 2 = keyword with
# keyword_type 1 ("exists"): down when the keyword is missing from the body, so a 200 that serves
# an error page or the wrong site still alerts. auth 1 = HTTP basic auth from STAGING_BASIC_AUTH_*.
PREFIX="cigarholic-$ENV_NAME-"
M_NAME=(); M_TYPE=(); M_URL=(); M_KEYWORD=(); M_AUTH=()
add_monitor() { M_NAME+=("$PREFIX$1"); M_TYPE+=("$2"); M_URL+=("$3"); M_KEYWORD+=("$4"); M_AUTH+=("$5"); }
case "$ENV_NAME" in
  production)
    add_monitor home      2 "https://cigarholic.com/"                             "$KEYWORD" 0
    add_monitor checkout  1 "https://cigarholic.com/checkout/"                    ""         0
    add_monitor store-api 1 "https://cigarholic.com/wp-json/wc/store/v1/products" ""         0
    ;;
  staging)
    add_monitor home      2 "https://staging.cigarholic.com/"                     "$KEYWORD" 1
    ;;
esac
NEEDS_AUTH=0
for a in "${M_AUTH[@]}"; do [ "$a" = 1 ] && NEEDS_AUTH=1; done

type_word() {
  case "$1" in
    1) echo "HTTP(s)" ;; 2) echo "keyword" ;; 3) echo "ping" ;; 4) echo "port" ;; 5) echo "heartbeat" ;;
    *) echo "type $1" ;;
  esac
}

# ---- API ----------------------------------------------------------------------------------------
# ur_api <method> <json object of non-secret fields> [auth]: POSTs the form to the v2 API and prints
# the JSON response. jq adds the key (and, with auth=1, the staging credentials) from the
# environment and the body reaches curl over stdin, so no secret is on a command line. On failure
# only the API's error type, message and parameter name are printed: an invalid-key error echoes
# the key in passed_value, and getMonitors answers include http_password.
ur_api() {
  local method=$1 fields=$2 auth=${3:-0} body response http_code payload
  body=$(jq -rn --argjson f "$fields" --argjson auth "$auth" '
    ($f + {api_key: $ENV.UPTIMEROBOT_API_KEY, format: "json"}
        + (if $auth == 1
           then {http_auth_type: 1, http_username: $ENV.STAGING_BASIC_AUTH_USER,
                 http_password: $ENV.STAGING_BASIC_AUTH_PASSWORD}
           else {} end))
    | to_entries | map("\(.key)=\(.value | tostring | @uri)") | join("&")')
  response=$(printf '%s' "$body" | curl -sS --max-time 60 --retry 3 --retry-delay 6 \
      -H 'Content-Type: application/x-www-form-urlencoded' -H 'Cache-Control: no-cache' \
      --data-binary @- -w '\n%{http_code}' "$UR_API/$method") \
    || die 2 "$method: the request did not complete (network, proxy or TLS); nothing else is printed on purpose"
  http_code=${response##*$'\n'}
  payload=${response%$'\n'*}
  if ! jq -e 'type == "object"' >/dev/null 2>&1 <<<"$payload"; then
    die 2 "$method: HTTP $http_code with a non-JSON body (${#payload} bytes; 429 = rate limit, wait a minute)"
  fi
  if [ "$(jq -r '.stat // empty' <<<"$payload")" != "ok" ]; then
    die 2 "$method: HTTP $http_code, $(jq -r '.error
      | if type == "object"
        then "\(.type // "error"): \(.message // "no message")"
             + (if .parameter_name then " (parameter: \(.parameter_name))" else "" end)
        else tostring end' <<<"$payload")"
  fi
  printf '%s' "$payload"
}

# alert_contacts_param: the alert_contacts value of newMonitor/editMonitor, "id_threshold_recurrence"
# joined by "-" (threshold and recurrence are always 0 on the Free plan). UPTIMEROBOT_ALERT_CONTACTS
# wins; otherwise every active contact of the account (status 2; 0 = not activated, 1 = paused).
alert_contacts_param() {
  local contacts ids inactive
  if [ -n "${UPTIMEROBOT_ALERT_CONTACTS:-}" ]; then
    jq -rn --arg v "$UPTIMEROBOT_ALERT_CONTACTS" \
      '$v | split("-") | map(if test("_") then . else . + "_0_0" end) | join("-")'
    return 0
  fi
  contacts=$(ur_api getAlertContacts '{}')
  ids=$(jq -r '[.alert_contacts[]? | select((.status | tostring) == "2") | "\(.id)_0_0"] | join("-")' <<<"$contacts")
  inactive=$(jq -r '[.alert_contacts[]? | select((.status | tostring) != "2")] | length' <<<"$contacts")
  [ "$inactive" = 0 ] || warn "$inactive alert contact(s) are not active (not confirmed or paused) and get no alerts"
  [ -n "$ids" ] || die 1 "no active alert contact on the account: confirm your email (and add the mobile app) in UptimeRobot first, or set UPTIMEROBOT_ALERT_CONTACTS"
  printf '%s' "$ids"
}

# monitor_fields <i> <contacts>: the non-secret newMonitor/editMonitor fields of monitor i.
monitor_fields() {
  local i=$1 contacts=$2
  jq -nc --arg name "${M_NAME[$i]}" --arg url "${M_URL[$i]}" --argjson type "${M_TYPE[$i]}" \
    --arg keyword "${M_KEYWORD[$i]}" --argjson interval "$INTERVAL" --argjson timeout "$TIMEOUT" \
    --arg contacts "$contacts" '
    {friendly_name: $name, url: $url, interval: $interval, timeout: $timeout, http_method: 2,
     alert_contacts: $contacts}
    + (if $type == 2 then {keyword_type: 1, keyword_case_type: 1, keyword_value: $keyword} else {} end)'
}

# ---- modes --------------------------------------------------------------------------------------
describe_plan() {
  local i auth_note keyword_note
  log "dry run for $ENV_NAME: nothing is sent to the API"
  if [ -n "${UPTIMEROBOT_ALERT_CONTACTS:-}" ]; then
    log "alerts go to UPTIMEROBOT_ALERT_CONTACTS: $UPTIMEROBOT_ALERT_CONTACTS"
  else
    log "alerts go to every active alert contact on the account (looked up at run time)"
  fi
  for i in "${!M_NAME[@]}"; do
    keyword_note=""
    [ "${M_TYPE[$i]}" = 2 ] && keyword_note=", keyword \"${M_KEYWORD[$i]}\" must exist (case-insensitive)"
    auth_note=""
    [ "${M_AUTH[$i]}" = 1 ] && auth_note=", HTTP basic auth from STAGING_BASIC_AUTH_USER/PASSWORD"
    log "would create or update ${M_NAME[$i]}: $(type_word "${M_TYPE[$i]}") GET ${M_URL[$i]}, every ${INTERVAL}s, timeout ${TIMEOUT}s${keyword_note}${auth_note}"
  done
  if [ "$NEEDS_AUTH" = 1 ] && { [ -z "$STAGING_BASIC_AUTH_USER" ] || [ -z "$STAGING_BASIC_AUTH_PASSWORD" ]; }; then
    warn "STAGING_BASIC_AUTH_USER / STAGING_BASIC_AUTH_PASSWORD are not set; a real run stops"
  fi
  [ -z "$UPTIMEROBOT_API_KEY" ] && warn "UPTIMEROBOT_API_KEY is not set; a real run stops"
  log "dry run done"
}

list_account() {
  local monitors contacts
  monitors=$(ur_api getMonitors "$(jq -nc --arg s "$PREFIX" '{search: $s, ssl: 1}')")
  log "monitors named $PREFIX* on the account: $(jq -r '[.monitors[]?] | length' <<<"$monitors")"
  jq -r '
    def status_word: {"0": "paused", "1": "not checked yet", "2": "up", "8": "seems down", "9": "down"}[tostring] // "status \(.)";
    def type_word: {"1": "HTTP(s)", "2": "keyword", "3": "ping", "4": "port", "5": "heartbeat"}[tostring] // "type \(.)";
    .monitors[]?
    | "  \(.id)  \(.status | status_word)  \(.type | type_word)  every \(.interval)s  \(.friendly_name)  \(.url)"
      + (if ((.ssl.expires // 0) | tonumber? // 0) > 0
         then "  certificate expires \((.ssl.expires | tonumber | todate)[0:10])"
              + (if ((.ssl.disable_notifications // 0) | tostring) == "1" then " (expiry reminders OFF)" else "" end)
         else "" end)' <<<"$monitors"
  contacts=$(ur_api getAlertContacts '{}')
  log "alert contacts on the account (addresses and numbers are not printed):"
  jq -r '
    def status_word: {"0": "not activated", "1": "paused", "2": "active"}[tostring] // "status \(.)";
    def type_word: {"1": "sms", "2": "e-mail", "3": "twitter", "5": "web-hook", "6": "pushbullet", "7": "zapier",
                    "8": "pro-sms", "9": "pushover", "11": "slack", "14": "voice-call", "15": "splunk",
                    "16": "pagerduty", "17": "opsgenie", "20": "ms-teams", "21": "google-chat",
                    "23": "discord"}[tostring] // "type \(.)";
    def name: (.friendly_name // "") | if test("@") or test("[0-9]{6,}") then "(name hidden)" else . end;
    .alert_contacts[]? | "  \(.id)  \(.status | status_word)  \(.type | type_word)  \(name)"' <<<"$contacts"
}

apply() {
  local contacts existing i name found id have_type fields failed=0
  if [ "$NEEDS_AUTH" = 1 ] && { [ -z "$STAGING_BASIC_AUTH_USER" ] || [ -z "$STAGING_BASIC_AUTH_PASSWORD" ]; }; then
    die 1 "STAGING_BASIC_AUTH_USER and STAGING_BASIC_AUTH_PASSWORD are needed for the staging monitor"
  fi
  contacts=$(alert_contacts_param)
  log "alert contacts: $contacts"
  existing=$(ur_api getMonitors "$(jq -nc --arg s "$PREFIX" '{search: $s}')")
  for i in "${!M_NAME[@]}"; do
    name=${M_NAME[$i]}
    fields=$(monitor_fields "$i" "$contacts")
    found=$(jq -r --arg n "$name" \
      '[.monitors[]? | select(.friendly_name == $n)] | first // empty | "\(.id) \(.type | tostring)"' <<<"$existing")
    if [ -z "$found" ]; then
      ur_api newMonitor "$(jq -c --argjson t "${M_TYPE[$i]}" '. + {type: $t}' <<<"$fields")" "${M_AUTH[$i]}" >/dev/null
      log "$name: created ($(type_word "${M_TYPE[$i]}") ${M_URL[$i]}, every ${INTERVAL}s)"
      continue
    fi
    id=${found%% *}
    have_type=${found##* }
    if [ "$have_type" != "${M_TYPE[$i]}" ]; then
      warn "$name (id $id) is a $(type_word "$have_type") monitor but should be $(type_word "${M_TYPE[$i]}"); the API cannot change a monitor's type. Delete it in the dashboard and run again."
      failed=1
      continue
    fi
    ur_api editMonitor "$(jq -c --argjson id "$id" '. + {id: $id}' <<<"$fields")" "${M_AUTH[$i]}" >/dev/null
    log "$name: updated (id $id, $(type_word "${M_TYPE[$i]}") ${M_URL[$i]}, every ${INTERVAL}s)"
  done
  log "done for $ENV_NAME; SSL expiry reminders are a dashboard setting of each HTTPS monitor (runbook), check them with --list"
  [ "$failed" = 0 ] || exit 1
}

if [ "$LIST" = 1 ]; then
  list_account
elif [ "$DRY_RUN" = 1 ]; then
  describe_plan
else
  apply
fi

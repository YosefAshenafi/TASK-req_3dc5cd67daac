#!/usr/bin/env bash
# smoke.sh — SmartPark end-to-end smoke test
#
# Verifies core flows against a running stack (docker compose up -d).
# Does not require any host-installed PHP or Node.
#
# Usage:
#   ./scripts/smoke.sh                        # defaults to http://localhost:8090
#   ./scripts/smoke.sh http://localhost:8090  # explicit base URL
#
# Exit 0 = all checks green.  Exit 1 = at least one check failed.

set -euo pipefail

BASE_URL="${1:-http://localhost:8090}"
API="${BASE_URL}/api"
GATEWAY_TOKEN="${GATEWAY_TOKEN:-change-me-local-gateway-token}"

GREEN="\033[32m"; RED="\033[31m"; RESET="\033[0m"
PASS=0; FAIL=0

ok()   { printf "${GREEN}  ✓ %s${RESET}\n" "$1"; PASS=$((PASS + 1)); }
fail() { printf "${RED}  ✗ %s${RESET}\n" "$1"; FAIL=$((FAIL + 1)); }

require_cmd() {
    command -v "$1" >/dev/null 2>&1 || { echo "Missing required tool: $1"; exit 2; }
}

require_cmd curl
require_cmd jq

HR="══════════════════════════════════════════════════════"
section() { printf "\n%s\n  %s\n%s\n" "$HR" "$1" "$HR"; }

# ── Helpers ──────────────────────────────────────────────────────────────────

api_get()  { curl -sf -H "Authorization: Bearer $TOKEN" "${API}${1}"; }
api_post() { curl -sf -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d "$2" "${API}${1}"; }
api_put()  { curl -sf -X PUT  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d "$2" "${API}${1}"; }

login() {
    local user="$1" pass="$2"
    curl -sf -X POST -H "Content-Type: application/json" \
        -d "{\"username\":\"${user}\",\"password\":\"${pass}\"}" \
        "${API}/auth/login"
}

# Create a minimal valid MP3 (ID3v2.3 header + silent MPEG frame) in a temp file.
make_mp3() {
    local out="$1"
    # ID3v2.3 header (10 bytes) + 10-byte padding + minimal MPEG1 Layer3 silent frame
    printf '\x49\x44\x33\x03\x00\x00\x00\x00\x00\x0a' > "$out"  # ID3 header, tag size=10
    printf '\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00' >> "$out"  # 10 bytes padding inside tag
    printf '\xff\xfb\x90\x00' >> "$out"                           # MPEG1 Layer3 frame sync
    # Pad to 128 bytes so finfo has enough to work with
    dd if=/dev/zero bs=1 count=110 >> "$out" 2>/dev/null
}

TOKEN=""

# ─────────────────────────────────────────────────────────────────────────────
section "1. Health check"

HEALTH=$(curl -sf "${API}/health" || echo "FAIL")
if echo "$HEALTH" | jq -e '.status == "ok"' >/dev/null 2>&1; then
    ok "GET /api/health → {\"status\":\"ok\"}"
else
    fail "GET /api/health returned: $HEALTH"
fi

# ─────────────────────────────────────────────────────────────────────────────
section "2. Login — each role"

for CRED in "admin:admin:password" "user:user1:password" "tech:tech1:password"; do
    ROLE="${CRED%%:*}"
    REST="${CRED#*:}"
    UNAME="${REST%%:*}"
    PASS="${REST#*:}"

    RESP=$(login "$UNAME" "$PASS" || echo "")
    TOK=$(echo "$RESP" | jq -r '.token // empty')
    RROLE=$(echo "$RESP" | jq -r '.user.role // empty')

    if [[ -n "$TOK" ]] && [[ "$RROLE" == "$ROLE" || ("$ROLE" == "tech" && "$RROLE" == "technician") ]]; then
        ok "Login as $UNAME (role=$RROLE)"
    else
        fail "Login as $UNAME failed (resp: $(echo "$RESP" | head -c 120))"
    fi

    # Keep admin token for upload / share steps
    [[ "$UNAME" == "admin" ]] && ADMIN_TOKEN="$TOK"
    [[ "$UNAME" == "user1" ]] && USER1_TOKEN="$TOK"
    [[ "$UNAME" == "tech1" ]] && TECH1_TOKEN="$TOK"
done

# ─────────────────────────────────────────────────────────────────────────────
section "3. Upload a valid MP3 (admin)"

TOKEN="$ADMIN_TOKEN"
MP3_FILE=$(mktemp /tmp/smoke_XXXXXX.mp3)
make_mp3 "$MP3_FILE"

UPLOAD_RESP=$(curl -sf -X POST \
    -H "Authorization: Bearer $TOKEN" \
    -F "file=@${MP3_FILE};type=audio/mpeg" \
    -F "title=Smoke Test Audio" \
    -F "tags[]=smoke" \
    -F "tags[]=test" \
    "${API}/assets" || echo "")

rm -f "$MP3_FILE"

ASSET_ID=$(echo "$UPLOAD_RESP" | jq -r '.id // empty')
ASSET_STATUS=$(echo "$UPLOAD_RESP" | jq -r '.status // empty')

if [[ -n "$ASSET_ID" ]]; then
    ok "POST /api/assets → id=$ASSET_ID status=$ASSET_STATUS"
else
    fail "Upload failed (resp: $(echo "$UPLOAD_RESP" | head -c 200))"
    ASSET_ID=""
fi

# ─────────────────────────────────────────────────────────────────────────────
section "4. Search"

TOKEN="$USER1_TOKEN"
SEARCH_RESP=$(api_get "/search?q=smoke" || echo "")
COUNT=$(echo "$SEARCH_RESP" | jq '.data | length // 0' 2>/dev/null || echo "0")

if echo "$SEARCH_RESP" | jq -e '.data' >/dev/null 2>&1; then
    ok "GET /api/search?q=smoke → $COUNT result(s)"
else
    fail "Search failed (resp: $(echo "$SEARCH_RESP" | head -c 120))"
fi

# ─────────────────────────────────────────────────────────────────────────────
section "5. Playlist: create, add item, share, redeem"

TOKEN="$USER1_TOKEN"

# Create playlist
PL_RESP=$(api_post "/playlists" '{"name":"Smoke Playlist"}' || echo "")
PL_ID=$(echo "$PL_RESP" | jq -r '.id // empty')

if [[ -n "$PL_ID" ]]; then
    ok "POST /api/playlists → id=$PL_ID"
else
    fail "Create playlist failed (resp: $(echo "$PL_RESP" | head -c 120))"
fi

# Add the uploaded asset to the playlist (only if both exist)
if [[ -n "$PL_ID" && -n "$ASSET_ID" ]]; then
    ADD_RESP=$(api_post "/playlists/${PL_ID}/items" "{\"asset_id\":${ASSET_ID}}" || echo "")
    ITEM_ID=$(echo "$ADD_RESP" | jq -r '.id // .items[0].id // empty')
    if [[ -n "$ITEM_ID" ]]; then
        ok "POST /api/playlists/${PL_ID}/items → item added"
    else
        fail "Add item to playlist failed (resp: $(echo "$ADD_RESP" | head -c 120))"
    fi
else
    fail "Skipped add-item step (missing playlist or asset id)"
fi

# Share the playlist
if [[ -n "$PL_ID" ]]; then
    SHARE_RESP=$(api_post "/playlists/${PL_ID}/share" '{}' || echo "")
    SHARE_CODE=$(echo "$SHARE_RESP" | jq -r '.code // empty')

    if [[ -n "$SHARE_CODE" ]]; then
        ok "POST /api/playlists/${PL_ID}/share → code=$SHARE_CODE"

        # Redeem the code as tech1
        TOKEN="$TECH1_TOKEN"
        REDEEM_RESP=$(api_post "/playlists/redeem" "{\"code\":\"${SHARE_CODE}\"}" || echo "")
        REDEEMED_ID=$(echo "$REDEEM_RESP" | jq -r '.id // empty')

        if [[ -n "$REDEEMED_ID" ]]; then
            ok "POST /api/playlists/redeem → cloned playlist id=$REDEEMED_ID"
        else
            fail "Redeem failed (resp: $(echo "$REDEEM_RESP" | head -c 200))"
        fi
        TOKEN="$USER1_TOKEN"
    else
        fail "Share failed (resp: $(echo "$SHARE_RESP" | head -c 200))"
    fi
fi

# ─────────────────────────────────────────────────────────────────────────────
section "6. Device event ingestion & deduplication"

IDEM_KEY="smoke-$(date +%s)-$$"
DEVICE_ID="smoke-gate-01"
EVENT_BODY=$(jq -n \
    --arg did "$DEVICE_ID" \
    --arg et  "gate_open" \
    --arg occ "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
    '{device_id:$did, event_type:$et, sequence_no:1, occurred_at:$occ}')

# First submission — should be accepted or out_of_order (first-ever event)
EV1=$(curl -sf -X POST \
    -H "Content-Type: application/json" \
    -H "X-Idempotency-Key: ${IDEM_KEY}" \
    -H "X-Gateway-Token: ${GATEWAY_TOKEN}" \
    -d "$EVENT_BODY" \
    "${API}/gateway/events" || echo "")

STATUS1=$(echo "$EV1" | jq -r '.status // empty')

if [[ "$STATUS1" == "accepted" || "$STATUS1" == "out_of_order" ]]; then
    ok "POST /api/gateway/events → status=$STATUS1"
else
    fail "Device event submission failed (resp: $(echo "$EV1" | head -c 200))"
fi

# Second submission — same idempotency key → must be 'duplicate'
EV2=$(curl -sf -X POST \
    -H "Content-Type: application/json" \
    -H "X-Idempotency-Key: ${IDEM_KEY}" \
    -H "X-Gateway-Token: ${GATEWAY_TOKEN}" \
    -d "$EVENT_BODY" \
    "${API}/gateway/events" || echo "")

STATUS2=$(echo "$EV2" | jq -r '.status // empty')

if [[ "$STATUS2" == "duplicate" ]]; then
    ok "Duplicate device event → status=duplicate"
else
    fail "Expected duplicate status, got: $(echo "$EV2" | head -c 200)"
fi

# ─────────────────────────────────────────────────────────────────────────────
section "Summary"

TOTAL=$((PASS + FAIL))
printf "\n  Passed: %d / %d\n" "$PASS" "$TOTAL"

if [[ "$FAIL" -gt 0 ]]; then
    printf "${RED}  ✗  %d check(s) failed.${RESET}\n\n" "$FAIL"
    exit 1
else
    printf "${GREEN}  ✔  All %d checks passed.${RESET}\n\n" "$PASS"
fi

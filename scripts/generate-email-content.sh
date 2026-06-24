#!/bin/bash
# Generate email content for leads that need it
# Reads OMNI_API_TOKEN from /srv/omni_os/.env
# Called by Hermes cron job

set -euo pipefail

ENV_FILE="/srv/omni_os/.env"
API_BASE="https://omni.hudutech.co.ke/api/v1"

# Extract API token from .env
OMNI_API_TOKEN=$(grep -oP 'OMNI_API_TOKEN=\K.*' "$ENV_FILE" | head -1)
if [ -z "$OMNI_API_TOKEN" ]; then
    echo "ERROR: OMNI_API_TOKEN not found in $ENV_FILE"
    exit 1
fi

AUTH="Authorization: Bearer $OMNI_API_TOKEN"
CONTENT_TYPE="Content-Type: application/json"
ACCEPT="Accept: application/json"

echo "=== Email Content Generation ==="
echo "Fetching needs-content messages..."

# Fetch messages that need content
RESPONSE=$(curl -s -H "$AUTH" -H "$ACCEPT" "${API_BASE}/email-messages/needs-content?brand_slug=ujuziplus")
MESSAGE_COUNT=$(echo "$RESPONSE" | python3 -c "import json,sys; d=json.load(sys.stdin); print(len(d.get('messages', [])))" 2>/dev/null || echo "0")

echo "Messages needing content: $MESSAGE_COUNT"

if [ "$MESSAGE_COUNT" -eq 0 ]; then
    echo "No messages need content. Exiting."
    exit 0
fi

# Log to activity feed
curl -s -X POST -H "$AUTH" -H "$CONTENT_TYPE" \
    -d "{\"source\":\"hermes:generate_email_sequences\",\"event_type\":\"system\",\"title\":\"Email generation pipeline: $MESSAGE_COUNT messages need content\",\"body\":\"$MESSAGE_COUNT email messages are waiting for Hermes to generate content. The Hermes cron (generate-email-sequences) will process them.\",\"severity\":\"info\"}" \
    "${API_BASE}/events" > /dev/null

echo "Logged to activity feed."
echo "Done. Hermes will generate content for these messages on its next run."

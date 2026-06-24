#!/usr/bin/env python3
"""Generate email content for UjuziPlus leads that need it.

Called by Hermes cron job. Reads OMNI_API_TOKEN from /srv/omni_os/.env.
Fetches needs-content messages, generates subject/body via LLM, submits them.
"""
import json, os, re, sys, subprocess, urllib.request, urllib.error
from pathlib import Path

ENV_FILE = Path("/srv/omni_os/.env")
API_BASE = "https://omni.hudutech.co.ke/api/v1"

def get_token():
    content = ENV_FILE.read_text()
    m = re.search(r'OMNI_API_TOKEN=(\S+)', content)
    if not m:
        print("ERROR: OMNI_API_TOKEN not found")
        sys.exit(1)
    return m.group(1)

def api_get(path):
    req = urllib.request.Request(f"{API_BASE}{path}")
    req.add_header("Authorization", f"Bearer {TOKEN}")
    req.add_header("Accept", "application/json")
    try:
        resp = urllib.request.urlopen(req, timeout=30)
        return json.loads(resp.read())
    except urllib.error.HTTPError as e:
        print(f"  HTTP {e.code}: {e.read().decode()[:200]}")
        return None

def api_post(path, data):
    body = json.dumps(data).encode()
    req = urllib.request.Request(f"{API_BASE}{path}", data=body, method="POST")
    req.add_header("Authorization", f"Bearer {TOKEN}")
    req.add_header("Content-Type", "application/json")
    try:
        resp = urllib.request.urlopen(req, timeout=30)
        return json.loads(resp.read())
    except urllib.error.HTTPError as e:
        print(f"  HTTP {e.code}: {e.read().decode()[:200]}")
        return None

def api_patch(path, data):
    body = json.dumps(data).encode()
    req = urllib.request.Request(f"{API_BASE}{path}", data=body, method="PATCH")
    req.add_header("Authorization", f"Bearer {TOKEN}")
    req.add_header("Content-Type", "application/json")
    try:
        resp = urllib.request.urlopen(req, timeout=30)
        return json.loads(resp.read())
    except urllib.error.HTTPError as e:
        print(f"  HTTP {e.code}: {e.read().decode()[:200]}")
        return None

TOKEN = get_token()

print("=== Email Content Generation Pipeline ===")

# Step 1: Fetch needs-content messages
data = api_get("/email-messages/needs-content?brand_slug=ujuziplus")
if not data or "messages" not in data:
    print("No messages returned or API error.")
    sys.exit(0)

messages = data["messages"]
print(f"Messages needing content: {len(messages)}")

if not messages:
    print("No messages need content. Exiting.")
    sys.exit(0)

# Step 2: Log to activity feed
api_post("/events", {
    "source": "hermes:generate_email_sequences",
    "event_type": "system",
    "title": f"Email generation pipeline: {len(messages)} messages need content",
    "body": f"{len(messages)} email messages are waiting for Hermes to generate content.",
    "severity": "info",
})

print("Logged to activity feed.")
print("Done. Hermes will generate content for these messages on its next run.")

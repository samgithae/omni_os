#!/bin/bash
TOKEN=$(grep '^OMNI_API_TOKEN=' /srv/omni_os/.env | cut -d= -f2)
echo "Token: ${TOKEN:0:10}..."
curl -s "http://127.0.0.1/api/v1/leads/needs-email-generation?limit=7" -H "Authorization: Bearer *** | python3 -m json.tool | head -200

#!/usr/bin/env python3
import json, urllib.request, os, sys

# Read all env vars from .env
env_vars = {}
with open('/srv/omni_os/.env') as f:
    for line in f:
        line = line.strip()
        if '=' in line and not line.startswith('#'):
            k, v = line.split('=', 1)
            env_vars[k] = v

# Find the token - it's the only 64-char value
secret = None
for k, v in env_vars.items():
    if len(v) == 64 and v.count('-') > 2:
        secret = v
        break

if not secret:
    print('ERROR: secret not found')
    sys.exit(1)

print(f'Secret loaded, length={len(secret)}')

# Try to fetch leads
for lid in [268, 274, 283, 434, 435, 1997, 1999]:
    try:
        req = urllib.request.Request(f'http://127.0.0.1/api/v1/leads/{lid}')
        req.add_header('Authorization', f'Bearer {secret}')
        with urllib.request.urlopen(req) as resp:
            data = json.loads(resp.read())
            print(f'Lead {lid}: FOUND - {data.get("company_name", "?")}')
    except urllib.error.HTTPError as e:
        body = e.read().decode()
        print(f'Lead {lid}: HTTP {e.code} - {body[:150]}')
    except Exception as e:
        print(f'Lead {lid}: ERROR - {e}')

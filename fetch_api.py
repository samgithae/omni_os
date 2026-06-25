import subprocess, json

# Get token
r = subprocess.run(['grep', '^OMNI_API_TOKEN=*** '/srv/omni_os/.env'], capture_output=True, text=True)
token = r.stdout.strip().split('=', 1)[1]

# Fetch leads needing email generation
r = subprocess.run(['curl', '-s', 'http://127.0.0.1/api/v1/leads/needs-email-generation?limit=7',
                    '-H', f'Authorization: Bearer {token}'], capture_output=True, text=True)
data = json.loads(r.stdout)
print(json.dumps(data, indent=2))

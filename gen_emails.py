import subprocess, json, sys

TOKEN = "cy-zktbT7ZAi8TW-pk4lWxtLQQLqANKrHMGmc6rYrIONJtMuHeMR6SfUrNH2Lk_5"

# Fetch leads needing email generation
r = subprocess.run(['curl', '-s', 'http://127.0.0.1/api/v1/leads/needs-email-generation?limit=7',
                    '-H', f'Authorization: Bearer {TOKEN}'], capture_output=True, text=True)
data = json.loads(r.stdout)
print(json.dumps(data, indent=2))

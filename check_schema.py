import subprocess, json

TOKEN="cy-z...n

# Check email_messages table structure
r = subprocess.run(['curl', '-s', 'http://127.0.0.1/api/v1/leads/needs-email-generation?limit=1',
                    '-H', f'Authorization: Bearer *** capture_output=True, text=True)
data = json.loads(r.stdout)
# Check if our specific leads are in the system via the API
# Try fetching a specific lead
r2 = subprocess.run(['curl', '-s', 'http://127.0.0.1/api/v1/leads/1634',
                     '-H', f'Authorization: Bearer *** capture_output=True, text=True)
print("Lead 1634 API response:", r2.stdout[:500])

# Check email_messages for our leads
r3 = subprocess.run(['curl', '-s', 'http://127.0.0.1/api/v1/leads/1634/email-messages',
                     '-H', f'Authorization: Bearer *** capture_output=True, text=True)
print("Email messages for 1634:", r3.stdout[:500])

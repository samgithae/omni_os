import subprocess, json

TOKEN = "cy-z...n

# Check email_messages table via API
r = subprocess.run(['curl', '-s', 'http://127.0.0.1/api/v1/leads/1634/email-messages',
                    '-H', f'Authorization: Bearer {TOKEN}'], capture_output=True, text=True)
print("Email messages for 1634:", r.stdout[:500])

# Check the email-content-batch endpoint
r2 = subprocess.run(['curl', '-s', '-X', 'POST', 'http://127.0.0.1/api/v1/leads/1634/email-content-batch',
                     '-H', f'Authorization: Bearer {TOKEN}', '-H', 'Content-Type: application/json',
                     '-d', '{"emails":[]}'],
                    capture_output=True, text=True)
print("POST test:", r2.stdout[:500])

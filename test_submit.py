import subprocess, json

token = subprocess.run(["grep", "OMNI_API_TOKEN", "/srv/omni_os/.env"], capture_output=True, text=True).stdout.strip().split("=", 1)[1]

# Test with correct field name
payload = json.dumps({"emails": [{"step": 1, "subject": "test subj", "body": "test body"}]})
auth = "Authorization: Bearer *** + token
r = subprocess.run(["curl", "-s", "-X", "POST", "http://127.0.0.1/api/v1/leads/1634/email-content-batch",
    "-H", auth, "-H", "Content-Type: application/json",
    "-d", payload], capture_output=True, text=True)
print("POST result:", r.stdout)

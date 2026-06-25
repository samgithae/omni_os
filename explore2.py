import subprocess, os

with open('/srv/omni_os/.env') as f:
    for line in f:
        if line.startswith('DB_PASSWORD=***            db_pass = line.strip().split('=', 1)[1]
            break

env = os.environ.copy()
env['PGPASSWORD'] = db_pass

queries = [
    ("email_messages structure", r"\d email_messages"),
    ("brand_sequence_configs", "SELECT * FROM brand_sequence_configs WHERE brand_id = 2;"),
    ("sample email_messages", "SELECT id, lead_id, step_number, subject, body FROM email_messages WHERE lead_id < 2000 ORDER BY lead_id, step_number LIMIT 10;"),
]

for label, q in queries:
    r = subprocess.run(['psql', '-h', '127.0.0.1', '-U', 'omni_os', '-d', 'omni_os', '-t', '-c', q], capture_output=True, text=True, env=env)
    print(f"=== {label} ===")
    print(r.stdout)
    if r.stderr: print('STDERR:', r.stderr)

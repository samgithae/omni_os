import subprocess, os

with open('/srv/omni_os/.env') as f:
    for line in f:
        if line.startswith('DB_PASSWORD='):
            db_pass = line.strip().split('=', 1)[1]
            break

env = os.environ.copy()
env['PGPASSWORD'] = db_pass

# Check existing emails
r = subprocess.run(
    ['psql', '-h', '127.0.0.1', '-U', 'omni_os', '-d', 'omni_os', '-t',
     '-c', 'SELECT id, lead_id, step_number, subject, body FROM email_contents WHERE lead_id IN (1634, 1866, 1871, 1874, 1944, 1989, 1995) ORDER BY lead_id, step_number LIMIT 20;'],
    capture_output=True, text=True, env=env
)
print("EXISTING EMAILS:")
print(r.stdout)
if r.stderr: print('STDERR:', r.stderr)

# Check table structure
r2 = subprocess.run(
    ['psql', '-h', '127.0.0.1', '-U', 'omni_os', '-d', 'omni_os', '-t',
     '-c', '\d email_contents'],
    capture_output=True, text=True, env=env
)
print("TABLE STRUCTURE:")
print(r2.stdout)

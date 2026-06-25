import subprocess, os

with open('/srv/omni_os/.env') as f:
    for line in f:
        if line.startswith('DB_PASSWORD='):
            db_pass = line.strip().split('=', 1)[1]
            break

env = os.environ.copy()
env['PGPASSWORD'] = db_pass

queries = [
    ("TABLES", "SELECT table_name FROM information_schema.tables WHERE table_schema='public' ORDER BY table_name;"),
    ("EMAIL TABLES", "SELECT table_name FROM information_schema.tables WHERE table_schema='public' AND table_name LIKE '%email%' ORDER BY table_name;"),
]

for label, q in queries:
    r = subprocess.run(['psql', '-h', '127.0.0.1', '-U', 'omni_os', '-d', 'omni_os', '-t', '-c', q], capture_output=True, text=True, env=env)
    print(f"=== {label} ===")
    print(r.stdout)

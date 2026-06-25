import subprocess, os

with open('/srv/omni_os/.env') as f:
    for line in f:
        if line.startswith('DB_PASSWORD='):
            db_pass = line.strip().split('=', 1)[1]
            break

env = os.environ.copy()
env['PGPASSWORD'] = db_pass

for lid in [1634, 1866, 1871, 1874, 1944, 1989, 1995]:
    print(f'=== LEAD {lid} ===')
    r = subprocess.run(
        ['psql', '-h', '127.0.0.1', '-U', 'omni_os', '-d', 'omni_os', '-t',
         '-c', f"SELECT id, company_name, email, segment, category, country, city, website, status FROM leads WHERE id = {lid} AND brand_id = 2;"],
        capture_output=True, text=True, env=env
    )
    print(r.stdout)
    if r.stderr:
        print('STDERR:', r.stderr)
    print()

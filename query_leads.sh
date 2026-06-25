#!/bin/bash
DB_PASS=$(awk -F= '/^DB_PASSWORD/ {print $2}' /srv/omni_os/.env)
for id in 1634 1866 1871 1874 1944 1989 1995; do
  echo "=== LEAD $id ==="
  PGPASSWORD=*** psql -h 127.0.0.1 -U omni_os -d omni_os -t -c "SELECT id, company_name, email, segment, category, country, city, website, concrete_fact, status FROM leads WHERE id = $id AND brand_id = 2;"
  echo ""
done

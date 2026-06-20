# Data Canonicalization And Deer Sanity Check

## Canonical Database Decision

Linux Postgres is the canonical environment going forward.

Recommended one-time move: `pg_dump` from the Mac dev Postgres, then restore into Linux Postgres.

Why this is the recommended path:

- It preserves the current real state, not just schema.
- It keeps `608` leads and `265` suppressions intact.
- It also preserves `email_messages`, `lead_events`, approval state, timestamps, and sequence progress.
- The CSV re-import path only rebuilds the CSV-backed UjuziPlus lead set and loses database-side history.

Use the CSV re-import path only as a fallback when you intentionally want a fresh rebuild from source exports.

## One-Time Data Move

### 1. Mac: create a data-only dump from the current Postgres

Current Mac dev database from `.env`:

- DB name: `omni_os`
- DB user: `im`
- DB host: `127.0.0.1`
- DB port: `5432`

Exact command:

```bash
mkdir -p "$HOME/backups/omni_os"
pg_dump \
  --dbname=postgresql://im@127.0.0.1:5432/omni_os \
  --format=custom \
  --data-only \
  --no-owner \
  --no-privileges \
  --file="$HOME/backups/omni_os/omni_os_data_$(date +%Y%m%d_%H%M%S).dump"
```

### 2. Mac: copy the dump to Linux

Replace `LINUX_USER` and `LINUX_HOST` with the real target values.

```bash
scp "$HOME/backups/omni_os/omni_os_data_YYYYMMDD_HHMMSS.dump" LINUX_USER@LINUX_HOST:/tmp/
```

### 3. Linux: prepare schema from git-managed migrations

Run this from the project root on Linux:

```bash
php artisan migrate --force
```

### 4. Linux: restore the Mac data dump into Linux Postgres

Replace the connection string as needed for the Linux machine.

```bash
pg_restore \
  --dbname=postgresql://LINUX_USER@127.0.0.1:5432/omni_os \
  --data-only \
  --disable-triggers \
  --no-owner \
  --no-privileges \
  /tmp/omni_os_data_YYYYMMDD_HHMMSS.dump
```

### 5. Linux: verify the restored counts

```bash
psql postgresql://LINUX_USER@127.0.0.1:5432/omni_os -c "select count(*) as leads from leads;"
psql postgresql://LINUX_USER@127.0.0.1:5432/omni_os -c "select count(*) as suppressions from suppressions;"
```

Expected counts:

- `leads = 608`
- `suppressions = 265`

### 6. Mac: wipe real customer data and reseed sample-only local data

After Linux has been restored and verified:

```bash
php artisan migrate:fresh --seed
```

That command rebuilds the local schema and seeds:

- one local dev user
- the four brands
- sample-only leads, suppressions, events, email messages, and mining targets

The local seeder intentionally uses fake names, fake companies, and `example.test` email domains.

## Fallback Re-Import Path

Use this only if you deliberately want to rebuild from the CSV exports instead of migrating the existing state.

On Linux:

```bash
php artisan migrate:fresh
php artisan db:seed
php artisan leads:import-ujuziplus
php artisan emails:import-sequences
```

Caveat:

- this restores CSV-backed UjuziPlus data only
- this does not preserve current approval history or event history from the Mac database

## Deer Null-Email Sanity Check

Current live Mac Postgres counts:

- `608` total leads
- `409` Deer leads
- `191` Deer leads with `email IS NULL`

Source CSV findings from `storage/app/private/ujuziplus_deer.csv`:

- `431` Deer source rows total
- `191` rows with blank `direct_email`
- `0` rows where `direct_email` exceeded `255` characters

Imported database findings for Deer rows where `email IS NULL`:

- `0` rows with a `direct_email` key left in `raw_data`
- `0` rows with a non-blank `direct_email` left in `raw_data`
- `0` rows with any email-like string anywhere in `raw_data`

Sample imported Deer rows with `email IS NULL` show two patterns:

- genuine no-email rows such as SACCO records marked `no_email_found_after_search`
- some column misalignment in non-email fields such as `website` and `category`, but without any recoverable email value

Conclusion:

- The current Deer `email IS NULL` population is not coming from the `strlen($email) > 255` nulling branch.
- The practical count for "nulled this way" is `0`.
- There is no recoverable email payload left in the imported DB for those `191` Deer rows.
- A one-off Deer email repair script is not recommended because there is nothing reliable to recover.

Recommendation:

- Do not spend the upcoming enrichment step trying to repair these `191` Deer emails from the existing imported payload.
- Treat them as true no-email cases unless you obtain a cleaner source export.
- If Deer import quality becomes a priority, improve the importer separately for non-email column drift, but do not invent or infer missing emails.

## Local PII Containment Note

The CSV exports under `storage/app/private/` can also contain customer data.

If Linux is now the only canonical environment, keep those exports on Linux only going forward. If the Mac copies are no longer needed after the restore, remove or archive them outside the local dev machine according to your retention policy.

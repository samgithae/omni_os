#!/usr/bin/env bash

set -euo pipefail

APP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

set -a
source "$APP_ROOT/.env"
set +a

: "${DB_HOST:?DB_HOST must be set in .env}"
: "${DB_PORT:?DB_PORT must be set in .env}"
: "${DB_DATABASE:?DB_DATABASE must be set in .env}"
: "${DB_USERNAME:?DB_USERNAME must be set in .env}"
: "${BACKUP_ROOT:?BACKUP_ROOT must be set in .env}"

RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-14}"
TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
BACKUP_DIR="$BACKUP_ROOT/postgres"
BACKUP_FILE="$BACKUP_DIR/${DB_DATABASE}_${TIMESTAMP}.dump"

mkdir -p "$BACKUP_DIR"

export PGPASSWORD="${DB_PASSWORD:-}"

pg_dump \
  --host="$DB_HOST" \
  --port="$DB_PORT" \
  --username="$DB_USERNAME" \
  --dbname="$DB_DATABASE" \
  --format=custom \
  --file="$BACKUP_FILE"

find "$BACKUP_DIR" -type f -name '*.dump' -mtime +"$RETENTION_DAYS" -delete

if [[ -n "${BACKUP_REMOTE_TARGET:-}" ]]; then
  rsync -az --delete "$BACKUP_DIR/" "$BACKUP_REMOTE_TARGET"
fi

echo "Backup created at $BACKUP_FILE"

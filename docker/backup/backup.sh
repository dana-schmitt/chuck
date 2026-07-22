#!/usr/bin/env bash
set -euo pipefail

# Dumps the production database to a gzip-compressed, timestamped file and
# prunes backups older than $BACKUP_RETENTION_DAYS. Intended to run on the
# deploy host via cron, next to compose.prod.yaml - see README > Production
# > Backups for the full setup and the matching restore command.
#
# Required in the environment (e.g. sourced from the same env file used for
# `docker compose -f compose.prod.yaml up`): MYSQL_ROOT_PASSWORD
# Optional: BACKUP_DIR (default ./backups), BACKUP_RETENTION_DAYS (default 14)

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
COMPOSE_FILE="$SCRIPT_DIR/../../compose.prod.yaml"

BACKUP_DIR="${BACKUP_DIR:-$SCRIPT_DIR/../../backups}"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-14}"

if [ -z "${MYSQL_ROOT_PASSWORD:-}" ]; then
    echo "MYSQL_ROOT_PASSWORD must be set in the environment." >&2
    exit 1
fi

mkdir -p "$BACKUP_DIR"

timestamp="$(date +%Y%m%d-%H%M%S)"
target="$BACKUP_DIR/chuckify-${timestamp}.sql.gz"

docker compose -f "$COMPOSE_FILE" exec -T database \
    mysqldump -uroot -p"${MYSQL_ROOT_PASSWORD}" --single-transaction joke \
    | gzip > "$target"

find "$BACKUP_DIR" -name 'chuckify-*.sql.gz' -mtime "+${RETENTION_DAYS}" -delete

echo "Backup written to ${target}"

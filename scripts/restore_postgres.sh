#!/usr/bin/env bash
# ==============================================================================
# Kaamly Production PostgreSQL Database Restore Script
# Restores a .dump archive directly into the database container
# ==============================================================================
set -euo pipefail

if [ "$#" -ne 1 ]; then
    echo "Usage: $0 <path_to_backup_file.dump>"
    exit 1
fi

BACKUP_FILE="$1"
CONTAINER_NAME="${PG_CONTAINER:-kaamly_postgres_prod}"
DB_NAME="${DB_DATABASE:-kaamly_db}"
DB_USER="${DB_USERNAME:-kaamly_app_user}"

if [ ! -f "${BACKUP_FILE}" ]; then
    echo "Error: Backup file '${BACKUP_FILE}' does not exist!"
    exit 1
fi

echo "========================================================================"
echo "WARNING: Restoring will overwrite existing data in '${DB_NAME}'."
echo "Target Container: ${CONTAINER_NAME}"
echo "Target Database:  ${DB_NAME}"
echo "Source File:      ${BACKUP_FILE}"
echo "========================================================================"
read -p "Type 'RESTORE-CONFIRM' to proceed: " -r CONFIRMATION

if [ "${CONFIRMATION}" != "RESTORE-CONFIRM" ]; then
    echo "Restoration aborted by user."
    exit 0
fi

echo "[$(date -u +"%Y-%m-%dT%H:%M:%SZ")] Beginning PostgreSQL restore..."

# Stream dump archive into container pg_restore
cat "${BACKUP_FILE}" | docker exec -i -e PGPASSWORD="${DB_PASSWORD:?DB_PASSWORD required}" \
    "${CONTAINER_NAME}" \
    pg_restore -U "${DB_USER}" -d "${DB_NAME}" --clean --if-exists -v

echo "[$(date -u +"%Y-%m-%dT%H:%M:%SZ")] PostgreSQL restoration completed successfully!"

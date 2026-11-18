#!/usr/bin/env bash
# Kaamly Production PostgreSQL Database Restore Script
set -e

if [ -z "$1" ]; then
    echo "Usage: $0 <path_to_backup_file.dump>"
    exit 1
fi

BACKUP_FILE="$1"
DB_HOST="${DB_HOST:-postgres}"
DB_PORT="${DB_PORT:-5432}"
DB_NAME="${DB_DATABASE:-kaamly_db}"
DB_USER="${DB_USERNAME:-kaamly_admin}"

if [ ! -f "${BACKUP_FILE}" ]; then
    echo "Error: Backup file ${BACKUP_FILE} does not exist!"
    exit 1
fi

echo "[$(date)] WARNING: Restoring will overwrite existing data in ${DB_NAME}."
read -p "Are you sure you want to proceed? (y/N): " -r
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Restore cancelled."
    exit 0
fi

echo "[$(date)] Restoring database from ${BACKUP_FILE}..."
PGPASSWORD="${DB_PASSWORD}" pg_restore \
    -h "${DB_HOST}" \
    -p "${DB_PORT}" \
    -U "${DB_USER}" \
    -d "${DB_NAME}" \
    --clean \
    --if-exists \
    -v \
    "${BACKUP_FILE}"

echo "[$(date)] Database restoration completed successfully!"

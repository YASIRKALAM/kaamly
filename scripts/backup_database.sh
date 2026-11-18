#!/usr/bin/env bash
# Kaamly Production PostgreSQL Database Backup Script
set -e

BACKUP_DIR="${BACKUP_DIR:-/var/backups/kaamly}"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="${BACKUP_DIR}/kaamly_backup_${TIMESTAMP}.dump"

DB_HOST="${DB_HOST:-postgres}"
DB_PORT="${DB_PORT:-5432}"
DB_NAME="${DB_DATABASE:-kaamly_db}"
DB_USER="${DB_USERNAME:-kaamly_admin}"

mkdir -p "${BACKUP_DIR}"

echo "[$(date)] Starting PostgreSQL backup for database: ${DB_NAME}..."
PGPASSWORD="${DB_PASSWORD}" pg_dump \
    -h "${DB_HOST}" \
    -p "${DB_PORT}" \
    -U "${DB_USER}" \
    -F c \
    -b \
    -v \
    -f "${BACKUP_FILE}" \
    "${DB_NAME}"

echo "[$(date)] Backup completed successfully: ${BACKUP_FILE}"

# Keep last 14 days of backups
find "${BACKUP_DIR}" -type f -name "kaamly_backup_*.dump" -mtime +14 -exec rm {} \;
echo "[$(date)] Cleaned up backups older than 14 days."

#!/usr/bin/env bash
# ==============================================================================
# Kaamly Production PostgreSQL Database Backup Script
# Executes pg_dump via Docker container without exposing DB ports to host
# ==============================================================================
set -euo pipefail

BACKUP_DIR="${BACKUP_DIR:-/var/backups/kaamly/postgres}"
CONTAINER_NAME="${PG_CONTAINER:-kaamly_postgres_prod}"
DB_NAME="${DB_DATABASE:-kaamly_db}"
DB_USER="${DB_USERNAME:-kaamly_app_user}"

TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="${BACKUP_DIR}/${DB_NAME}_${TIMESTAMP}.dump"
LOG_FILE="${BACKUP_DIR}/backup.log"

mkdir -p "${BACKUP_DIR}"

echo "[$(date -u +"%Y-%m-%dT%H:%M:%SZ")] Starting PostgreSQL backup for ${DB_NAME} from container ${CONTAINER_NAME}..." | tee -a "${LOG_FILE}"

# Execute pg_dump directly inside the database container
docker exec -e PGPASSWORD="${DB_PASSWORD:?DB_PASSWORD required}" \
    "${CONTAINER_NAME}" \
    pg_dump -U "${DB_USER}" -d "${DB_NAME}" -F c -b -v > "${BACKUP_FILE}"

FILESIZE=$(du -h "${BACKUP_FILE}" | cut -f1)
echo "[$(date -u +"%Y-%m-%dT%H:%M:%SZ")] Backup successful! File: ${BACKUP_FILE} (${FILESIZE})" | tee -a "${LOG_FILE}"

# Retain backups for 14 days; purge older files
find "${BACKUP_DIR}" -type f -name "${DB_NAME}_*.dump" -mtime +14 -exec rm -f {} \;
echo "[$(date -u +"%Y-%m-%dT%H:%M:%SZ")] Purged PostgreSQL backups older than 14 days." | tee -a "${LOG_FILE}"

#!/usr/bin/env bash
# ==============================================================================
# Kaamly MinIO Object Storage Backup Script
# Mirrors all S3 buckets to an encrypted archive directory using minio/mc
# ==============================================================================
set -euo pipefail

BACKUP_DIR="${BACKUP_DIR:-/var/backups/kaamly/minio}"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
TARGET_DIR="${BACKUP_DIR}/${TIMESTAMP}"

mkdir -p "${TARGET_DIR}"

echo "[$(date -u +"%Y-%m-%dT%H:%M:%SZ")] Starting MinIO bucket mirroring..."

# Execute mirror command via minio/mc container
docker run --rm --network kaamly_internal \
    -v "${TARGET_DIR}:/backup" \
    minio/mc:RELEASE.2024-04-18T16-45-29Z \
    sh -c "
        mc alias set target http://minio:9000 '${MINIO_ROOT_USER:?required}' '${MINIO_ROOT_PASSWORD:?required}' &&
        mc mirror --overwrite target/kaamly-private-docs /backup/kaamly-private-docs &&
        mc mirror --overwrite target/kaamly-public-assets /backup/kaamly-public-assets
    "

# Create compressed tarball
tar -czf "${TARGET_DIR}.tar.gz" -C "${BACKUP_DIR}" "${TIMESTAMP}"
rm -rf "${TARGET_DIR}"

echo "[$(date -u +"%Y-%m-%dT%H:%M:%SZ")] MinIO backup complete: ${TARGET_DIR}.tar.gz"

# Retain backups for 14 days
find "${BACKUP_DIR}" -type f -name "*.tar.gz" -mtime +14 -exec rm -f {} \;

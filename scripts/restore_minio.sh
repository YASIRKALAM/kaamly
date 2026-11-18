#!/usr/bin/env bash
# ==============================================================================
# Kaamly MinIO Object Storage Restore Script
# Restores S3 buckets from a tar.gz archive using minio/mc
# ==============================================================================
set -euo pipefail

if [ "$#" -ne 1 ]; then
    echo "Usage: $0 <path_to_minio_backup.tar.gz>"
    exit 1
fi

ARCHIVE_FILE="$1"
TEMP_DIR="/tmp/minio_restore_$(date +%s)"

if [ ! -f "${ARCHIVE_FILE}" ]; then
    echo "Error: Backup archive '${ARCHIVE_FILE}' does not exist!"
    exit 1
fi

echo "WARNING: Restoring will overwrite existing files in MinIO."
read -p "Type 'RESTORE-MINIO' to confirm: " -r CONFIRMATION
if [ "${CONFIRMATION}" != "RESTORE-MINIO" ]; then
    echo "Restoration aborted."
    exit 0
fi

mkdir -p "${TEMP_DIR}"
tar -xzf "${ARCHIVE_FILE}" -C "${TEMP_DIR}"
EXTRACTED_SUBDIR=$(ls -1 "${TEMP_DIR}" | head -n 1)

docker run --rm --network kaamly_internal \
    -v "${TEMP_DIR}/${EXTRACTED_SUBDIR}:/restore" \
    minio/mc:RELEASE.2024-04-18T16-45-29Z \
    sh -c "
        mc alias set target http://minio:9000 '${MINIO_ROOT_USER:?required}' '${MINIO_ROOT_PASSWORD:?required}' &&
        mc mirror --overwrite /restore/kaamly-private-docs target/kaamly-private-docs &&
        mc mirror --overwrite /restore/kaamly-public-assets target/kaamly-public-assets
    "

rm -rf "${TEMP_DIR}"
echo "[$(date -u +"%Y-%m-%dT%H:%M:%SZ")] MinIO restoration completed successfully!"

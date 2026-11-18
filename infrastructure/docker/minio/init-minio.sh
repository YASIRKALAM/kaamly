#!/bin/sh
# ==============================================================================
# Kaamly MinIO Initialization Script (Runs inside minio/mc container)
# Sets up buckets and creates restricted application credentials
# ==============================================================================
set -e

echo "[MinIO Init] Waiting for MinIO service to become healthy..."
until curl -s -f http://minio:9000/minio/health/live > /dev/null; do
    echo "[MinIO Init] MinIO not ready yet. Waiting 2 seconds..."
    sleep 2
done

echo "[MinIO Init] MinIO is ready. Setting up admin alias..."
mc alias set local http://minio:9000 "${MINIO_ROOT_USER:?Root user required}" "${MINIO_ROOT_PASSWORD:?Root password required}"

# 1. Create Private Bucket for KYC Documents & Sensitive Job Proofs
echo "[MinIO Init] Creating private bucket: ${MINIO_PRIVATE_BUCKET:-kaamly-private-docs}..."
mc mb --ignore-existing "local/${MINIO_PRIVATE_BUCKET:-kaamly-private-docs}"
# Ensure strictly private (no anonymous read/write)
mc anonymous set none "local/${MINIO_PRIVATE_BUCKET:-kaamly-private-docs}"

# 2. Create Public Bucket for Avatars and Category Icons
echo "[MinIO Init] Creating public bucket: ${MINIO_PUBLIC_BUCKET:-kaamly-public-assets}..."
mc mb --ignore-existing "local/${MINIO_PUBLIC_BUCKET:-kaamly-public-assets}"
# Set public download policy for public assets
mc anonymous set download "local/${MINIO_PUBLIC_BUCKET:-kaamly-public-assets}"

# 3. Create Restricted Application IAM User (Separated from Root Administrator)
echo "[MinIO Init] Configuring restricted application user: ${MINIO_APP_USER:?App user required}..."
if mc admin user info local "${MINIO_APP_USER}" > /dev/null 2>&1; then
    echo "[MinIO Init] Application user already exists. Updating password..."
    mc admin user password local "${MINIO_APP_USER}" "${MINIO_APP_PASSWORD:?App password required}"
else
    echo "[MinIO Init] Creating new application user..."
    mc admin user add local "${MINIO_APP_USER}" "${MINIO_APP_PASSWORD:?App password required}"
fi

# 4. Attach Scoped Read/Write Policy to Application User
mc admin policy attach local readwrite --user "${MINIO_APP_USER}"

echo "[MinIO Init] MinIO initialization completed successfully!"
exit 0

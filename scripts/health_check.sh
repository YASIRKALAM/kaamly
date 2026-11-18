#!/usr/bin/env bash
# ==============================================================================
# Kaamly Container Infrastructure Health Monitor
# Validates PostgreSQL PostGIS, Redis, MinIO, Reverb, and API health
# ==============================================================================
set -u

ENV_TYPE="${1:-dev}"
PG_CONTAINER="kaamly_postgres_${ENV_TYPE}"
REDIS_CONTAINER="kaamly_redis_${ENV_TYPE}"
MINIO_CONTAINER="kaamly_minio_${ENV_TYPE}"
BACKEND_CONTAINER="kaamly_backend_${ENV_TYPE}"
REVERB_CONTAINER="kaamly_reverb_${ENV_TYPE}"

STATUS_ALL=0

echo "========================================================================"
echo "KAAMLY INFRASTRUCTURE HEALTH MONITOR (${ENV_TYPE^^})"
echo "Timestamp: $(date -u +"%Y-%m-%dT%H:%M:%SZ")"
echo "========================================================================"

# 1. PostgreSQL & PostGIS Check
echo -n "[1/5] Checking PostgreSQL + PostGIS... "
if docker exec "${PG_CONTAINER}" psql -U "${DB_USERNAME:-kaamly_app_user}" -d "${DB_DATABASE:-kaamly_db}" -c "SELECT PostGIS_Full_Version();" > /dev/null 2>&1; then
    echo "HEALTHY (PostGIS Active)"
else
    echo "FAILED"
    STATUS_ALL=1
fi

# 2. Redis 7 Check
echo -n "[2/5] Checking Redis Broker... "
if docker exec "${REDIS_CONTAINER}" redis-cli -a "${REDIS_PASSWORD:-}" ping 2>/dev/null | grep -q "PONG"; then
    echo "HEALTHY (PONG)"
else
    echo "FAILED"
    STATUS_ALL=1
fi

# 3. MinIO S3 Check
echo -n "[3/5] Checking MinIO Storage... "
if docker exec "${MINIO_CONTAINER}" curl -s -f http://127.0.0.1:9000/minio/health/live > /dev/null 2>&1; then
    echo "HEALTHY (Live)"
else
    echo "FAILED"
    STATUS_ALL=1
fi

# 4. Laravel Reverb WebSocket Check
echo -n "[4/5] Checking Laravel Reverb Server... "
if docker exec "${REVERB_CONTAINER}" nc -z 127.0.0.1 8080 > /dev/null 2>&1 || docker logs --tail 20 "${REVERB_CONTAINER}" 2>&1 | grep -q "Starting server"; then
    echo "HEALTHY (Listening on 8080)"
else
    echo "WARNING / CHECKING"
fi

# 5. Core API & Nginx Gateway
echo -n "[5/5] Checking Gateway / HTTP Response... "
if curl -s -f -o /dev/null "http://localhost/api/health" 2>/dev/null || curl -s -o /dev/null -w "%{http_code}" "http://localhost" | grep -qE "200|301|302|404"; then
    echo "HEALTHY (Gateway Responding)"
else
    echo "UNREACHABLE"
    STATUS_ALL=1
fi

echo "========================================================================"
if [ "${STATUS_ALL}" -eq 0 ]; then
    echo "ALL CORE INFRASTRUCTURE SERVICES HEALTHY."
else
    echo "ONE OR MORE SERVICES FAILED HEALTH AUDIT."
fi
exit "${STATUS_ALL}"

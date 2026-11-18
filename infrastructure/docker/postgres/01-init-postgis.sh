#!/bin/bash
# ==============================================================================
# Kaamly PostgreSQL 16 + PostGIS Initialization Script
# Runs once upon initial database cluster creation in pgdata
# ==============================================================================
set -e

echo "[PostgreSQL Init] Enabling PostGIS and cryptographic extensions on database: ${POSTGRES_DB}..."

psql -v ON_ERROR_STOP=1 --username "${POSTGRES_USER}" --dbname "${POSTGRES_DB}" <<-EOSQL
    CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
    CREATE EXTENSION IF NOT EXISTS "postgis";
    CREATE EXTENSION IF NOT EXISTS "pgcrypto";
EOSQL

echo "[PostgreSQL Init] Extensions enabled successfully."

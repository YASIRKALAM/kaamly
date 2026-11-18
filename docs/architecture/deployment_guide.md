# KAAMLY PLATFORM – INFRASTRUCTURE & DEPLOYMENT OPERATIONS MANUAL

## 1. Overview & Architectural Isolation

The **Kaamly** infrastructure is architected for strict separation of concerns, container isolation, and production security.

```
Host / Edge Network
       │
   [Port 80 / 443]
       ▼
 ┌──────────────┐
 │ Nginx (Proxy)│
 └──────┬───────┘
        │
        ├────────────► [/admin] ──► [Next.js 16 Web Portal (Node 22)]
        ├────────────► [/app]   ──► [Laravel Reverb (WebSockets 8080)]
        └────────────► [/api]   ──► [Laravel 13 API (PHP 8.3-FPM 9000)]
                                           │
          ┌────────────────────────────────┼────────────────────────────────┐
          ▼                                ▼                                ▼
  ┌──────────────┐                 ┌──────────────┐                 ┌──────────────┐
  │PostgreSQL 16 │                 │   Redis 7    │                 │ MinIO S3     │
  │+ PostGIS     │                 │ (Queues/Lock)│                 │ (Private/Pub)│
  │(Port 5432)   │                 │ (Port 6379)  │                 │ (Port 9000)  │
  └──────────────┘                 └──────────────┘                 └──────────────┘
   [Internal Only]                  [Internal Only]                  [Internal Only]
```

---

## 2. Environment Configuration

### Rule 1: No Hardcoded Default Credentials
Both development and production Compose files use bash parameter expansion guards:
`${DB_PASSWORD:?DB_PASSWORD required in .env}`.
If an administrator or developer attempts to run `docker compose up` without declaring credentials in `.env`, Docker Compose will abort execution immediately with an explicit error message.

### Rule 2: Credentials Separation
* **MinIO Root User**: Used solely for initial storage initialization and root management.
* **MinIO App User**: A restricted IAM application user with scoped permissions (`readwrite` on `kaamly-private-docs` and `kaamly-public-assets`). The Laravel application connects **exclusively** via these application credentials.

---

## 3. Local Development Operations

### A. Initial Setup
```bash
# 1. Clone or navigate to the project directory
cd /path/to/Kaamly

# 2. Copy the environment template
cp .env.example .env

# 3. Open .env and set secure keys and passwords
# (Ensure DB_PASSWORD, REDIS_PASSWORD, MINIO_ROOT_PASSWORD, MINIO_APP_PASSWORD, APP_KEY are defined)
```

### B. Starting the Development Stack
```bash
docker compose up -d
```

### C. Running Laravel Database Migrations
> **IMPORTANT NOTE ON POSTGRESQL VOLUMES:**
> PostgreSQL's `/docker-entrypoint-initdb.d/` scripts only execute **once** during the very first creation of the database volume (`pgdata`). Once the database exists, `schema.sql` is never automatically re-run.
> Therefore, **all ongoing schema alterations, index additions, and table creations must be performed via Laravel Migrations.**

```bash
# Run migrations inside the backend container
docker compose exec backend php artisan migrate

# Seed baseline roles, permissions, and test categories
docker compose exec backend php artisan db:seed
```

### D. Checking Container Status & Logs
```bash
# Inspect all running containers
docker compose ps

# Tail backend API logs
docker compose logs -f backend

# Tail Reverb WebSocket server logs
docker compose logs -f reverb

# Tail Queue Worker logs
docker compose logs -f queue_worker
```

### E. Graceful Shutdown
```bash
docker compose down
```

---

## 4. Production Deployment Operations

### A. Host Prerequisites
* **Operating System**: Ubuntu 22.04 LTS / 24.04 LTS or Debian 12
* **Container Engine**: Docker Engine 24.0+ & Docker Compose v2.20+
* **System Resources**: Minimum 4 vCPUs, 8 GB RAM, 80 GB SSD
* **Network Firewalls**: Allow incoming traffic on ports **80** (HTTP) and **443** (HTTPS) only. Disallow host access on 5432, 6379, 9000, 9001.

### B. TLS / SSL Certificate Installation
Mount valid production certificates from your host (e.g. Let's Encrypt / Certbot):

```bash
# Example obtaining Let's Encrypt certificate:
sudo certbot certonly --standalone -d api.kaamly.com -d admin.kaamly.com -d ws.kaamly.com

# In .env.production:
SSL_CERT_PATH=/etc/letsencrypt/live/kaamly.com/fullchain.pem
SSL_KEY_PATH=/etc/letsencrypt/live/kaamly.com/privkey.pem
```

### C. Launching the Production Stack
```bash
# 1. Prepare production environment file
cp .env.example .env.production
nano .env.production # Set APP_ENV=production, APP_DEBUG=false, and production passwords

# 2. Build and start production containers
docker compose -f docker-compose.prod.yml --env-file .env.production up -d --build

# 3. Execute database migrations
docker compose -f docker-compose.prod.yml --env-file .env.production exec backend php artisan migrate --force

# 4. Cache configurations and routes for maximum performance
docker compose -f docker-compose.prod.yml --env-file .env.production exec backend php artisan config:cache
docker compose -f docker-compose.prod.yml --env-file .env.production exec backend php artisan route:cache
docker compose -f docker-compose.prod.yml --env-file .env.production exec backend php artisan view:cache
```

---

## 5. Backup, Restoration & Maintenance Protocols

### A. Automated PostgreSQL Backups
The script [`scripts/backup_postgres.sh`](file:///d:/Study/code/Kaamly/scripts/backup_postgres.sh) executes `pg_dump` directly inside the container without exposing port 5432:

```bash
# Execute manual backup:
./scripts/backup_postgres.sh

# Recommended Crontab entry (Runs daily at 02:00 UTC):
# 0 2 * * * /path/to/Kaamly/scripts/backup_postgres.sh >> /var/log/kaamly_pg_backup.log 2>&1
```

### B. PostgreSQL Restoration (Disaster Recovery)
```bash
./scripts/restore_postgres.sh /var/backups/kaamly/postgres/kaamly_db_20261116_120000.dump
```

### C. MinIO S3 Object Storage Backups
The script [`scripts/backup_minio.sh`](file:///d:/Study/code/Kaamly/scripts/backup_minio.sh) uses the `minio/mc` container to mirror buckets into compressed local archives:

```bash
# Execute manual S3 backup:
./scripts/backup_minio.sh

# Recommended Crontab entry (Runs daily at 03:00 UTC):
# 0 3 * * * /path/to/Kaamly/scripts/backup_minio.sh >> /var/log/kaamly_minio_backup.log 2>&1
```

### D. Health Monitoring Audit
Run the automated diagnostic suite at any time to verify PostgreSQL PostGIS, Redis, MinIO, Reverb, and API responses:

```bash
./scripts/health_check.sh dev    # For development stack
./scripts/health_check.sh prod   # For production stack
```

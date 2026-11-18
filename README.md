# Kaamly – On-Demand Service Marketplace Platform (Revised Architecture)

Kaamly is an enterprise-grade, high-concurrency service marketplace connecting customers with verified local technicians and skilled service providers.

---

## 1. Monorepo Structure

```
Kaamly/
├── backend/                   # Laravel 13 REST API & Real-time WebSockets (PHP 8.3+)
├── admin-portal/              # Next.js 16 Admin Dashboard (React 19 / TypeScript)
├── mobile/                    # Flutter Multi-App Monorepo
│   ├── customer-app/          # Kaamly Customer Mobile App
│   ├── provider-app/          # Kaamly Service Provider Mobile App
│   └── shared/                # Shared Dart models, API clients & widgets
├── database/                  # Master PostgreSQL / PostGIS schemas, dictionaries & seeds
│   ├── documentation/         # Schema descriptions and audit rules
│   ├── diagrams/              # Entity-Relationship (ER) source diagrams
│   └── schema.sql             # Production DDL definition with PostGIS & Double-Entry Ledger
├── infrastructure/            # Production & Local Infrastructure
│   ├── docker/                # PHP 8.3-FPM, Reverb, Worker Dockerfiles
│   ├── nginx/                 # Gateway reverse proxy configurations
│   └── deployment/            # Linux server deployment & backup scripts
├── docs/                      # Technical Documentation
│   ├── architecture/          # System architecture, state machines & financial ledger
│   ├── database/              # Data dictionary & ER diagrams
│   └── api/                   # OpenAPI / REST API route specifications
├── scripts/                   # Database backup, restore & maintenance utilities
├── docker-compose.yml         # Local container orchestration
└── .gitignore                 # Repository ignore rules
```

---

## 2. Technology Stack – Upgraded Standards

* **Database**: PostgreSQL 16 with PostGIS spatial extension (Central Single Source of Truth)
* **Backend API**: Laravel 13 REST API (PHP 8.3+)
* **Admin Web Portal**: Next.js 16 (React 19 / TypeScript / Tailwind CSS)
* **Mobile Applications**: Flutter 3.x / Dart (Customer & Provider apps)
* **Real-Time Communication**: Laravel Reverb WebSockets
* **Queue & Cache**: Redis 7
* **Object Storage**: S3-compatible (MinIO / AWS S3) with time-limited pre-signed URLs
* **Gateway**: Nginx reverse proxy

---

## 3. Core Architectural Highlights

1. **True Double-Entry General Ledger**:
   - `ledger_accounts`, `ledger_transactions`, `ledger_entries`.
   - Immutable once posted, balanced ($\sum \text{Debit} = \sum \text{Credit}$), and idempotent.
   - Comprehensive accounting for Cash On Delivery (COD), Online Escrow, Commission Receivables, Settlements, and Dispute Awards.

2. **13-State Booking Lifecycle State Machine**:
   - `pending` -> `broadcasted` -> `accepted` -> `quotation_submitted` -> `awaiting_customer_confirmation` -> `confirmed` -> `provider_on_the_way` -> `in_progress` -> `completed` -> `cancelled` -> `disputed` -> `rescheduled` -> `no_show`.
   - Strict transition permissions, cancellation fee rules, 20-min wait customer no-show protocols, 45-min provider no-show penalties.

3. **6-Gate PostGIS Spatial Discovery Engine**:
   - Evaluates spatial proximity (`ST_DWithin`), polygon service area containment (`ST_Contains`), provider verification status, location freshness ($\le 30$ mins), approved active pricing within market bounds, shift availability, day-off exclusion, and booking conflict exclusion.

4. **Security, Governance & Audit Controls**:
   - Application-level `AES-256-GCM` encryption for sensitive PII/banking numbers.
   - Private S3 document storage with 15-minute temporary pre-signed URLs.
   - Immutable, tamper-proof `audit_logs` storing JSONB deltas.
   - Maker-Checker dual authorization for manual ledger adjustments exceeding PKR 10,000.

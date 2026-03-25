# External Integrations

**Analysis Date:** 2026-03-24

## APIs & External Services

**None explicitly observed yet** outside of default Laravel framework features. No explicit third-party REST/GraphQL clients configured in `.env.example` except basic AWS S3 variables.

## Data Storage

**Databases:**
- SQLite (Default Local) / MySQL / PostgreSQL (Laravel PDO) - Primary data store
  - Connection: via `DB_CONNECTION` in `.env` (default: `sqlite`)
  - Client: Laravel Eloquent ORM
  - Migrations: `php artisan migrate`

**File Storage:**
- AWS S3 (Optional) - Cloud object storage
  - Connection: Configured via `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_BUCKET`
  - Integration: Laravel Filesystem (`FILESYSTEM_DISK=s3`)
- Local Disk - Default (`FILESYSTEM_DISK=local`)

**Caching & Queues:**
- Database - Local default (`CACHE_STORE=database`, `QUEUE_CONNECTION=database`)
- Redis / Memcached - Supported but disabled by default
  - Connection: `REDIS_HOST`, `REDIS_PORT`, `MEMCACHED_HOST`

## Authentication & Identity

**Auth Provider:**
- Laravel Fortify - Backend headless authentication
  - Implementation: Session-based via `SESSION_DRIVER=database`

**OAuth Integrations:**
- None identified natively.

## Monitoring & Observability

**Error Tracking:**
- None identified (Default Laravel exception handling).

**Analytics:**
- None configured.

**Logs:**
- Laravel Log stack (`LOG_CHANNEL=stack`)
  - Files stored in `storage/logs/laravel.log` typically

## CI/CD & Deployment

**Hosting:**
- Standard PHP/Laravel hosting

**CI Pipeline:**
- Unknown / None identified in root directory (No `.github/workflows` found yet)

## Environment Configuration

**Development:**
- Required env vars: Standard Laravel defaults (`APP_KEY`, `DB_CONNECTION`)
- Secrets location: `.env` (gitignored), template in `.env.example`
- Local dev server: `php artisan serve`, `npm run dev`, Laravel Sail

**Production:**
- Standard Laravel environment requirements.
- Expects `APP_ENV=production`, `APP_DEBUG=false`.

## Webhooks & Callbacks

**Incoming/Outgoing:**
- None identified natively.

---

*Integration audit: 2026-03-24*
*Update when adding/removing external services*

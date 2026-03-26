# Stack Research

**Domain:** Political campaign operations platform
**Researched:** 2026-03-25
**Confidence:** HIGH

## Recommended Stack

### Core Technologies

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| PHP + Laravel | 12.x | Core application framework, auth, queues, policies, jobs, notifications, and testing surface | SIGMA already uses Laravel 12 successfully, and the framework is a strong fit for brownfield hardening because it supports policy enforcement, queues, events, and operational workflows without forcing a rewrite |
| Filament | 4.x current in repo | Admin and role-based operational back office | Filament accelerates role-aware CRUD, dashboards, resources, tables, and widgets. For SIGMA's current milestone, staying on the existing major is safer than a broad admin rewrite |
| Livewire + Volt + Flux | Livewire 3.x / Volt 1.7 / Flux 2.1 in repo | Server-driven operational UI for workflow-heavy screens | SIGMA's users need fast iteration on forms, tables, status transitions, and role-specific actions. The existing Livewire pattern is already a strong fit for these internal operations workflows |
| PostgreSQL | 17+ | Primary production database for transactional integrity, reporting queries, and higher write concurrency | The project docs already flag SQLite risk in production. PostgreSQL 17 emphasizes concurrency and bulk data improvements, which matches multi-campaign reporting, imports, and audit trails better than SQLite |
| Redis | 8.x | Queues, cache, locks, rate limiting, and transient workflow coordination | Redis is a strong companion for queues, dedupe guards, scheduled tasks, and cache-backed reporting snapshots in operations-heavy systems |

### Supporting Libraries

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `spatie/laravel-permission` | 6.x | Roles and permissions | Keep as the authority for role predicates, but centralize campaign-aware authorization rules around policies and shared guard helpers |
| `maatwebsite/excel` | 3.1 | Import/export of operational datasets | Use for controlled voter imports/exports with strict validation, preview, dry-run, and audit metadata |
| Laravel queues + Horizon | 12.x companion | Background jobs and queue visibility | Use for import processing, SMS callbacks, reconciliation, and reporting refresh jobs once concurrency and job volume rise |
| Laravel Pulse or equivalent observability | 12.x companion | Operational insight into slow queries, queues, cache, and exceptions | Use when hardening trust-critical workflows so regressions in imports, dashboards, or follow-up queues are visible quickly |
| Twilio or equivalent callback-capable messaging provider | current vendor API | Delivery-status tracking and webhook-driven message lifecycle updates | Use when message status and delivery visibility matter more than simple fire-and-forget SMS sending |

### Development Tools

| Tool | Purpose | Notes |
|------|---------|-------|
| Pest + Browser tests | Regression protection for operational workflows | Keep strengthening end-to-end coverage around campaign isolation, imports, dashboards, call queues, and Day D flows |
| Laravel Pint | Style consistency | Keep mandatory in workflow; this project already has established conventions |
| Playwright | Browser-level verification | Use for cross-role operational flows where widget failures, visibility bugs, or status transitions matter |

## Installation

```bash
# Core brownfield hardening additions to consider
composer require laravel/horizon laravel/pulse predis/predis

# Optional observability / logging support
composer require sentry/sentry-laravel
```

## Alternatives Considered

| Recommended | Alternative | When to Use Alternative |
|-------------|-------------|-------------------------|
| PostgreSQL 17+ | MySQL 8+ | Use MySQL if hosting constraints are fixed around MySQL and reporting workloads stay moderate |
| Livewire operational UI | SPA frontend rewrite | Only use a SPA if SIGMA later grows into a highly interactive public-facing product with major offline/mobile requirements beyond admin operations |
| Redis-backed queues and locks | Database queue only | Acceptable for very small deployments, but not ideal once imports, messaging callbacks, and reporting refreshes overlap |

## What NOT to Use

| Avoid | Why | Use Instead |
|-------|-----|-------------|
| SQLite in production for multi-operator workloads | The local codebase concern already flags write-lock risk and concurrency limits | PostgreSQL 17+ |
| Big-bang upgrade of Laravel/Filament/Livewire during this hardening milestone | It mixes platform migration risk into an operations-trust milestone | Stabilize current stack first, then plan framework upgrades separately |
| Fire-and-forget SMS delivery assumptions | Messaging status is not reliable unless you ingest callbacks and reconcile outcomes | Provider callbacks plus auditable message state transitions |

## Stack Patterns by Variant

**If the deployment remains single-tenant per instance with low operator concurrency:**
- Laravel monolith + PostgreSQL + Redis is enough
- Because the main risks are data correctness, queues, and reporting trust, not service decomposition

**If SIGMA later adds offline-first field apps or public volunteer apps:**
- Add a dedicated mobile or PWA client with sync boundaries
- Because field canvassing and election-day use can justify offline capabilities that the current admin-first UI does not need yet

## Version Compatibility

| Package A | Compatible With | Notes |
|-----------|-----------------|-------|
| `laravel/framework:^12.0` | `filament/filament:^4.0` | Current repo alignment; keep stable during hardening |
| `laravel/framework:^12.0` | `livewire/volt:^1.7` and Flux 2.x | Current repo alignment |
| `tailwindcss:^4.0` | `@tailwindcss/vite:^4.x` + `vite:^7.x` | Current repo alignment |

## Sources

- Local source: `/Volumes/NAS(MAC)/Data/Herd/sigma-project/composer.json` - current backend stack
- Local source: `/Volumes/NAS(MAC)/Data/Herd/sigma-project/package.json` - current frontend/dev tooling
- Local source: `/Volumes/NAS(MAC)/Data/Herd/sigma-project/.planning/codebase/CONCERNS.md` - current production risk around SQLite
- Official docs: https://livewire.laravel.com/docs/4.x/quickstart - confirms current Livewire documentation baseline has moved to 4.x, which argues against mixing an upgrade into this hardening milestone
- Official docs: https://www.postgresql.org/about/press/presskit17/ - PostgreSQL 17 positioning around concurrency and performance
- Official docs: https://redis.io/docs/latest/develop/whats-new/8-0/ - Redis 8 current capabilities and performance positioning
- Official docs: https://www.twilio.com/docs/messaging/guides/outbound-message-status-in-status-callbacks - delivery callback model for production messaging workflows

---
*Stack research for: political campaign operations platform*
*Researched: 2026-03-25*

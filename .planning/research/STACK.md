# Stack Research

**Domain:** Political campaign operations platforms for voter operations, territorial coordination, communications, reporting trust, and election-day execution
**Researched:** 2026-03-25
**Confidence:** HIGH for core stack, MEDIUM for provider-specific integrations

## Recommendation Summary

SIGMA should remain a Laravel modular monolith. The existing Laravel 12 + Filament 4 + Livewire 3 stack is already a strong fit for multi-panel operator software, role-aware workflows, fast CRUD iteration, and election operations where business rules matter more than frontend novelty. Do not spend this milestone rewriting the control plane in React/Next.js or splitting services just to look more "modern."

The production-grade 2026 version of this stack is: PostgreSQL 17 with PostGIS for the system of record, Redis for queues/cache/session/broadcasting, Horizon for operational queue control, S3-compatible object storage for evidence and imports, Reverb for realtime coordination, Pulse plus Telescope plus production monitoring for observability, and Scout with Meilisearch only where voter search or geo-aware operator lookup actually needs it.

For integrations, use an adapter layer and outbox pattern inside Laravel. That means Twilio Voice/Messaging for telephony and SMS-first communications, optional WhatsApp only where provider approval and campaign compliance are workable, SES or Postmark for staff/system email, and provider webhooks stored immutably for delivery and audit evidence. For reporting, start with PostgreSQL read models and projection tables. Add ClickHouse only if event volume or multi-campaign analytics genuinely outgrow Postgres.

## Where The Existing Stack Is Already Correct

- **Laravel 12 is still a safe base for this milestone:** Laravel 12 receives security fixes until **February 24, 2027**, so hardening work does not need to stop for an immediate framework rewrite. Laravel 13 was released on **March 17, 2026**, but its biggest additions are AI-native features and new APIs, not must-have fixes for SIGMA's current trust problems.
- **Filament remains the right operator UI framework:** SIGMA is a role-heavy internal operations product, not a consumer marketing site. Filament's server-driven tables, forms, actions, widgets, and multi-panel model are aligned with campaign admin, coordinator, leader, and Day D workflows.
- **Livewire remains the right interaction model:** For voter queues, validation review, follow-up worklists, and election-day lookup/marking, Livewire keeps workflow logic close to Laravel policies, validation, and transactions. That is an advantage here, not a limitation.
- **Tailwind 4 + Vite is already current:** The repo already uses Tailwind 4 and the Vite plugin, which matches current Tailwind guidance.

## Recommended Stack

### Core Technologies

| Technology | Version | Purpose | Why Recommended | Confidence |
|------------|---------|---------|-----------------|------------|
| Laravel | `12.x` now, plan `13.x` after hardening baseline | Application framework and control plane | Keep 12 during this milestone because it is supported through 2027 and the product risk is workflow trust, not framework capability. Plan 13 only once hardening work is stable or if AI/search features become a roadmap item. | HIGH |
| PHP | `8.3` minimum target for infra, `8.4` acceptable | Runtime | Laravel 13 requires PHP 8.3+, and even if SIGMA stays on Laravel 12 through this milestone, standardizing infrastructure on 8.3+ avoids a second platform jump later. | HIGH |
| Filament | `4.x` now, `5.x` only with planned Livewire upgrade | Multi-panel operator UI | Filament is a strong fit for admin dashboards, review queues, exports, widgets, and task-oriented operations. Stay on 4.x while hardening unless a deliberate Livewire 4 migration is funded. | HIGH |
| Livewire | `3.x` now, `4.x` with Filament 5 | Server-driven interactivity | Livewire is the right choice for policy-heavy workflow screens. Move to 4.x only when the Filament 5 upgrade is scheduled, not as a side quest inside the hardening milestone. | HIGH |
| PostgreSQL | `17.x` | Primary transactional database | PostgreSQL is the right 2026 system of record for strict data integrity, concurrency, materialized reporting patterns, and optional defense-in-depth with row-level security on the highest-risk tables and service-account queries. | HIGH |
| PostGIS | `3.5.x` | Geospatial territory and polling-place logic | Territorial coordination is native to this product. PostGIS keeps geospatial truth in the database instead of pushing campaign geography into ad hoc external map logic. | HIGH |
| Redis | `7.x` | Cache, session, queue, broadcast backend | Database-backed queues and cache are fine for local development but the wrong production default for election operations. Redis is the standard backend for fast queueing, cache invalidation, and realtime fanout in Laravel apps. | HIGH |
| S3-compatible object storage | Current GA service | Evidence photos, import files, exports, callback payloads | Day D evidence, import artifacts, and export archives should live in durable object storage, not local disk on app servers. | HIGH |

### Supporting Libraries And Platform Capabilities

| Library / Capability | Version | Purpose | When to Use | Confidence |
|----------------------|---------|---------|-------------|------------|
| `laravel/horizon` | latest Laravel-compatible | Queue supervision, retries, throughput, failed-job operations | Add immediately when moving SIGMA from `database` queues to Redis. This is baseline hardening for imports, message fanout, validation jobs, and projection refresh. | HIGH |
| `laravel/pulse` | latest Laravel-compatible | App-level performance and usage metrics | Add immediately for queue latency, slow endpoints, slow jobs, and active-user visibility. Pulse is the right first-party dashboard for operational health. | HIGH |
| `laravel/telescope` | latest Laravel-compatible | Local and staging deep debugging | Use in non-production environments for request, query, notification, and job debugging. Keep it out of production unless tightly controlled. | HIGH |
| Laravel Nightwatch or existing approved APM | current GA | Production monitoring and issue workflow | Use when SIGMA needs production-grade exception, request, job, and query correlation. Choose Nightwatch if managed Laravel-native observability is acceptable for data policy; otherwise keep an approved APM already accepted by the organization. | MEDIUM |
| `laravel/reverb` | latest Laravel-compatible | Realtime updates for queues, dashboards, and Day D counters | Add when coordinator dashboards, call queues, or election-day participation views need near-live updates without polling every surface. | HIGH |
| `laravel/sanctum` | latest Laravel-compatible | First-party SPA/mobile/API auth | Use for first-party clients and internal APIs. This is the correct auth layer if SIGMA exposes mobile or partner-facing endpoints later. | HIGH |
| `laravel/fortify` | `1.x` | Auth flows, password reset, 2FA foundation | Keep it. Fortify is already installed and is still the right fit for first-party authentication. Extend with MFA/passkeys if exposure broadens. | HIGH |
| `laravel/pennant` | latest Laravel-compatible | Feature flags and rollout control | Add before changing core voter, reporting, or Day D behavior in production. Pennant is the safe way to roll out high-risk workflow changes incrementally by campaign or role. | HIGH |
| `laravel/scout` | latest Laravel-compatible | Unified app search abstraction | Use Scout as the boundary so SIGMA can start with the database engine and move specific search workloads to Meilisearch later without rewriting calling code. | HIGH |
| Meilisearch | `1.x` current stable | Fuzzy, prefix, faceted, multi-tenant, and geo-aware operator search | Add when voter/person lookup, assignment lookup, or territory search outgrow PostgreSQL full-text or when typo tolerance and geo search materially improve operator speed. Do not add it just because "search feels modern." | HIGH |
| ClickHouse | current stable cloud/self-managed release | High-volume event analytics and pre-aggregated multi-campaign reporting | Add only if Postgres read models stop meeting latency or retention goals. This is not the default reporting store for the next milestone. | MEDIUM |
| `larastan/larastan` | `3.x` | Type-aware static analysis for Laravel | Add in dev now. The current app already has a production failure caused by a relation-vs-builder type mismatch; Larastan is the right tool to catch that class of bug earlier. | HIGH |
| Pest + Browser / Playwright | already present | Workflow and browser verification | Keep and expand. This stack is already in the repo and should become the enforcement layer for campaign isolation, widgets, exports, imports, and Day D flows. | HIGH |
| `maatwebsite/excel` staging pipeline | `3.1.x` already present | CSV/XLSX import-export | Keep the package, but only behind staging tables, preview/validation, and audited commit steps. Never let operator uploads write directly into live voter truth. | HIGH |

### Integration Categories

| Category | Recommended Approach | Why | Confidence |
|----------|----------------------|-----|------------|
| Telephony and call-center workflow | Twilio Programmable Voice with webhook-driven call state, optional TaskRouter/Flex only if a full agent desktop is required | SIGMA already has call-center workflows. Keep those inside Laravel/Filament if the need is outbound calling, callback queues, and disposition capture. Use Flex only if supervisor tooling, skills-based routing, and omnichannel agent UX become a product in themselves. | MEDIUM |
| SMS outreach | Twilio Messaging or an equivalent approved regional provider behind a Laravel adapter | SMS remains the safest baseline campaign channel because it is simpler to operationalize than WhatsApp and integrates cleanly with outbox, retries, suppression, and delivery callbacks. | MEDIUM |
| WhatsApp outreach | Optional channel, never the only channel; keep behind the same message-intent and suppression model as SMS | WhatsApp can be useful, but onboarding, approval, and campaign-category restrictions vary. Do not design SIGMA's communications model around the assumption that every campaign can use it. | MEDIUM |
| Staff and system email | AWS SES or Postmark | Use email for staff alerts, export notifications, password flows, and exception reports. Do not make email the main voter-operations channel in this milestone. | MEDIUM |
| Evidence/media storage | S3-compatible object storage plus immutable metadata rows in Postgres | Evidence files need durable storage, signed access, retention control, and traceable metadata tied to campaign, actor, and operation. | HIGH |
| Mapping and territory rendering | PostGIS as source of truth, map provider only as rendering/geocoding layer | Do not let external map APIs become the authoritative territory model. SIGMA should own shapes, assignments, and spatial joins in Postgres. | HIGH |
| Decision support / AI | Only after data hardening; prefer Laravel 13 + PostgreSQL `pgvector` or Meilisearch hybrid search when actually funded | Decision support is valuable, but it depends on trusted data. Do not add LLM features before voter state, assignments, reporting definitions, and communication ledgers are reliable. | MEDIUM |

## Development Tools

| Tool | Purpose | Notes |
|------|---------|-------|
| Larastan | Static analysis for Eloquent, relations, builders, policies, and query contracts | Add to CI early. This is directly relevant to the existing `HasMany` vs `Builder` production bug class. |
| Pest | Domain, integration, and feature tests | Keep as the default PHP test runner. Focus new coverage on campaign isolation, exports, jobs, and projection correctness. |
| Pest Browser / Playwright | Role-aware browser workflows and Day D UI verification | Already present. Use it for admin/coordinator/leader parity, queue widgets, and critical Day D flows. |
| Laravel Pint | Code style and low-noise diffs | Keep in CI so refactors around policy and domain boundaries stay readable. |
| Laravel Pail | Tail and filter logs during active debugging | Keep for local/staging troubleshooting, especially around queues and provider callbacks. |

## Installation

```bash
# Baseline hardening
composer require laravel/horizon laravel/pulse laravel/reverb laravel/pennant laravel/scout laravel/sanctum
composer require --dev larastan/larastan

# Optional search
composer require meilisearch/meilisearch-php http-interop/http-factory-guzzle

# Optional high-concurrency runtime
composer require laravel/octane

# Realtime client support when using Reverb
npm install laravel-echo pusher-js
```

## Alternatives Considered

| Recommended | Alternative | When to Use Alternative |
|-------------|-------------|-------------------------|
| Laravel modular monolith | Node/TypeScript microservices + React admin | Only if SIGMA becomes a multi-product platform with independently scaled teams and public APIs as the primary surface. That is not the current problem. |
| Filament + Livewire panels | React/Next.js admin rewrite | Use only if the operator UX becomes heavily custom, offline-first, or front-end-platform-centric. For SIGMA's current workflow-heavy surfaces, rewrite cost exceeds value. |
| PostgreSQL + PostGIS | MySQL-only stack | Use only if the organization is already irreversibly standardized on MySQL and does not need strong geospatial operations. For territorial campaign software, PostgreSQL is the better fit. |
| Redis + Horizon | Database queue/cache/session | Keep database drivers for local/dev simplicity only. Production election operations should not depend on them. |
| Scout + database engine first, Meilisearch second | OpenSearch/Elasticsearch first | Use OpenSearch only if SIGMA eventually needs cross-index analytics, huge document-style search, or organization-wide search infrastructure already exists. For operator lookup and queue search, it is usually overkill. |
| Postgres projections first, ClickHouse later | Immediate BI warehouse | Use a warehouse first only if SIGMA already has dedicated analytics engineering capacity and reporting workloads are clearly beyond Postgres. |
| Custom SIGMA call UI + Twilio Voice | Twilio Flex from day one | Use Flex only if call-center operations outgrow the existing embedded workflow and need skills routing, supervisor tooling, QA, and a dedicated agent desktop. |

## What NOT To Use

| Avoid | Why | Use Instead |
|-------|-----|-------------|
| React/Next.js rewrite of existing operator panels | Duplicates policy, validation, table/query logic, and slows the hardening milestone without solving campaign isolation or reporting trust. | Keep Laravel + Filament + Livewire and move logic out of widgets/resources into tested application/domain services. |
| Full per-campaign databases as the default tenancy model | Heavy brownfield migration cost, harder reporting, harder cross-campaign super-admin support, more ops overhead, and little value if campaign isolation is already modeled in one app. | Shared PostgreSQL with strict `campaign_id` discipline, policy enforcement, queue context propagation, and selective RLS where it materially reduces risk. |
| Production `database` queues/cache/session | Too slow and too fragile for import pipelines, message fanout, realtime dashboards, and Day D pressure. | Redis + Horizon. |
| Passport as the default auth stack | OAuth server complexity is unnecessary for a first-party internal ops product. | Fortify + Sanctum. Add Passport only if SIGMA must become a third-party OAuth provider. |
| Elasticsearch/OpenSearch as the first search investment | More infrastructure and tuning than this product likely needs during hardening. | PostgreSQL full-text via Scout first, Meilisearch for fuzzy/geo search when justified. |
| Octane as the first performance move | Brownfield Livewire apps often contain hidden state assumptions; Octane magnifies them. | Fix query shape, add Redis/Horizon, add projections, profile with Pulse, then consider Octane on proven hot paths. |
| Filament plugin sprawl for core workflow logic | Critical campaign behavior becomes dependent on third-party maintenance quality and upgrade timing. | Keep core queueing, reporting, scoping, and Day D logic in app code. Use plugins only for peripheral capabilities. |
| WhatsApp-only communication strategy | Channel approval, policy, and regional deliverability constraints vary too much for a campaign platform to bet everything on it. | Model a channel-agnostic message ledger with SMS as baseline and WhatsApp as optional. |

## Stack Patterns By Variant

**If the next milestone is strictly hardening the current app:**
- Stay on `Laravel 12 + Filament 4 + Livewire 3`.
- Move production to `PostgreSQL 17 + Redis + Horizon + object storage`.
- Add `Pulse`, `Telescope`, `Pennant`, and `Larastan`.
- Use `Scout` with the database engine first.
- Because this solves real trust and scale issues without turning the roadmap into an upgrade project.

**If the team schedules a post-hardening platform uplift in 2026:**
- Move to `Laravel 13 + Filament 5 + Livewire 4 + PHP 8.3+`.
- Evaluate Laravel 13 AI and semantic search features only after reporting and audit semantics are stable.
- Because Filament 5 is mainly the Livewire 4 transition, and Laravel 13 is a good follow-on platform step once the workflow layer is calm.

**If call-center workflows remain embedded inside SIGMA:**
- Use Twilio Voice, webhook callbacks, queue tables, and Reverb-powered supervisor views.
- Keep agent worklists and dispositions in Filament/Livewire.
- Because the campaign workflow context already lives in SIGMA.

**If call-center operations become a standalone supervisor-heavy function:**
- Introduce Twilio Flex.
- Keep SIGMA as the source of voter context, assignments, permissions, and outcome ingestion.
- Because Flex is justified when contact-center mechanics become more complex than the surrounding campaign workflow.

**If reporting needs stay operational and campaign-scoped:**
- Use Postgres projections, materialized views, read replicas, and export snapshots.
- Because this is the simplest trustworthy architecture for near-term dashboard and export parity.

**If SIGMA starts processing very high event volume across many campaigns:**
- Add ClickHouse for event analytics and pre-aggregated reporting.
- Keep Postgres as the system of record.
- Because ClickHouse is excellent for large-scale analytics, but not the right first transactional store.

## Version Compatibility

| Package A | Compatible With | Notes |
|-----------|-----------------|-------|
| `laravel/framework@12.x` | PHP `8.2` to `8.5` | Released **2025-02-24**. Security fixes until **2027-02-24**. Safe for this milestone. |
| `laravel/framework@13.x` | PHP `8.3` to `8.5` | Released **2026-03-17**. Good target for the next uplift window, not required for current hardening. |
| `filament/filament@4.x` | PHP `8.2+`, Laravel `11.28+`, Tailwind `4.1+` | Current repo already uses Filament 4. Bug fixes until **2027-01-15**, security fixes until **2028-01-15**. |
| `filament/filament@5.x` | Livewire `4.0+` | Current stable Filament line as of **2026-03-25**. Upgrade only with a deliberate Livewire migration. |
| `livewire/livewire@4.x` | Laravel `10+`, PHP `8.1+` | Required by Filament 5. Do not partially migrate. |
| `tailwindcss@4.x` | Vite via `@tailwindcss/vite` | Already matches current Tailwind guidance and the repo's existing setup. |

## Recommendation By Priority

### Add Now

- PostgreSQL 17 + PostGIS as production source of truth.
- Redis + Horizon for queue, cache, session, and broadcast backends.
- Pulse, Telescope, Pennant, and Larastan.
- S3-compatible object storage for evidence and artifacts.
- Scout as the stable search abstraction.

### Add When The Problem Is Real

- Meilisearch for typo-tolerant or geo-aware voter lookup.
- Reverb for genuinely realtime dashboards and Day D counters.
- Twilio Flex for a full contact-center desktop.
- ClickHouse for high-volume analytics beyond Postgres projections.
- Laravel 13 + Filament 5 + Livewire 4 as a planned uplift, not an incidental refactor.

## Sources

- Local repo: [composer.json](/Volumes/NAS(MAC)/Data/Herd/sigma-project/composer.json) and [package.json](/Volumes/NAS(MAC)/Data/Herd/sigma-project/package.json) for the current SIGMA stack [HIGH]
- [Laravel 12 release notes](https://laravel.com/docs/12.x/releases) - Laravel 12 support window and starter-kit direction [HIGH]
- [Laravel 13 release notes](https://laravel.com/docs/13.x/releases) - Laravel 13 release date, PHP requirement, and AI/search additions [HIGH]
- [Laravel Horizon docs](https://laravel.com/docs/12.x/horizon) - queue monitoring and management [HIGH]
- [Laravel Reverb docs](https://laravel.com/docs/12.x/reverb) - realtime WebSocket support [HIGH]
- [Laravel Pulse docs](https://laravel.com/docs/12.x/pulse) - performance and usage monitoring [HIGH]
- [Laravel Sanctum docs](https://laravel.com/docs/12.x/sanctum) - first-party SPA/mobile authentication guidance [HIGH]
- [Laravel Scout docs](https://laravel.com/docs/12.x/scout) - database engine default and external engine options [HIGH]
- [Laravel Octane docs](https://laravel.com/docs/12.x/octane) - Octane and FrankenPHP deployment pattern [HIGH]
- [Laravel Pennant docs](https://laravel.com/docs/12.x/pennant) - feature-flag rollout [HIGH]
- [Laravel Precognition docs](https://laravel.com/docs/12.x/precognition) - live validation capability for operator workflows [HIGH]
- [Filament docs homepage](https://filamentphp.com/docs) and [Filament v5 blueprint](https://filamentphp.com/insights/danharrin-filament-v5-blueprint) - current stable version and Filament 5 / Livewire 4 relationship [HIGH]
- [Filament version support policy](https://filamentphp.com/docs/5.x/introduction/version-support-policy) - bug-fix and security windows for Filament 4 and 5 [HIGH]
- [Filament 4 installation / upgrade guidance](https://filamentphp.com/docs/4.x/introduction/installation) and [Filament 4 upgrade guide](https://filamentphp.com/docs/4.x/upgrade-guide) - Filament 4 requirements [HIGH]
- [Filament 5 upgrade guide](https://filamentphp.com/docs/5.x/upgrade-guide) - Filament 5 requires Livewire 4 [HIGH]
- [Livewire 3 quickstart](https://livewire.laravel.com/docs/3.x/quickstart) - current repo's generation and features [HIGH]
- [Livewire 4 installation](https://livewire.laravel.com/docs/4.x/installation) - Livewire 4 prerequisites and install path [HIGH]
- [Tailwind CSS with Vite](https://tailwindcss.com/docs/installation/using-vite) - Tailwind 4 + Vite guidance [HIGH]
- [PostgreSQL row security docs](https://www.postgresql.org/docs/17/ddl-rowsecurity.html) - selective defense-in-depth for high-risk tables [HIGH]
- [ClickHouse incremental materialized views](https://clickhouse.com/docs/materialized-view/incremental-materialized-view) - real-time analytical projection model [HIGH]
- [Meilisearch Laravel integration](https://www.meilisearch.com/integrations/laravel) - multi-tenancy, geo search, and official Laravel Scout support [HIGH]
- [Twilio Flex docs](https://www.twilio.com/docs/flex) - full contact-center platform scope [MEDIUM]
- [Twilio WhatsApp docs](https://www.twilio.com/docs/whatsapp) - WhatsApp as an optional messaging channel, not a guaranteed default [MEDIUM]
- [Larastan releases](https://github.com/larastan/larastan/releases) and [Larastan repository](https://github.com/larastan/larastan) - current 3.x line and Laravel support [MEDIUM]

---
*Stack research for: political campaign operations platforms*
*Researched: 2026-03-25*

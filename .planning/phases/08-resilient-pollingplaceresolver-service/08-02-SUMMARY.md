---
phase: 08-resilient-pollingplaceresolver-service
plan: 02
subsystem: services
tags: [laravel, pest, resolver, cascade, audit-trail, no-downgrade-guard]

# Dependency graph
requires:
  - phase: 08-01
    provides: LiveSourceAdapter interface, PollingPlaceResolutionResult VO, RegistraduriaService::isReachable()
  - phase: 07-source-flag-schema-audit-trail
    provides: PollingPlaceSource enum and PollingPlaceResolution audit model this plan builds the write path against
provides:
  - PollingPlaceResolver — the single class expressing the full fallback cascade (campaign DB -> national snapshot -> bounded live attempt)
  - PollingPlaceSource::precedence()/outranks() no-downgrade guard (SRC-02)
  - PollingPlaceResolver::persist() — audit-transition-only write path with explicit-override bypass
  - PollingPlaceResolver::resolveAutomated() — headless live->snapshot cascade, bounded and non-blocking (LIVE-03)
affects: [08-03-trait-wiring, 11-scheduled-reconciliation-job]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "No-downgrade guard expressed as PollingPlaceSource::outranks() (lower precedence() number = more trusted), consumed by persist() rather than duplicated per-caller"
    - "Audit row written only on a real source transition; freshness timestamp (polling_place_resolved_at) always refreshed regardless, satisfying D-11/D-12 as two independent conditions in one method"
    - "Bounded automated polling via a fixed backoff array (200/400/800/1200/1600ms) with immediate give-up on waiting_captcha/error, using Illuminate\\Support\\Sleep so tests can Sleep::fake()/assertSleptTimes() instead of wall-clock waiting"

key-files:
  created:
    - tests/Feature/Services/PollingPlaceResolverTest.php
  modified:
    - app/Services/PollingPlaceResolver.php
    - app/Enums/PollingPlaceSource.php

key-decisions:
  - "persist() treats voter===null as a pure pass-through (no persistence, no audit row) — supports the Filament CreateVoter flow where no Voter row exists yet to attach an audit row to."
  - "resolveOrCreatePollingPlace() duplicates HasRegistraduriaPolling::fillPollingPlaceFields()'s firstOrCreate enrichment logic inside the resolver so the headless resolveAutomated() path needs no Livewire form context — Plan 08-03 will refactor the interactive trait to call into the resolver instead of duplicating this logic a third time."

requirements-completed: [CENSO-01, SRC-02, LIVE-01, LIVE-03]

# Metrics
duration: 12min
completed: 2026-07-25
---

# Phase 8 Plan 2: PollingPlaceResolver Core Summary

**The single `PollingPlaceResolver` service expressing SIGMA's entire polling-place fallback cascade — campaign-DB reconstruction, national-snapshot lookup, and a bounded/non-blocking automated live attempt — plus the no-downgrade audit-transition persistence guard, fully covered by 17 Pest tests**

## Performance

- **Duration:** ~12 min
- **Started:** 2026-07-25 (session start)
- **Completed:** 2026-07-25
- **Tasks:** 3
- **Files modified:** 3 (1 created, 2 modified)

## Accomplishments

- `PollingPlaceResolver::resolveFromCampaignCensus()` and `resolveFromNationalSnapshot()` implement the two read-only cascade tiers (CENSO-01), plus `isLiveReachable()`/`startLiveLookup()` adapter-array helpers respecting constructor-array priority order (LIVE-01)
- `PollingPlaceSource::precedence()`/`outranks()` added as the enum's own no-downgrade comparator, consumed by the resolver rather than duplicated per-caller (SRC-02)
- `PollingPlaceResolver::persist()` writes a `PollingPlaceResolution` audit row only on a real source transition, always refreshes `polling_place_resolved_at`, blocks automatic downgrades unless `isExplicitOverride` is true (D-10/D-11/D-12), and passes through untouched when no `Voter` exists yet
- `PollingPlaceResolver::attemptLiveAutomated()` polls a started live session up to 5 times with a fixed backoff, giving up immediately (not after exhausting all polls) on `waiting_captcha` or `error` — LIVE-03's "never blocks" guarantee and D-07's hard captcha give-up
- `PollingPlaceResolver::resolveAutomated()` composes the full headless cascade (live -> national snapshot only, per D-03), persisting through `persist()` with `isExplicitOverride: false`
- 17/17 Pest tests pass; `vendor/bin/pint --dirty --test` reports no violations; the resolver file contains zero references to `CampaignContext` (Pitfall D avoided — the resolver only ever reads its explicit `Voter` argument)

## Task Commits

Each task was committed atomically:

1. **Task 1: Read tiers — campaign-DB reconstruction and national-snapshot resolution** - `e067dab` (feat)
2. **Task 2: No-downgrade guard + audit-transition persistence** - `9533630` (feat)
3. **Task 3: Bounded automated live attempt + full automated cascade** - `9325f28` (feat)

## Files Created/Modified

- `app/Services/PollingPlaceResolver.php` - New service (283 lines): `resolveFromCampaignCensus()`, `resolveFromNationalSnapshot()`, `isLiveReachable()`, `startLiveLookup()`, `persist()`, `attemptLiveAutomated()` (private), `resolveAutomated()`, `resolveOrCreatePollingPlace()` (private)
- `app/Enums/PollingPlaceSource.php` - Added `precedence(): int` (LIVE=0 most trusted … MANUAL=3 least trusted) and `outranks(self $other): bool`
- `tests/Feature/Services/PollingPlaceResolverTest.php` - New: 17 behavioral tests across all three tasks, using anonymous `LiveSourceAdapter` implementations (no Mockery) and `Sleep::fake()`/`assertSleptTimes()`/`assertNeverSlept()` for the bounded-polling assertions

## Decisions Made

- Kept the plan's `<action>` code blocks verbatim — no deviation from the specified `PollingPlaceResolver`/`PollingPlaceSource` implementation was needed; all 17 behavior tests passed against the plan's code as written.
- `persist(null, ...)` pass-through and `resolveOrCreatePollingPlace()`'s trait-mirroring logic were kept exactly as specified in the plan rather than refactored, since Plan 08-03 is explicitly scoped to do that wiring/dedup work against the Filament trait.

## Deviations from Plan

None — plan executed exactly as written. All three tasks' TDD behavior specs (17 total test cases) passed against the plan's own `<action>` code blocks with no bug fixes, missing-functionality additions, or blocking-issue workarounds required.

## Issues Encountered

None.

## User Setup Required

None — no new configuration, environment variables, or external service setup introduced by this plan.

## Next Phase Readiness

- `PollingPlaceResolver` is fully ready for Plan 08-03 to wire into `HasRegistraduriaPolling` (the Filament trait) as the interactive caller, and for Phase 11's future headless reconciliation job to call `resolveAutomated()` directly — no cascade logic needs to be written or duplicated outside this class.
- The no-downgrade guard and audit-transition persistence rules (SRC-02) are proven end to end via `persist()`'s test coverage; Plan 08-03 only needs to decide when to pass `isExplicitOverride: true` (the "Actualizar datos" force-refresh button) vs. `false` (all automatic paths).
- No blockers for 08-03.

---
*Phase: 08-resilient-pollingplaceresolver-service*
*Completed: 2026-07-25*

## Self-Check: PASSED

All 3 claimed files found on disk (`app/Services/PollingPlaceResolver.php`, `app/Enums/PollingPlaceSource.php`, `tests/Feature/Services/PollingPlaceResolverTest.php`). All 3 task commits (`e067dab`, `9533630`, `9325f28`) found in git history.

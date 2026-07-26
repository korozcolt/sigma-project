---
phase: quick
plan: 260726-jao
subsystem: voters
tags: [livewire, volt, filament, eloquent, pest]

requires:
  - phase: 08-resilient-pollingplaceresolver-service
    provides: "PollingPlaceResolver's tiered resolve* methods, PollingPlaceResolutionResult shape, and resolveOrCreatePollingPlace() reused directly by the new permanent-lookup methods"
  - phase: quick/260726-ifp
    provides: "The local-census blur-warning precedent (censusNotFoundWarning, updatedDocumentNumber()) on register-voter.blade.php that this task extends with a higher-priority Registraduría tier"
provides:
  - "Permanent, non-expiring registraduria_lookups table (document_number unique, parsed fields, source, informational nullable campaign_id) replacing the 30-day Cache-store mechanism"
  - "PollingPlaceResolver::resolveFromPermanentLookup() / persistPermanentLookup(), and resolveAutomated() checking the permanent table before any live adapter attempt"
  - "VoterStatus::VERIFIED_REGISTRADURIA — stronger than VERIFIED_CENSUS — wired into the Filament Voters table"
  - "Líder's register-voter form: blur-triggered Registraduría autofill (puesto/mesa/dirección) + green banner + VERIFIED_REGISTRADURIA save status"
  - "Coordinador's create-leader form: new required+unique document_number field, same blur cascade/banners, persisted onto the created leader User"
affects: [voters-admin, leader-register-voter, coordinator-create-leader, scheduled-reconciliation-job]

tech-stack:
  added: []
  patterns:
    - "Permanent lookup table replaces a TTL cache entirely, not alongside it — every consumer (interactive admin modal, líder form, coordinator form, headless reconciliation) checks the same registraduria_lookups table first, and every genuine live success writes back into it via PollingPlaceResolver::persistPermanentLookup(), so savings compound across all four call sites"
    - "Registraduría-table check always precedes the local CensusRecord check, and its banner replaces (not stacks with) the census warning — mirrors the existing VoterValidationService::documentExistsInCensus() blur pattern from quick task 260726-ifp but with an extra, higher-priority tier"
    - "Voter save() always recomputes status from a fresh DB read (RegistraduriaLookup::exists(), documentExistsInCensus()) rather than trusting blur-set component state, so a paste-then-submit flow that skips the blur hook still gets the correct status — same precedent as 260726-ifp"

key-files:
  created:
    - database/migrations/2026_07_26_170000_create_registraduria_lookups_table.php
    - app/Models/RegistraduriaLookup.php
    - database/factories/RegistraduriaLookupFactory.php
    - tests/Feature/Leader/RegisterVoterRegistraduriaLookupTest.php
    - tests/Feature/Coordinator/CreateLeaderRegistraduriaLookupTest.php
  modified:
    - app/Services/PollingPlaceResolver.php
    - tests/Feature/Services/PollingPlaceResolverTest.php
    - app/Enums/VoterStatus.php
    - app/Filament/Resources/Voters/Tables/VotersTable.php
    - tests/Feature/Filament/VoterResourceTest.php
    - app/Filament/Resources/Voters/Concerns/HasRegistraduriaPolling.php
    - tests/Feature/Filament/VoterRegistraduriaRefreshTest.php
    - resources/views/livewire/leader/register-voter.blade.php
    - resources/views/livewire/coordinator/create-leader.blade.php
    - tests/Feature/CreateLeaderOtpTest.php

key-decisions:
  - "registraduria_lookups has no TTL/expiration column by design — permanent, survives cache:clear, unlike the 30-day Cache::put mechanism it fully replaces"
  - "campaign_id on registraduria_lookups is nullable and purely informational/audit — never used to scope reads, matching the prior cache key's cross-campaign global-data precedent (locked in CONTEXT.md)"
  - "resolveFromPermanentLookup() treats every row as PollingPlaceSource::LIVE because every row originated from a genuine live result — same authority level as a fresh live lookup, more authoritative than CensusRecord"
  - "resolveAutomated() checks the permanent table as tier 0, before the live-adapter loop, and persists a fresh live success into it — the headless reconciliation job (Phase 11) now benefits from the same accumulated table as every interactive flow"
  - "register-voter.blade.php and create-leader.blade.php both make the green Registraduría banner mutually exclusive with the amber census warning via @if/@elseif — Registraduría always wins per the locked decision"
  - "Voter save() status priority: VERIFIED_REGISTRADURIA > PENDING_REVIEW (found in local census) > CENSUS_NOT_FOUND — computed fresh from RegistraduriaLookup::exists() and VoterValidationService::documentExistsInCensus() at submit time, never trusting blur-set state alone"
  - "Coordinador's document_number validation mirrors CoordinatorForm.php's only existing precedent for this column on User (required, unique, no digit-count constraint) — no new validation convention invented"
  - "Explicitly did NOT implement User/Voter deduplication or anonymous/placeholder identifier schemes for coordinators — out of scope per CONTEXT.md's deferred section; document_number is the leader's real cédula with no anonymity logic"

requirements-completed: []

duration: ~35min
completed: 2026-07-26
---

# Quick Task 260726-jao: Tabla Permanente de Resultados de Registraduría por Cédula Summary

**Permanent, non-expiring `registraduria_lookups` table replaces the 30-day Registraduría result cache and is now consulted by all four points that can produce or use a live result — the admin Filament flow, the Líder's register-voter form, the Coordinador's create-leader form, and `PollingPlaceResolver::resolveAutomated()`'s headless reconciliation cascade — plus a new `VoterStatus::VERIFIED_REGISTRADURIA` status stronger than `VERIFIED_CENSUS`.**

## Performance

- **Duration:** ~35 min (excluding stale-worktree environment setup: fast-forward merge, `composer install`, `.env` copy)
- **Completed:** 2026-07-26
- **Tasks:** 5
- **Files modified:** 15 (5 created, 10 modified)

## Accomplishments

- New `registraduria_lookups` table (permanent, no TTL, `document_number` unique) + `RegistraduriaLookup` model + factory, with `toRegistraduriaFields()` converting a row back into `RegistraduriaService`'s exact 7-key field shape.
- `PollingPlaceResolver::resolveFromPermanentLookup()` / `persistPermanentLookup()` added; `resolveAutomated()` now checks the permanent table as its first tier (before any live adapter call) and persists every fresh live success into it — the scheduled reconciliation job (Phase 11) now benefits automatically.
- `VoterStatus::VERIFIED_REGISTRADURIA` added with all four required match arms (label/color/icon/description), wired into `VotersTable`'s status column color closure — renders and filters correctly, distinct from and stronger than `VERIFIED_CENSUS`.
- `HasRegistraduriaPolling` fully migrated off `Illuminate\Support\Facades\Cache` — `openRegistraduriaBrowser()`'s Layer 1 now reads `resolveFromPermanentLookup()`, and `handleRegistraduriaResult()` writes via `persistPermanentLookup()`. No `Cache::` reference remains in the trait.
- Líder's `register-voter.blade.php`: blur on `document_number` now checks the permanent Registraduría table first (autofilling `polling_place_id`/`polling_table_number`/`address` and showing a green banner that replaces the amber census warning), falling back to the unchanged local-census check from quick task 260726-ifp. `save()` persists `VERIFIED_REGISTRADURIA` / `PENDING_REVIEW` / `CENSUS_NOT_FOUND` based on a fresh DB check.
- Coordinador's `create-leader.blade.php`: new required + unique `document_number` field (mirroring `CoordinatorForm.php`'s only existing precedent for validating this column on `User`), same blur cascade and banners as the líder form, persisted onto the created leader `User`.
- 20 new Pest tests across the touched suites, plus 2 tests added to `VoterResourceTest.php` and 1 assertion extended in `CreateLeaderOtpTest.php` — all passing. Full targeted regression sweep (`PollingPlaceResolverTest`, `PollingPlaceResolverPriorityTest`, `VoterResourceTest`, `VoterRegistraduriaRefreshTest`, `RegisterVoterRegistraduriaLookupTest`, `RegisterVoterCensusWarningTest`, `CreateLeaderRegistraduriaLookupTest`, `CreateLeaderOtpTest`, `ReconcileFallbackPollingPlacesTest`) passes 114/114 (412 assertions). Broader sweep across `tests/Feature/Filament`, `tests/Feature/Leader`, `tests/Feature/Coordinator`, `tests/Feature/Services`, `tests/Feature/Jobs`, `tests/Feature/VoterTest.php` passes 300/301 (the 1 failure is a pre-existing, previously-documented flaky test unrelated to this task — see Deviations).
- New migration applied to the local dev database (`registraduria_lookups` table now exists, ready for browser verification).

## Task Commits

Each task was committed atomically:

1. **Task 1: Permanent registraduria_lookups table + model + PollingPlaceResolver wiring** - `0a32796` (feat)
2. **Task 2: VoterStatus::VERIFIED_REGISTRADURIA case, wired into the Filament Voters table** - `011d576` (feat)
3. **Task 3: HasRegistraduriaPolling — replace the 30-day cache with the permanent lookup table** - `ed4ef8a` (refactor)
4. **Task 4: Líder register-voter.blade.php — Registraduría blur cascade, autofill, banner, save status** - `2a7df2f` (feat)
5. **Task 5: Coordinador create-leader.blade.php — required document_number field + Registraduría cascade** - `3744164` (feat)

## Files Created/Modified

- `database/migrations/2026_07_26_170000_create_registraduria_lookups_table.php` - New permanent table, no TTL, `document_number` unique, nullable informational `campaign_id`
- `app/Models/RegistraduriaLookup.php` - Model + `toRegistraduriaFields()` shape converter
- `database/factories/RegistraduriaLookupFactory.php` - Factory for tests
- `app/Services/PollingPlaceResolver.php` - New `resolveFromPermanentLookup()`/`persistPermanentLookup()`; `resolveAutomated()` checks the permanent table first and persists fresh live successes
- `tests/Feature/Services/PollingPlaceResolverTest.php` - 6 new tests (Tests 21-26) covering all new/changed behavior
- `app/Enums/VoterStatus.php` - New `VERIFIED_REGISTRADURIA` case, all 4 match arms
- `app/Filament/Resources/Voters/Tables/VotersTable.php` - Status column color closure handles the new case
- `tests/Feature/Filament/VoterResourceTest.php` - 2 new tests: renders + filters `VERIFIED_REGISTRADURIA`
- `app/Filament/Resources/Voters/Concerns/HasRegistraduriaPolling.php` - Cache → permanent table migration in `openRegistraduriaBrowser()` and `handleRegistraduriaResult()`; `Cache` import and `CACHE_TTL_DAYS`/`registraduriaCacheKey()` removed entirely
- `tests/Feature/Filament/VoterRegistraduriaRefreshTest.php` - Fixtures migrated from `Cache::put`/`Cache::get` to `RegistraduriaLookup::factory()`; 1 new test proving a permanent-table hit skips the live modal
- `resources/views/livewire/leader/register-voter.blade.php` - `updatedDocumentNumber()` checks the permanent table first; `registraduriaVerified` property; green/amber mutually-exclusive banners; `save()` computes status fresh
- `tests/Feature/Leader/RegisterVoterRegistraduriaLookupTest.php` - 6 new tests covering all `<behavior>` items
- `resources/views/livewire/coordinator/create-leader.blade.php` - New `document_number` field (required, unique), `updatedDocumentNumber()` cascade, banners, persisted onto the created leader
- `tests/Feature/CreateLeaderOtpTest.php` - Extended the successful-save test to set/assert `document_number`
- `tests/Feature/Coordinator/CreateLeaderRegistraduriaLookupTest.php` - 6 new tests covering all `<behavior>` items

## Decisions Made

See `key-decisions` in frontmatter above — all followed the plan's locked CONTEXT.md decisions exactly (permanent no-TTL table, cross-campaign global data, Registraduría banner replaces census warning, `VERIFIED_REGISTRADURIA` stronger than `VERIFIED_CENSUS`, required+unique `document_number` on the coordinator form). No architectural deviations from the plan.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Stale execution worktree missing vendor/, .env, and 5+ merged phases**
- **Found during:** Pre-Task-1 environment setup
- **Issue:** This worktree (`agent-a2b66af830c2ca5b4`) was checked out at commit `403e0f0` ("Prod test"), a fast-forward ancestor of `main`'s HEAD (`47f2565`), missing `vendor/`, `.env`, and everything through Phase 11 / quick tasks eu3-ifp — same class of issue repeatedly documented in `.planning/STATE.md`'s Blockers/Concerns.
- **Fix:** `git merge --ff-only main`, copied `.env` from the main checkout, ran `composer install --no-interaction`. DB migrations for prior phases were already applied to the shared dev DB.
- **Files modified:** none (environment setup only)
- **Verification:** `php artisan migrate:status` showed all prior migrations already `Ran`; `php artisan test` runnable afterward

---

**2. [Rule 3 - Blocking] Missing Vite build manifest in the freshly fast-forwarded worktree**
- **Found during:** Post-Task-5 broader regression sweep (`tests/Feature/Leader`)
- **Issue:** `public/build/manifest.json` didn't exist in this worktree (`node_modules/` never installed, `npm run build` never run here), causing all 6 `LeaderAppTest.php` tests (full-page HTTP requests through the leader layout) to fail with a Vite manifest exception unrelated to any file this task touched.
- **Fix:** Copied the already-built, gitignored `public/build/` directory from the main checkout — no source files changed, nothing committed (gitignored). Same precedent as quick task 260726-ifp's deviation #2.
- **Files modified:** none (gitignored build artifacts only)
- **Verification:** `php artisan test --filter=LeaderAppTest` — 30/30 pass after the copy

---

**Total deviations:** 2 auto-fixed (both Rule 3 environment/blocking issues, zero code changes). No scope creep — both were necessary to run and verify this task's own test suite in this worktree.
**Impact on plan:** None on shipped behavior — both fixes were execution-environment setup, not code changes.

## Issues Encountered

- **Pre-existing flaky test confirmed unrelated:** `tests/Feature/Filament/UserResourceTest.php > can update user campaigns` failed once during the broader regression sweep with "Component has errors: data.phone", then passed 28/28 when re-run in isolation. This exact flake (~1/3 of full-suite runs) is already documented in `.planning/STATE.md`'s Blockers/Concerns section (originally logged in `04.1 deferred-items.md`), predates this task, and is unrelated to any file this plan touched. Logged in `.planning/quick/260726-jao-tabla-permanente-de-resultados-de-regist/deferred-items.md`; not fixed here per the SCOPE BOUNDARY rule.

## User Setup Required

None - no external service configuration required. No dependency changes. New migration was run against the local dev database as part of finishing this task (`registraduria_lookups` table exists).

## Next Phase Readiness

- Every point in the system that can produce a genuine live Registraduría result now writes into the same permanent table, and every point that needs a result checks it first — the admin Filament modal, the líder's register-voter form, the coordinador's create-leader form, and the headless `ReconcileFallbackPollingPlaces` job (via `resolveAutomated()`) all compound savings into one accumulating store.
- `VoterStatus::VERIFIED_REGISTRADURIA` is immediately usable and filterable in the admin Voters table.
- Explicitly deferred (per CONTEXT.md, not implemented): User/Voter deduplication and anonymous/placeholder identifier schemes for coordinators. `document_number` on the coordinator form is the leader's real cédula, with no anonymity or cross-table counting logic — a future session should pick this up separately if still desired.
- Full targeted verification suite from the plan's `<verification>` block passes 114/114; broader Filament/Leader/Coordinator/Services/Jobs/Voter sweep passes 300/301 (1 pre-existing unrelated flake, documented above).

---
*Quick task: 260726-jao*
*Completed: 2026-07-26*

## Self-Check: PASSED

All 15 created/modified files confirmed present on disk. All 5 task commit hashes
(`0a32796`, `011d576`, `ed4ef8a`, `2a7df2f`, `3744164`) confirmed present in git log.

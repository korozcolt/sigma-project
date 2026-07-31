---
phase: quick
plan: 260731-n0n
subsystem: database
tags: [audit-trail, observers, auth-events, eloquent, laravel]

# Dependency graph
requires: []
provides:
  - "audit_logs table (polymorphic auditable, action, actor, campaign_id, old/new JSON, ip/user-agent)"
  - "AuditLog model + factory"
  - "AuditObserver: generic created/updated/deleted audit trail for User/Campaign/Voter"
  - "AuditAuthActivitySubscriber: login/logout/failed-login audit trail via stock Laravel auth events"
affects: [auth, campaigns, voters, users]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Generic Eloquent observer (AuditObserver) reused across multiple models instead of one observer per model"
    - "Event::subscribe() subscriber class for auth-activity logging instead of per-listener registration"
    - "Audit writes wrapped in try/catch that logs and swallows — never lets an audit failure break the primary write"

key-files:
  created:
    - database/migrations/2026_07_31_120000_create_audit_logs_table.php
    - app/Models/AuditLog.php
    - database/factories/AuditLogFactory.php
    - app/Observers/AuditObserver.php
    - app/Listeners/AuditAuthActivitySubscriber.php
    - tests/Feature/AuditLogTest.php
    - tests/Feature/AuditObserverTest.php
    - tests/Feature/AuditAuthActivitySubscriberTest.php
  modified:
    - app/Providers/AppServiceProvider.php

key-decisions:
  - "Generic AuditObserver stacked alongside the existing UserObserver on User (Laravel supports multiple observers per model) rather than merging audit logic into UserObserver"
  - "campaign_id resolution is per-model: Campaign uses its own id, Voter reads its raw campaign_id attribute, User falls back to CampaignContext::currentCampaignId() (legitimately null when no context)"
  - "Failed-login handler reads only $event->credentials['email'] — the plaintext password is never read or persisted"
  - "No Filament UI for browsing the log — write path only, explicitly deferred"

patterns-established:
  - "Audit-write resilience pattern: try/catch around AuditLog::create() inside both the observer and the auth subscriber, logging via Log::error() and never rethrowing"

requirements-completed: []

# Metrics
duration: ~25min
completed: 2026-07-31
---

# Phase quick: Agregar sistema de auditoría general (audit_logs) Summary

**Native audit trail (audit_logs table + AuditLog model + generic AuditObserver + auth event subscriber) covering create/update/delete on User/Campaign/Voter plus login/logout/failed-login, built entirely with stock Laravel — no new dependency.**

## Performance

- **Duration:** ~25 min
- **Completed:** 2026-07-31T21:45:29Z
- **Tasks:** 3/3
- **Files modified:** 9 (5 created app code, 3 created test files, 1 migration, 1 provider edit — AppServiceProvider counted once)

## Accomplishments
- `audit_logs` migration + `AuditLog` model + factory: polymorphic `auditable`, `action` string, actor `user_id`, `campaign_id`, `old_values`/`new_values` JSON casts, ip/user-agent.
- Generic `AuditObserver` wired onto `User`, `Campaign`, and `Voter` — writes `created`/`updated`/`deleted` audit rows, resolves `campaign_id` per-model, skips no-op updates, and never breaks the primary write on audit-write failure.
- `AuditAuthActivitySubscriber` wired via `Event::subscribe()` — writes `login`/`logout`/`login_failed` audit rows off Laravel's stock `Login`/`Logout`/`Failed` auth events (Fortify's guard already dispatches these natively, no Fortify changes needed). Failed-login rows never contain the submitted password.
- 11 new Pest tests across 3 files, all passing together.

## Task Commits

Each task was committed atomically:

1. **Task 1: audit_logs migration, AuditLog model, and factory** - `1ec3882` (feat)
2. **Task 2: generic AuditObserver for User/Campaign/Voter** - `d3d2529` (feat)
3. **Task 3: login/logout/failed-login audit subscriber** - `4a8e802` (feat)

**Plan metadata:** (this commit, docs: complete plan)

## Files Created/Modified
- `database/migrations/2026_07_31_120000_create_audit_logs_table.php` - audit_logs schema
- `app/Models/AuditLog.php` - AuditLog Eloquent model with auditable/user/campaign relations, array casts
- `database/factories/AuditLogFactory.php` - factory for tests
- `app/Observers/AuditObserver.php` - generic created/updated/deleted audit observer
- `app/Listeners/AuditAuthActivitySubscriber.php` - login/logout/failed-login audit subscriber
- `app/Providers/AppServiceProvider.php` - wires AuditObserver onto User/Campaign/Voter (alongside existing UserObserver), registers AuditAuthActivitySubscriber via Event::subscribe()
- `tests/Feature/AuditLogTest.php` - 2 tests (casting, relations)
- `tests/Feature/AuditObserverTest.php` - 6 tests (create/update/no-op/delete/campaign-own-id/user-fallback)
- `tests/Feature/AuditAuthActivitySubscriberTest.php` - 3 tests (login/logout/failed-login-no-password)

## Decisions Made
- `AuditObserver::resolveCampaignId()` checks `instanceof Campaign` first (own id), then whether the model's raw attributes contain `campaign_id` (Voter), falling back to `CampaignContext::currentCampaignId()` for models with neither (User) — matches the plan's three documented cases exactly.
- `AuditAuthActivitySubscriber` never touches `$event->credentials` beyond the `email` key, guaranteeing no plaintext password is ever written to `audit_logs.new_values`.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Added missing `use function Pest\Laravel\actingAs;` import in AuditObserverTest.php**
- **Found during:** Task 2 verification
- **Issue:** The plan's test file called the global `actingAs()` helper without importing it. This codebase's Pest setup does not auto-import `Pest\Laravel\actingAs` (confirmed via sibling test files like `VoterCensusValidationTest.php`), so the call failed with "Call to undefined function actingAs()".
- **Fix:** Added `use function Pest\Laravel\actingAs;` to the test file, matching the established sibling-file convention.
- **Files modified:** tests/Feature/AuditObserverTest.php
- **Verification:** Test suite re-run, import resolved.
- **Committed in:** d3d2529 (Task 2 commit)

**2. [Rule 1 - Bug] Attached the test actor to the campaign before creating the voter in "creating a voter writes an audit log..." test**
- **Found during:** Task 2 verification
- **Issue:** `Voter` uses the `HasCampaignContext` trait, whose `creating` hook calls `CampaignContext::enforceCampaignId($model)`. When an authenticated non-super_admin actor has no resolvable campaign (not assigned to any campaign, no session context), this throws `OperationalDenialException`. The plan's test called `actingAs($actor)` before `Voter::factory()->create(...)` without first attaching the actor to the campaign, so voter creation threw instead of succeeding.
- **Fix:** Added `$actor->campaigns()->attach($campaign);` before `actingAs($actor)`, matching the existing test-suite convention (e.g. `UserTest.php`, `DashboardWidgetsTest.php`) for giving an actor a resolvable campaign context.
- **Files modified:** tests/Feature/AuditObserverTest.php
- **Verification:** Test passes; `AuditLog` row's `campaign_id` correctly matches `$campaign->id`.
- **Committed in:** d3d2529 (Task 2 commit)

**3. [Worktree provisioning, not a plan deviation] Stale worktree fast-forwarded and re-provisioned**
- **Found during:** Session start
- **Issue:** This worktree (`agent-ae025c2eea1cb506e`) was checked out one commit behind main (missing the plan-creation commit `17f2339`) and was completely unprovisioned — no `vendor/`, `.env`, `node_modules/`, or `public/build/`. Same class of issue documented repeatedly in STATE.md Blockers/Concerns for prior quick tasks/phases.
- **Fix:** `git merge --ff-only main`, copied `.env` from the main checkout, ran `composer install`, `npm install`, `npm run build`. Discarded the resulting `package-lock.json` name-field diff via `git checkout --` (not committed).
- **Files modified:** none committed (environment-only; `package-lock.json` diff discarded)
- **Verification:** `php artisan migrate:status` confirmed schema in sync; `composer install`/`npm run build` completed cleanly.

---

**Total deviations:** 3 (2 auto-fixed test bugs, 1 environment provisioning step)
**Impact on plan:** Both test fixes were necessary for the plan's own tests to pass as specified; no scope creep, no change to application code beyond what the plan specified. Provisioning was required to run tests at all, not a code change.

## Issues Encountered
- A broad regression sweep (`php artisan test --testsuite=Feature --filter="User|Campaign|Voter|Login|Logout|Auth"`) surfaced 15 failures unrelated to this task. All 15 were confirmed pre-existing: 14 pass cleanly in isolation (the already-documented `CampaignContext` static-override test-pollution issue, logged multiple times in STATE.md), and the 15th (`UserResourceTest > can update user campaigns`) is a separately documented pre-existing flake (~1/3 of full-suite runs). None of the 15 failing files were touched by this task. Logged with full detail in `.planning/quick/260731-n0n-agregar-sistema-de-auditoria-general-aud/deferred-items.md`; no fix attempted per the scope-boundary rule.

## User Setup Required

None - no external service configuration required. No new Composer dependency, no new `.env` variables, no Filament UI added (explicitly deferred per plan scope).

## Next Phase Readiness
- `audit_logs` now captures a traceable trail for User/Campaign/Voter mutations and all login/logout/failed-login activity.
- Explicitly deferred follow-up (not part of this task): a Filament UI/resource for browsing/searching `audit_logs`.

---
*Phase: quick*
*Completed: 2026-07-31*

## Self-Check: PASSED

All 9 created/modified files verified present on disk; all 3 task commits (`1ec3882`, `d3d2529`, `4a8e802`) verified present in `git log`.

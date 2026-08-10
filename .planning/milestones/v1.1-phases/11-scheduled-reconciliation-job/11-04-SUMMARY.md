---
phase: 11-scheduled-reconciliation-job
plan: 04
subsystem: jobs
tags: [laravel-scheduler, queue, reconciliation, registraduria, campaign-safety]

# Dependency graph
requires:
  - phase: 11-03
    provides: "voters.reconciliation_attempts / reconciliation_exhausted_at columns wired into Voter's $fillable/casts()"
provides:
  - "ReconcileFallbackPollingPlaces ShouldQueue job: bounded (50/run) eligibility query, isLiveReachable() circuit breaker, resolveAutomated() loop, attempt/exhaustion bookkeeping"
  - "census:reconcile-live thin Artisan command (dispatchSync)"
  - "Hourly Schedule::command('census:reconcile-live')->withoutOverlapping(10) entry in routes/console.php"
affects: []

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Method-injected service dependencies in job handle() when the service holds non-serializable state (iterable adapters), mirroring FinalizeElectionEvent's constructor-stays-primitive convention"
    - "dispatchSync() from a thin scheduled command so withoutOverlapping()'s lock duration actually bounds real processing time instead of an instant async dispatch"

key-files:
  created:
    - app/Jobs/ReconcileFallbackPollingPlaces.php
    - app/Console/Commands/ReconcileLivePollingPlaces.php
    - tests/Feature/Jobs/ReconcileFallbackPollingPlacesTest.php
  modified:
    - routes/console.php

key-decisions:
  - "SNAPSHOT-sourced resolveAutomated() fallthrough is treated as a FAILED attempt (reconciliation_attempts increments), never as success, per D-08's literal wording — this is the phase's single most consequential branch and is directly regression-tested"
  - "withoutOverlapping(10) is 10 MINUTES (verified against vendor/laravel/framework's signature), matching D-10 exactly; the plan explicitly warns against the equivalent-looking but wrong withoutOverlapping(600)"
  - "No CampaignContext/campaign_id filtering added to the job's eligibility query — RECON-02 is satisfied because CampaignContextScope no-ops without an authenticated user (confirmed by reading CampaignContext::currentCampaignId(), which returns null when Auth::user() is null); adding defensive scoping would have been unnecessary code"

patterns-established: []

requirements-completed: [RECON-01, RECON-02, RECON-03, RECON-04, RECON-05, RECON-06]

# Metrics
duration: 18min
completed: 2026-07-26
---

# Phase 11 Plan 04: Scheduled Reconciliation Job Logic Summary

**Built the `ReconcileFallbackPollingPlaces` job (bounded, circuit-breaker-gated, campaign-safe), wired it to a new hourly `census:reconcile-live` schedule entry with a correctly-unitted 10-minute lock, and covered all of RECON-01 through RECON-06 with 9 passing Pest tests — closing out Phase 11 and the whole v1.1 milestone's reconciliation requirement.**

## Performance

- **Duration:** ~18 min
- **Tasks:** 3
- **Files modified:** 3 created (job, command, test), 1 modified (routes/console.php)

## Accomplishments

- `ReconcileFallbackPollingPlaces` (`ShouldQueue`, method-injected `PollingPlaceResolver`) queries fallback-sourced, non-exhausted voters (oldest `polling_place_resolved_at` first), capped at 50/run, and skips the entire run with a log line when `isLiveReachable()` is false (RECON-04's circuit breaker, checked before the query executes).
- For each eligible voter it calls `resolveAutomated()` once: a genuine `LIVE`-sourced result resets `reconciliation_attempts` to 0 and clears `reconciliation_exhausted_at` (RECON-01/03); any other outcome — including a `SNAPSHOT`-sourced cascade fallthrough — increments `reconciliation_attempts` and, at 5, sets `reconciliation_exhausted_at = now()` (RECON-05).
- `census:reconcile-live` is a thin command that calls `ReconcileFallbackPollingPlaces::dispatchSync()` (not `dispatch()`) so the schedule's lock duration actually bounds real processing time.
- `routes/console.php` now schedules `census:reconcile-live` hourly with `withoutOverlapping(10)` — verified as 10 minutes, not 10 seconds or 600 minutes (RECON-06).
- 9 Pest tests cover: circuit breaker skip, 50/run cap (51 voters -> exactly 50 touched), live upgrade + audit row (`pollingPlaceResolutions()->count() === 1`), the SNAPSHOT-fallthrough-is-a-failure branch, 5-strike exhaustion, exhausted-voter skip, cross-campaign processing with no authenticated user (`auth()->check()` false), the schedule's lock-unit correctness (string match against `routes/console.php`), and command-to-job dispatch wiring via `Bus::fake()`.
- Confirmed no regressions: `VoterTest` (35 passing), `RegistraduriaServiceParserTest`/`RegistraduriaServiceReachabilityTest` (8 passing), `PollingPlaceResolverTest` (17 passing) all still pass.

## Task Commits

Each task was committed atomically:

1. **Task 1: ReconcileFallbackPollingPlaces job (query, circuit breaker, attempt/exhaustion bookkeeping)** - `c325fc0` (feat)
2. **Task 2: census:reconcile-live command + hourly schedule entry (D-05, D-10)** - `7b7bd0a` (feat)
3. **Task 3: Pest coverage for RECON-01 through RECON-06** - `5253e06` (test)

## Files Created/Modified

- `app/Jobs/ReconcileFallbackPollingPlaces.php` - bounded, circuit-breaker-gated, campaign-safe reconciliation job with attempt/exhaustion bookkeeping
- `app/Console/Commands/ReconcileLivePollingPlaces.php` - thin `census:reconcile-live` command, synchronous dispatch
- `routes/console.php` - added `Schedule::command('census:reconcile-live')->hourly()->withoutOverlapping(10)`
- `tests/Feature/Jobs/ReconcileFallbackPollingPlacesTest.php` - 9 Pest tests covering RECON-01 through RECON-06

## Decisions Made

- **No ambient campaign scoping added to the job's query.** `CampaignContextScope` (the global scope every `Voter` query goes through via `HasCampaignContext`) resolves `CampaignContext::currentCampaignId()`, which returns `null` whenever `Auth::user()` is null — exactly the reconciliation job's unattended, headless execution context. Confirmed by reading the scope and service directly and by the cross-campaign regression test (`auth()->check()` asserted false, both campaigns' voters updated). Adding defensive `campaign_id` filtering would have been redundant, unneeded code.
- **`dispatchSync()` over `dispatch()`** in the command — deliberate per the plan's D-10 rationale: if the job were pushed to an async queue, the command would return instantly and the 10-minute `withoutOverlapping()` lock would no longer bound the real processing duration.
- **RECON-01 through RECON-06 are now all marked complete** in `REQUIREMENTS.md` — unlike Plans 11-01/11-02/11-03 (which were explicitly deferred prerequisites), this plan is the one that makes every one of those requirements' literal claims true: the scheduled job exists, runs hourly, resolves each voter's own campaign, writes an audit row via the existing `persist()` no-downgrade path, is bounded/circuit-broken, reaches a terminal exhaustion state, and cannot freeze indefinitely.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed a self-contradictory test fixture for the 5th-consecutive-failure exhaustion test**
- **Found during:** Task 3 (Pest coverage), first test run
- **Issue:** The plan's literal test body for "sets `reconciliation_exhausted_at` on the 5th consecutive failed attempt" bound `unreachableAdapter()` (an adapter whose `isReachable()` returns `false`). Because Task 1's job correctly implements the RECON-04 circuit breaker (`isLiveReachable()` checked before the query/loop even runs), binding an unreachable adapter causes the entire run to be skipped — the voter's `reconciliation_attempts` never gets touched, so the test's expectation of `4 -> 5` failed (`Failed asserting that 4 is identical to 5`). The test as literally specified could never pass alongside a correctly-built job; the plan's own Task 1 `<behavior>` block distinguishes "resolver unreachable -> skip run" from "voter's `resolveAutomated()` call fails -> increment attempts" as two separate scenarios, and this test needed the second one.
- **Fix:** Replaced `unreachableAdapter()` in this one test with an inline reachable adapter (`isReachable(): true`) that always returns `['status' => 'done', 'data' => []]` — an empty-data "done" result is falsy in PHP's `if ($fields = ...)` check inside `PollingPlaceResolver::resolveAutomated()`, so it correctly falls through to the (non-matching) snapshot tier and returns `null`, giving a genuine reachable-but-failed attempt without tripping the circuit breaker.
- **Files modified:** `tests/Feature/Jobs/ReconcileFallbackPollingPlacesTest.php`
- **Commit:** `5253e06` (test file was written and fixed in the same commit, prior to committing)

## Issues Encountered

None beyond the auto-fixed test fixture above.

## User Setup Required

None. The schedule entry runs automatically once the Laravel scheduler (`schedule:run`) is active in the deployment's cron/supervisor — no additional manual step needed for this plan specifically.

## Next Phase Readiness

- Phase 11 (Scheduled Reconciliation Job) is complete: all 4 plans done, all 6 requirements (RECON-01 through RECON-06) satisfied end-to-end and test-covered.
- This closes the v1.1 milestone's final Active requirement ("Voters resolved via local snapshot are automatically re-verified against the live source once it's reachable, via a scheduled job").

---
*Phase: 11-scheduled-reconciliation-job*
*Completed: 2026-07-26*

## Self-Check: PASSED

All claimed files exist on disk (`app/Jobs/ReconcileFallbackPollingPlaces.php`, `app/Console/Commands/ReconcileLivePollingPlaces.php`, `tests/Feature/Jobs/ReconcileFallbackPollingPlacesTest.php`, `routes/console.php` modified) and all three task commits (`c325fc0`, `7b7bd0a`, `5253e06`) are present in git history.

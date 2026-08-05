---
phase: quick/260804-um4
plan: 01
subsystem: infra
tags: [php, syntax-compat, jobs, services]

requires: []
provides:
  - "3 production files (DispatchCensusRevalidation, ReconcileFallbackPollingPlaces, PollingPlaceResolver) parse correctly under PHP 8.2, fixing a live ParseError that broke the 'Revalidar apoyos de un líder' button"
affects: [jobs, polling-place-resolution, census-reconciliation]

tech-stack:
  added: []
  patterns: []

key-files:
  created: []
  modified:
    - app/Jobs/DispatchCensusRevalidation.php
    - app/Jobs/ReconcileFallbackPollingPlaces.php
    - app/Services/PollingPlaceResolver.php

key-decisions:
  - "No behavior change — only the PHP 8.3-only `int` type keyword was removed from 6 `private const` declarations; values, visibility, and all self:: usages left untouched."

patterns-established: []

requirements-completed: [FIX-CRITICO-PHP82-TYPED-CONSTANTS]

duration: 8min
completed: 2026-08-05
---

# Quick Task 260804-um4: Fix Critico Constantes de Clase Tipadas Summary

**Removed PHP 8.3-only typed class constant syntax (`private const int NAME`) from 3 production files so they parse correctly under production's actual PHP 8.2.27 runtime**

## Performance

- **Duration:** 8 min
- **Started:** 2026-08-05T02:57:00Z (approx.)
- **Completed:** 2026-08-05T03:05:02Z
- **Tasks:** 2
- **Files modified:** 3

## Accomplishments
- Removed the `int` type keyword from all 6 typed `private const` declarations across `DispatchCensusRevalidation.php`, `ReconcileFallbackPollingPlaces.php`, and `PollingPlaceResolver.php` — this syntax is PHP 8.3+ only and production runs PHP 8.2.27 (matching `composer.json`'s `"php": "^8.2"`), so the affected job class could not even autoload, silently breaking the "Revalidar apoyos de un líder" button.
- Confirmed `php -l` reports no syntax errors on all 3 files, and `grep -n "const int"` across the 3 files returns zero matches.
- Ran the full existing Pest coverage for all 4 affected test files (61 tests, 168 assertions) — all pass unmodified, confirming zero behavior change.

## Task Commits

Each task was committed atomically:

1. **Task 1: Remove PHP 8.3 typed-constant syntax from all 3 files (6 occurrences)** - `6230718` (fix)
2. **Task 2: Run existing Pest coverage for all 4 affected test files** - no commit (verification-only task; no files modified, all tests passed as-is)

**Plan metadata:** committed alongside this SUMMARY

_Note: Task 2 required no code/test changes — all 61 tests in the 4 affected files passed without modification, confirming the fix is behavior-neutral._

## Files Created/Modified
- `app/Jobs/DispatchCensusRevalidation.php` - Removed `int` type from `MAX_VOTERS_PER_RUN` and `MAX_ATTEMPTS_BEFORE_EXHAUSTION` const declarations
- `app/Jobs/ReconcileFallbackPollingPlaces.php` - Removed `int` type from `MAX_VOTERS_PER_RUN` and `MAX_ATTEMPTS_BEFORE_EXHAUSTION` const declarations
- `app/Services/PollingPlaceResolver.php` - Removed `int` type from `LIVE_POLL_ATTEMPTS` and `LIVE_POLL_INTERVAL_MS` const declarations

## Decisions Made
- No behavior change — only the type keyword was removed; constant values, visibility, and every `self::CONST_NAME` usage site were left completely untouched, per the plan's explicit constraint.

## Deviations from Plan

None - plan executed exactly as written. All 6 edits matched the plan's exact before/after specification; `git diff` confirmed only the ` int` substring was removed from each of the 6 lines, no other line changed.

## Issues Encountered

Minor tooling note (not a deviation from the plan): the first commit attempt used a message containing literal backticks around the word `int`, which the shell interpreted as command substitution (`` `int` `` → ran `int` as a command, silently dropping the word from the commit message body). Caught immediately via `git log`, and fixed with `git commit --amend -F -` using a heredoc to avoid shell interpretation. Final commit message (`6230718`) is accurate; no code was affected.

## User Setup Required

None - no external service configuration required. This is a pure code fix; once deployed to production (sigma-betha), the "Revalidar apoyos de un líder" button will work because `DispatchCensusRevalidation` will autoload without a `ParseError`.

## Next Phase Readiness

Fix is complete, committed, and test-verified locally. Per project precedent (Blockers/Concerns in STATE.md), a real-browser verification in production after deployment is recommended but not required to close this quick task, since the root cause (a PHP version mismatch causing a hard autoload ParseError) is unambiguous and fully covered by `php -l` + the existing Pest suite. No blockers for closing this task.

---
*Phase: quick/260804-um4*
*Completed: 2026-08-05*

## Self-Check: PASSED

- FOUND: app/Jobs/DispatchCensusRevalidation.php
- FOUND: app/Jobs/ReconcileFallbackPollingPlaces.php
- FOUND: app/Services/PollingPlaceResolver.php
- FOUND: .planning/quick/260804-um4-fix-critico-constantes-de-clase-tipadas-/260804-um4-SUMMARY.md
- FOUND: commit 6230718

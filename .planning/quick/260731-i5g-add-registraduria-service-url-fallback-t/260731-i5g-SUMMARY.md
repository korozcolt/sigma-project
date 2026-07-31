---
phase: quick
plan: 260731-i5g
subsystem: config
tags: [laravel, config, env-fallback, registraduria, consulta-censo]

requires: []
provides:
  - "consulta_censo.url now falls back through REGISTRADURIA_SERVICE_URL before the hardcoded localhost default, matching infovotantes.url"
affects: [registraduria, consulta-censo, live-source-adapters]

tech-stack:
  added: []
  patterns:
    - "3-level env() fallback chain for per-adapter service URLs: OWN_VAR > REGISTRADURIA_SERVICE_URL > hardcoded localhost default"

key-files:
  created: []
  modified:
    - config/services.php
    - tests/Feature/Services/ConsultaCensoServiceTest.php

key-decisions:
  - "No .env.example change needed — CONSULTA_CENSO_SERVICE_URL's existing plain KEY=value line already mirrors INFOVOTANTES_SERVICE_URL's documentation convention (i.e. no comment) exactly, confirmed by direct inspection."

patterns-established: []

requirements-completed: []

duration: 10min
completed: 2026-07-31
---

# Quick Task 260731-i5g: Nest consulta_censo.url fallback through REGISTRADURIA_SERVICE_URL Summary

**config/services.php's consulta_censo.url now resolves through a 3-level env() fallback chain (own var > REGISTRADURIA_SERVICE_URL > localhost default), identical in shape to the pre-existing infovotantes.url pattern, closing a production gap where ConsultaCensoService silently defaulted to the wrong Docker-network hostname.**

## Performance

- **Duration:** 10 min
- **Started:** 2026-07-31T18:02:00Z (approx)
- **Completed:** 2026-07-31T18:12:31Z
- **Tasks:** 1
- **Files modified:** 2

## Accomplishments
- `config/services.php`'s `consulta_censo.url` default changed from a flat `env('CONSULTA_CENSO_SERVICE_URL', 'http://localhost:5757')` to the nested `env('CONSULTA_CENSO_SERVICE_URL', env('REGISTRADURIA_SERVICE_URL', 'http://localhost:5757'))` — byte-for-byte the same shape as the existing `infovotantes.url` line.
- Added a new Pest test to `tests/Feature/Services/ConsultaCensoServiceTest.php` that proves the real 3-level fallback order by re-`require`-ing `config/services.php`'s actual source after manipulating process env vars via `putenv()`/`$_ENV`/`$_SERVER` (not a static/regex check of file text), covering all three tiers of the chain (own var wins, middle-tier fallback, final hardcoded default) plus proper env restoration in a `finally` block.
- Confirmed `.env.example` needs zero changes — its `CONSULTA_CENSO_SERVICE_URL=http://localhost:5757` line already mirrors `INFOVOTANTES_SERVICE_URL`'s plain-line documentation convention exactly.

## Task Commits

1. **Task 1: Nest consulta_censo.url's fallback through REGISTRADURIA_SERVICE_URL + prove the chain with a real env-re-evaluation test** - `4982d61` (fix)

_Note: single-commit task (config change + test in the same commit, per plan's file grouping); no separate TDD red/green split was applied since this is a config default change, not new application behavior — the test directly exercises the real config evaluation._

## Files Created/Modified
- `config/services.php` - `consulta_censo.url`'s default now nests through `REGISTRADURIA_SERVICE_URL` before falling to `http://localhost:5757`; `registraduria.url`, `infovotantes.url`, `consulta_censo.live_enabled`, and `consulta_censo.probe_url` all untouched (confirmed via `git diff` showing only the single intended line changed).
- `tests/Feature/Services/ConsultaCensoServiceTest.php` - new 8th test proving the real fallback-chain evaluation order via fresh `require` of the config source with manipulated process env vars.

## Decisions Made
- No `.env.example` change needed — confirmed by direct inspection that `INFOVOTANTES_SERVICE_URL` (the established precedent for this exact pattern) has no distinguishing comment, so `CONSULTA_CENSO_SERVICE_URL`'s existing plain line already mirrors it correctly as-is.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

**Worktree staleness (pre-existing, documented class of issue in STATE.md Blockers/Concerns):** This task's worktree (`agent-a7437305116510a0a`) was stale at session start — missing `app/Services/ConsultaCensoService.php`, `tests/Feature/Services/ConsultaCensoServiceTest.php`, `vendor/`, and `.env` entirely (checked out several commits behind the main checkout, which was 17 commits ahead of `origin/main` including this very plan's own creation commit). Resolved using the established workaround: confirmed fast-forward ancestry between the worktree's HEAD and the main checkout's HEAD (via a temporary local `mainrepo` remote pointing at the main checkout path), ran `git merge --ff-only`, copied `.env` from the main checkout, and ran `composer install --no-interaction`. No repo-content changes resulted from this remediation beyond the merge itself; the plan's actual code changes were then made cleanly in the now-current worktree.

## User Setup Required

None - no external service configuration required. This is a pure config-default change; production's `.env` already sets `REGISTRADURIA_SERVICE_URL` and requires no update to benefit from this fix.

## Next Phase Readiness
`ConsultaCensoService` will now resolve to the correct Docker-network hostname (`REGISTRADURIA_SERVICE_URL`) in any environment (including production) that never explicitly sets `CONSULTA_CENSO_SERVICE_URL`, closing the gap noted in STATE.md. No blockers for future work.

---
*Phase: quick*
*Completed: 2026-07-31*

## Self-Check: PASSED

- FOUND: config/services.php
- FOUND: tests/Feature/Services/ConsultaCensoServiceTest.php
- FOUND: 260731-i5g-SUMMARY.md
- FOUND: commit 4982d61

---
phase: quick-260730-ofo
plan: 01
subsystem: ui
tags: [filament, hablame, 2captcha, topbar-widget, scheduled-job, super-admin]

requires: []
provides:
  - "two_captcha_balance_snapshots table + TwoCaptchaBalanceSnapshot model/factory"
  - "TwoCaptchaService::getBalance() — never-throws 2captcha USD balance client"
  - "balances:snapshot-2captcha hourly scheduled command"
  - "TwoCaptchaDailyCostService (+ DailyCaptchaCost DTO + DailyCaptchaCostStatus enum) — Bogota-bucketed daily average cost derivation"
  - "SaldoColorResolver — named-threshold badge color resolution"
  - "saldos-badge.blade.php topbar widget, super_admin-only, wired via a second TOPBAR_END renderHook"
affects: [admin-panel-topbar, scheduled-jobs, campaign-config]

tech-stack:
  added: []
  patterns:
    - "External balance/cost widgets read persisted snapshots + 1h cache in the render path, never call the live API synchronously"
    - "Day-bucketing in America/Bogota done by converting the Bogota day boundary to UTC for the query range, not MySQL CONVERT_TZ"

key-files:
  created:
    - database/migrations/2026_07_30_200000_create_two_captcha_balance_snapshots_table.php
    - app/Models/TwoCaptchaBalanceSnapshot.php
    - database/factories/TwoCaptchaBalanceSnapshotFactory.php
    - app/Services/TwoCaptchaService.php
    - app/Console/Commands/SnapshotTwoCaptchaBalance.php
    - app/Enums/DailyCaptchaCostStatus.php
    - app/Services/DailyCaptchaCost.php
    - app/Services/TwoCaptchaDailyCostService.php
    - app/Services/SaldoColorResolver.php
    - resources/views/filament/components/saldos-badge.blade.php
    - tests/Feature/Services/TwoCaptchaServiceTest.php
    - tests/Feature/Console/SnapshotTwoCaptchaBalanceTest.php
    - tests/Feature/Services/TwoCaptchaDailyCostServiceTest.php
    - tests/Feature/Filament/SaldosBadgeTest.php
  modified:
    - config/services.php
    - .env.example
    - routes/console.php
    - app/Providers/Filament/AdminPanelProvider.php

key-decisions:
  - "TWO_CAPTCHA_KEY reused verbatim from existing .env (no new env var); config/services.php twocaptcha block reads it"
  - "Hablame COP thresholds marked explicitly provisional (named constants + // TODO comment) pending user confirmation; 2captcha USD thresholds (green>$5, yellow $1-5, red<$1) are the approved values"
  - "Daily cost bucketing converts the America/Bogota day boundary to UTC for the query range rather than relying on MySQL timezone tables"
  - "No snapshot row written when getBalance() returns null, to avoid corrupting the balance-diff-based daily average"

patterns-established:
  - "Topbar widgets gated to super_admin replicate campaign-context-switcher.blade.php's exact `if (! CampaignContext::isSuperAdmin()) { return; }` guard and coexist via a second renderHook on the same PanelsRenderHook::TOPBAR_END slot"

requirements-completed: [QUICK-260730-ofo]

duration: 20min
completed: 2026-07-30
---

# Quick Task 260730-ofo: Cuadrito de saldos (Hablame + 2captcha) Summary

**Super-admin-only topbar dropdown showing Hablame (COP) and 2captcha (USD) balances plus a 7-day daily-average 2captcha cost, backed by an hourly balance-snapshot job and zero synchronous external API calls in the render path.**

## Performance

- **Duration:** ~20 min
- **Completed:** 2026-07-30
- **Tasks:** 5/5 completed
- **Files modified:** 17 (13 created, 4 modified)

## Accomplishments

- `two_captcha_balance_snapshots` table + model/factory, no campaign scoping (account-level data)
- `TwoCaptchaService::getBalance()` — degrades to `null` on missing key, API error, or timeout; never throws
- `balances:snapshot-2captcha` hourly scheduled command, skips writing a row when the balance is unavailable
- `TwoCaptchaDailyCostService` (enum + DTO + service) computing a Bogota-bucketed daily average 2captcha cost, correctly handling cold-start, recharge-detected, and zero-lookup-count edge cases
- `SaldoColorResolver` + `saldos-badge.blade.php` — an Alpine dropdown in the admin topbar, gated to `super_admin`, reading the latest snapshot row and a 1h-cached Hablame balance (never calling either API synchronously in the render path)

## Task Commits

Each task was committed atomically:

1. **Task 1: Cimientos — config, .env.example, migración, modelo, factory** - `18da4d2` (feat)
2. **Task 2: TwoCaptchaService::getBalance() con degradación grácil** - `6b80ce3` (test, RED) + `a81a03f` (feat, GREEN)
3. **Task 3: Comando de snapshot horario + entrada en el scheduler** - `9ceb863` (feat, includes test)
4. **Task 4: Cálculo del costo promedio diario (enum + DTO + servicio)** - `548976f` (test, RED) + `87751ee` (feat, GREEN)
5. **Task 5: Vista Blade del cuadrito + resolutor de colores + cableado** - `1432293` (feat, includes test)

## Files Created/Modified

- `config/services.php` - new `twocaptcha` block reading existing `TWO_CAPTCHA_KEY`
- `.env.example` - `TWO_CAPTCHA_KEY=` placeholder added
- `database/migrations/2026_07_30_200000_create_two_captcha_balance_snapshots_table.php` - balance snapshots schema
- `app/Models/TwoCaptchaBalanceSnapshot.php` / `database/factories/TwoCaptchaBalanceSnapshotFactory.php` - model + factory
- `app/Services/TwoCaptchaService.php` - never-throwing `getBalance(): ?float`
- `app/Console/Commands/SnapshotTwoCaptchaBalance.php` / `routes/console.php` - hourly snapshot job
- `app/Enums/DailyCaptchaCostStatus.php`, `app/Services/DailyCaptchaCost.php`, `app/Services/TwoCaptchaDailyCostService.php` - daily average cost derivation
- `app/Services/SaldoColorResolver.php` - badge color thresholds
- `resources/views/filament/components/saldos-badge.blade.php` - the topbar widget itself
- `app/Providers/Filament/AdminPanelProvider.php` - second coexisting `TOPBAR_END` renderHook
- 4 new Pest test files covering the client, command, daily-cost service, and gating

## Decisions Made

- Reused `TWO_CAPTCHA_KEY` verbatim (no duplicate env var) per explicit task constraint.
- Hablame COP thresholds (green > 500.000, yellow 100.000–500.000, red < 100.000) are marked provisional in code via named constants + `// TODO ajustar con el usuario` comment, since no real recharge cadence was confirmed with the client. 2captcha USD thresholds (green > $5, yellow $1–5, red < $1) are the approved values.
- Daily cost bucketing converts the America/Bogota day boundary to UTC and queries the UTC range directly, avoiding any dependency on MySQL timezone conversion functions/tables.
- The snapshot command intentionally writes nothing when `getBalance()` returns `null`, so a bad/expired key never corrupts the balance-diff-based daily average with a garbage snapshot.

## Deviations from Plan

None - plan executed exactly as written. Both `tdd="true"` tasks (2 and 4) had their tests verified RED (via `BindingResolutionException` for missing classes, and — for Task 4 — by temporarily relocating the enum/DTO/service files) before the implementation was added and confirmed GREEN.

## Issues Encountered

- **Worktree staleness** (worktree `agent-af20f91f4b65174c1`, same class of issue documented repeatedly in STATE.md's Blockers/Concerns): the worktree was at the correct HEAD but missing `vendor/`, `.env`, `node_modules/`, `public/build/`, and this task's own PLAN/RESEARCH files (untracked-only in the main checkout). Resolved with the established workaround: copied `.env` and the plan directory from the main checkout, then `composer install` + `npm install && npm run build`. The resulting cosmetic `package-lock.json` name-field diff was discarded via `git checkout --`.
- Full-suite run (`php artisan test --testsuite=Feature`) showed 13 failures in `TopCoordinatorsTableTest`, `TopPollingPlacesTableTest`, and `VoterResourceTest` — all confirmed to pass cleanly in isolation on re-run, matching the already-documented `CampaignContext` static-override test-pollution issue in STATE.md's Blockers/Concerns section. Not a regression introduced by this task; none of the touched files (`config/services.php`, `routes/console.php`, `AdminPanelProvider.php`) are referenced by the failing tests' assertions.

## User Setup Required

None - no external service configuration required. `TWO_CAPTCHA_KEY` already exists in the target `.env` files (local and production); the `balances:snapshot-2captcha` schedule entry starts populating `two_captcha_balance_snapshots` automatically once Laravel's scheduler runs (cron already configured for this project's other hourly jobs).

## Next Phase Readiness

- Feature is complete and covered by 14 passing Pest tests (client, command, daily-cost service incl. day-boundary case, and gating).
- **Not yet browser-verified** — per the user's standing preference (browser-verify before prod), the dropdown's render, click-to-open behavior, and badge colors should be checked in a real browser session before deploying, especially since no automated test can currently populate more than one snapshot row across real hours of scheduler runtime; the "last 7 days" list will show mostly "—" (no data) until the hourly job has accumulated enough history in a real environment.
- Nothing from this task has been deployed to sigma-betha yet.

---
*Task: 260730-ofo*
*Completed: 2026-07-30*

## Self-Check: PASSED

All 14 created files verified present on disk; all 7 task commits (18da4d2, 6b80ce3, a81a03f, 9ceb863, 548976f, 87751ee, 1432293) verified present in git log.

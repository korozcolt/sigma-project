---
phase: quick
plan: 260731-odu
subsystem: ui
tags: [filament, infolist, key-value-entry, navigation, audit-log]

# Dependency graph
requires:
  - phase: quick-260731-o5i
    provides: ViewAuditLog's TextEntry+->state()+json_encode() workaround for the array-as-list formatStateUsing() bug
provides:
  - ViewAuditLog old_values/new_values rendered as native Filament KeyValueEntry tables (readable key/value rows, not raw JSON)
  - AdminPanelProvider's sixth 'Sistema' navigation group, positioned last after 'Configuración'
affects: [audit-log-ui, admin-panel-navigation]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Filament KeyValueEntry for array-cast model attributes — no ->state() closure needed, avoids TextEntry's per-array-element formatStateUsing() iteration bug entirely (KeyValueEntry never calls formatStateUsing())"

key-files:
  created:
    - tests/Feature/Filament/AdminPanelProviderTest.php
  modified:
    - app/Filament/Resources/AuditLogs/Pages/ViewAuditLog.php
    - tests/Feature/Filament/AuditLogResourceTest.php
    - app/Providers/Filament/AdminPanelProvider.php

key-decisions:
  - "Replaced TextEntry+json_encode()+FontFamily::Mono with KeyValueEntry::make() for old_values/new_values — sidesteps the 260731-o5i bug class entirely rather than continuing to patch around it"
  - "'Sistema' navigation group appended as the sixth/last entry, purely additive — the existing five groups (Gestión, Call Center, Mensajería, Jornada Electoral, Configuración) were not touched"

patterns-established:
  - "For array-cast model attributes rendered read-only in a Filament infolist, prefer KeyValueEntry over TextEntry+custom formatting — it has its own null-safe placeholder and reads the cast attribute directly"

requirements-completed: []

# Metrics
duration: ~15min
completed: 2026-07-31
---

# Phase quick: Fix AuditLogResource KeyValueEntry & Sistema Nav Group Summary

**Replaced ViewAuditLog's raw JSON-string old_values/new_values TextEntry blocks with native Filament KeyValueEntry tables, and added a 'Sistema' navigation group as the sixth/last entry in AdminPanelProvider.**

## Performance

- **Duration:** ~15 min (including worktree re-provisioning: fast-forward merge, composer install, .env/build copy)
- **Started:** 2026-07-31 (session start)
- **Completed:** 2026-07-31T22:38:05Z
- **Tasks:** 2
- **Files modified:** 4 (1 created, 3 modified)

## Accomplishments
- `old_values`/`new_values` on the AuditLog view page now render as proper key/value tables via `KeyValueEntry::make()`, replacing the harder-to-read pretty-printed JSON string introduced by quick task 260731-o5i
- `KeyValueEntry`'s default state resolution reads the model's array-cast attribute directly (no custom `->state()` closure), which structurally avoids the original o5i bug class (Filament's `TextEntry` invoking `formatStateUsing()` once per array element) rather than just working around it
- `AdminPanelProvider`'s sidebar now has a dedicated `'Sistema'` navigation group (sixth, after `'Configuración'`), giving system-level resources like `AuditLogResource` (which has no explicit `->navigationGroup()` call) a proper home instead of Filament's default "Other" bucket
- New `AdminPanelProviderTest` locks in the exact 6-group label order via `Filament::getPanel('admin')->getNavigationGroups()`

## Task Commits

Each task was committed atomically:

1. **Task 1: Replace old_values/new_values TextEntry with KeyValueEntry in ViewAuditLog** - `0093f12` (fix)
2. **Task 2: Register 'Sistema' navigation group in AdminPanelProvider** - `fd83dc1` (feat)

**Plan metadata:** (this commit, see below)

## Files Created/Modified
- `app/Filament/Resources/AuditLogs/Pages/ViewAuditLog.php` - old_values/new_values switched from `TextEntry`+`json_encode()`+`FontFamily::Mono` to `KeyValueEntry::make()`; removed now-unused `FontFamily` and `AuditLog` imports
- `tests/Feature/Filament/AuditLogResourceTest.php` - strengthened the mixed int/string regression test to assert individual key/value pairs render (`assertSee('email')`, `assertSee('jane@example.com')`) instead of only `assertSee('Jane Doe')`; added a new test for a record with both `old_values`/`new_values` null
- `app/Providers/Filament/AdminPanelProvider.php` - appended a sixth `NavigationGroup::make()->label('Sistema')` entry to `->navigationGroups([...])`
- `tests/Feature/Filament/AdminPanelProviderTest.php` - new file; asserts the panel's registered navigation group labels equal `['Gestión', 'Call Center', 'Mensajería', 'Jornada Electoral', 'Configuración', 'Sistema']` in exact order

## Decisions Made
- Replaced `TextEntry`+`json_encode()`+`FontFamily::Mono` with `KeyValueEntry::make()` for old_values/new_values — sidesteps the 260731-o5i bug class entirely rather than continuing to patch around it (no `->state()` closure, no manual JSON formatting)
- `'Sistema'` navigation group appended as the sixth/last entry, purely additive — the existing five groups were not reordered, renamed, or otherwise modified

## Deviations from Plan

None - plan executed exactly as written. Both tasks matched the plan's `<action>` and `<behavior>` specs precisely (KeyValueEntry with no custom state, `->placeholder('—')` override, unused-import removal verified via grep before deleting; Sistema group appended last with an exact-order Pest assertion).

## Issues Encountered
- **Stale worktree at session start:** `agent-a2dabfbe2d92f7e70` was one commit behind main (missing the plan file's own commit) and entirely missing `vendor/`, `.env`, `node_modules/`, and `public/build/` — the same recurring class of issue already documented in STATE.md's Blockers/Concerns. Resolved with the established workaround: confirmed `eb2a326`→`b22ae57` fast-forward ancestry against main, `git merge --ff-only main`, copied `.env` from the main checkout, ran `composer install`, and copied `public/build/` from the main checkout (rather than a full `npm install && npm run build`, since the main checkout's build was already current and this task made no frontend asset changes). This was necessary to get `php artisan test` running at all (initial `AdminPanelProviderTest`/`AuditLogResourceTest` runs failed with "Vite manifest not found" before the build copy).

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- Both defects fixed and regression-tested; no follow-up work identified by this plan
- Manual browser sanity check (visiting `/admin/audit-logs/{id}` and checking the sidebar for the "Sistema" group) was not performed as part of this automated quick task — the plan explicitly marks this as optional/not required to close the task, and the Pest HTTP-response assertions (`assertSee('email')`, `assertSee('jane@example.com')`, exact navigation-group-order assertion) already exercise the real rendered output through the full Filament stack

---
*Phase: quick*
*Completed: 2026-07-31*

## Self-Check: PASSED

All 4 claimed files found on disk (`app/Filament/Resources/AuditLogs/Pages/ViewAuditLog.php`, `tests/Feature/Filament/AuditLogResourceTest.php`, `app/Providers/Filament/AdminPanelProvider.php`, `tests/Feature/Filament/AdminPanelProviderTest.php`); both task commits (`0093f12`, `fd83dc1`) confirmed present in `git log`.

---
phase: quick
plan: 260731-nuk
subsystem: admin-ui
tags: [filament, audit-log, super-admin, pest]

requires:
  - phase: quick-260731-n0n
    provides: audit_logs table + AuditLog model + AuditObserver/AuditAuthActivitySubscriber write path
provides:
  - Read-only Filament AuditLogResource (index + view) to browse audit_logs
affects: [audit-trail-review, super-admin-tooling]

tech-stack:
  added: []
  patterns:
    - "canAccess() gate on a Resource, delegated to CampaignContext::isSuperAdmin() — same D-01 precedent as SaldosBadge"
    - "getPages() intentionally omits create/edit keys as the no-mutation-route mechanism"

key-files:
  created:
    - app/Filament/Resources/AuditLogs/AuditLogResource.php
    - app/Filament/Resources/AuditLogs/Tables/AuditLogsTable.php
    - app/Filament/Resources/AuditLogs/Pages/ListAuditLogs.php
    - app/Filament/Resources/AuditLogs/Pages/ViewAuditLog.php
    - tests/Feature/Filament/AuditLogResourceTest.php
  modified: []

key-decisions:
  - "AuditLogResource::canAccess() gates on CampaignContext::isSuperAdmin(), matching the D-01 precedent (SaldosBadge) rather than introducing a new role check"
  - "getPages() lists only index/view — no create/edit keys — so no mutation routes exist for the resource at all"
  - "old_values/new_values rendered via json_encode(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) with FontFamily::Mono on the ViewAuditLog infolist for legibility"

patterns-established:
  - "Read-only Filament Resource pattern: no form() override, getPages() omits create/edit, recordActions() has only ViewAction::make(), no bulkActions() call at all (not even an empty array)"

requirements-completed: []

duration: 20min
completed: 2026-07-31
---

# Quick Task 260731-nuk: Read-only AuditLogResource Summary

**Filament Resource (index + view only) to browse `audit_logs`, gated to `super_admin` via `CampaignContext::isSuperAdmin()`, with user/action/date-range filters and legible JSON-formatted old/new-values detail view.**

## Performance

- **Duration:** ~20 min
- **Completed:** 2026-07-31
- **Tasks:** 2
- **Files modified:** 5 (4 created resource files + 1 test file)

## Accomplishments
- `AuditLogResource` registered under a new "Sistema" navigation group, visible only to `super_admin` (both nav item and direct URL access blocked/403 for every other role)
- `AuditLogsTable` with sortable/searchable columns (fecha, usuario, acción-badge, modelo, ID registro, campaña, IP) plus `user_id` SelectFilter, multi-select `action` SelectFilter, and `created_at` date-range Filter
- `ViewAuditLog` infolist renders `old_values`/`new_values` as pretty-printed, monospace JSON
- No create/edit/delete/bulk-delete UI anywhere on the resource — `getPages()` returns only `index`/`view`
- Full Pest coverage: 10 tests covering gating (super_admin 200 vs admin_campaign/coordinator/reviewer 403), listing, all three filter types, `getPages()` key assertion, and absence of edit/delete table actions

## Task Commits

Each task was committed atomically:

1. **Task 1: AuditLogResource (index + view, super-admin gated)** - `3bd42f8` (feat)
2. **Task 2: Pest coverage for gating, listing, filters, and route surface** - `187928d` (test)

## Files Created/Modified
- `app/Filament/Resources/AuditLogs/AuditLogResource.php` - Resource definition, `canAccess()` gate, `getPages()` (index/view only)
- `app/Filament/Resources/AuditLogs/Tables/AuditLogsTable.php` - Columns, filters (user/action/date-range), `ViewAction`-only record actions
- `app/Filament/Resources/AuditLogs/Pages/ListAuditLogs.php` - Index page, no header actions (no create button)
- `app/Filament/Resources/AuditLogs/Pages/ViewAuditLog.php` - Read-only detail infolist with formatted old/new values JSON
- `tests/Feature/Filament/AuditLogResourceTest.php` - Full Pest coverage per plan's `<behavior>` spec

## Decisions Made
- Reused the existing `CampaignContext::isSuperAdmin()` gate (D-01 precedent from `260731-nuk-CONTEXT.md`) rather than a new role check.
- No `->bulkActions()` call at all on the table (not even an empty array) so no bulk-selection checkboxes render.
- No `form()` override on the Resource — base `Resource::form()` no-op is sufficient since there's no create/edit page.

## Deviations from Plan

None - plan executed exactly as written. All four resource files and the test file match the plan's specified content verbatim (aside from Pint formatting, which made no changes).

## Issues Encountered

- **Worktree staleness (recurring, pre-existing issue):** This execution's worktree (`agent-a4e19fbbf29fcfa95`) was checked out one commit behind `main`, missing the plan commit itself, `vendor/`, `.env`, `node_modules/`, and `public/build/`. Resolved with the established workaround from prior quick tasks: confirmed fast-forward ancestry, `git merge --ff-only main`, copied `.env` from the main checkout, ran `composer install`, `npm install`, and `npm run build`. Not a deviation from this plan's scope — purely environment provisioning, same class of issue logged repeatedly in STATE.md's Blockers/Concerns section.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- Closes the UI gap explicitly deferred by quick task `260731-n0n` — a super admin can now fully inspect the audit trail (who/what/when/before-after) via `/admin/audit-logs`.
- No blockers for future work. The read-only pattern established here (no `form()`, `getPages()` without create/edit, no `bulkActions()`) is reusable for any future audit/log-style Resource.

---
*Phase: quick*
*Completed: 2026-07-31*

## Self-Check: PASSED

All 5 created files confirmed present on disk; both task commits (3bd42f8, 187928d) confirmed in git log.

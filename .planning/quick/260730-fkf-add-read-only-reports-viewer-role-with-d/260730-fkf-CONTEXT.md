# Quick Task 260730-fkf: Read-only reports-viewer role - Context

**Gathered:** 2026-07-30
**Status:** Ready for planning

<domain>
## Task Boundary

Client asked for a new user type that can ONLY see reports/lists — no create/edit/delete,
no bulk actions, no other buttons. From a report (e.g. "duplicados", or "apoyos of leader X"),
clicking should navigate to the corresponding filtered list (reusing the existing
drill-through pattern already proven in `tests/Feature/WidgetDrillThroughTest.php`). Any list
this role lands on must also be view-only for this role (open a record to view, no edit/delete/
create/bulk actions).

</domain>

<decisions>
## Implementation Decisions

### Role
- New role, distinct from the existing `REVIEWER` (`app/Enums/UserRole.php`) — do NOT touch
  `REVIEWER`'s existing behavior (it validates apoyos / makes calls, that's a different job).
  Working name: "Analista" (view-only reports role) — confirm/adjust exact enum case name and
  Spanish label during planning if a better fit is found, but it must be a NEW `UserRole` case.

### Panel architecture
- New, dedicated Filament panel (same pattern as the existing Coordinator/Leader panels:
  `app/Providers/Filament/CoordinatorPanelProvider.php` / `LeaderPanelProvider.php`), not a
  restricted view bolted onto the existing Admin panel. This role gets its OWN panel with ONLY
  a reports-home screen (and the read-only drill-through list pages it links to) — nothing else
  from Admin is reachable. Chosen specifically to avoid any risk of an Admin-panel change later
  accidentally exposing an action button to this role.

### Campaign scoping
- Same as every other role today: ONE active campaign at a time via the existing campaign
  selector / `CampaignContext` mechanism — NOT all campaigns mixed together. Must respect the
  project's strict per-campaign data isolation (a hard constraint per CLAUDE.md/PROJECT.md).

### Reports to include
- ALL existing report/overview widgets, not a curated subset. Planner/researcher must produce
  the authoritative list by inspecting `app/Filament/Widgets/` and excluding anything that is
  operational/action-oriented rather than a report (e.g. `CallQueueTable` looked like an action
  queue, not a report, during initial scoping — confirm during planning/research, don't assume).
  Each included report becomes a card/entry on the reports-home screen; each links (drill-through)
  to its underlying filtered list.

### Enforcement of "no buttons"
- Whatever mechanism keeps this role's list pages fully read-only (view + open a record to view
  only) must be robust against future changes to the underlying Resources — prefer an approach
  that denies actions by role at the Resource/Policy level (mirroring the existing `hasAnyRole()`
  gating pattern already used elsewhere, e.g. the `actualizar_registraduria` action) rather than
  something that must be remembered to be re-applied every time a Resource gains a new action.

</decisions>

<specifics>
## Specific Ideas

- Reuse the existing drill-through pattern/tests as the reference implementation:
  `tests/Feature/WidgetDrillThroughTest.php` (FollowUpBacklogOverview, TopLeadersTable already
  link into filtered Voter/Leader lists this way).
- Existing precedent for role-gating a single action: `HasRegistraduriaPolling`'s
  `actualizar_registraduria` suffixAction gated via `hasAnyRole()` (Phase 10).

</specifics>

<canonical_refs>
## Canonical References

- `app/Enums/UserRole.php` — existing roles, add the new case here
- `app/Providers/Filament/CoordinatorPanelProvider.php` / `LeaderPanelProvider.php` — panel
  structure to mirror for the new panel
- `tests/Feature/WidgetDrillThroughTest.php` — drill-through pattern and its test conventions
- `app/Services/CampaignContext.php` — campaign-scoping mechanism to reuse

</canonical_refs>

# Phase 10: Operator Provenance & Fallback Controls - Context

**Gathered:** 2026-07-25
**Status:** Ready for planning

<domain>
## Phase Boundary

Operators can see the origin of every polling-place result (SRC-01), trigger a manual re-check at any time (SRC-04), and filter/triage the set of voters currently on fallback (non-live) data (SRC-05). This phase is UI/visibility work on top of the schema, audit trail, and resolver already built in Phases 7-8 — it does not change resolution logic, the resolver cascade, or the source-precedence rules.

</domain>

<decisions>
## Implementation Decisions

### SRC-04 — Manual Re-check Scope
- **D-01:** The existing "Actualizar datos desde Registraduría" suffix action (`forceRefreshFromRegistraduria`, `app/Filament/Resources/Voters/Schemas/VoterForm.php:210-224`) already satisfies SRC-04 as written ("operator can trigger a manual re-check at any time from the record"). No new UI surface (ViewVoter action, table row action) is needed for this requirement — the existing Edit-form button counts. This phase's SRC-04 work is to confirm/verify this satisfies the criterion, not build new UI.

### SRC-01 — Source Badge Placement
- **D-02:** The `polling_place_source` badge (using `PollingPlaceSource`'s existing `HasColor`/`HasLabel`/`HasIcon` — no new color/label mapping needed) appears in all three places:
  1. **VotersTable column** — new `TextColumn` using the same `->badge()` pattern as the existing `status` column (`app/Filament/Resources/Voters/Tables/VotersTable.php:83-97`).
  2. **ViewVoter infolist** — new `TextEntry` following the exact pattern of the existing `last_validation_source` entry (`app/Filament/Resources/Voters/Pages/ViewVoter.php:47-51`: `->badge()->color(...)`).
  3. **VoterForm (edit)** — near the `document_number` field / polling-place fields, so the operator sees current source while editing.
- **D-03:** `polling_place_resolved_at` (the freshness timestamp) is shown alongside the badge in **all three** locations — same `->dateTime('d/m/Y H:i')` pattern as existing date columns like `census_validated_at` (`VotersTable.php:127-131`).

### SRC-05 — Triage Filter
- **D-04:** The table filter is a `SelectFilter` on the exact 4 `PollingPlaceSource` values (Live/DB Reconstruction/Snapshot/Manual) — same pattern as the existing `status` `SelectFilter` (`VotersTable.php:166-170`), not a simplified "live vs. fallback" toggle. Follow the codebase convention: `->options(PollingPlaceSource::class)->multiple()->preload()`.
- **D-05:** A new dashboard widget shows a count of voters currently on fallback-sourced (non-live) polling-place data, scoped to the current campaign. Model this on `app/Filament/Widgets/FollowUpBacklogOverview.php` (a `StatsOverviewWidget` scoped via `CampaignContext`) — same architecture, new metric (count where `polling_place_source != 'live'` and not null).

### Cost/Role Control on Re-check
- **D-06:** The "Actualizar datos desde Registraduría" action (forces a paid live lookup, bypassing cache) is restricted to `UserRole::ADMIN_CAMPAIGN` and `UserRole::COORDINATOR` (plus `UserRole::SUPER_ADMIN`, per the codebase's existing convention of always including super-admin alongside admin-level role gates — see `EditVoter.php:35-36`'s `reassignDuplicateOwner` action for the exact pattern: `auth()->user()?->hasAnyRole([...])`). Leaders and reviewers can still see the source badge (SRC-01 is universally visible) but the "Actualizar" button's `->visible()` callback must additionally gate on this role check. The read-only "Consultar Registraduría" lookup action (`openRegistraduriaBrowser`, used when no polling place is set yet) is UNCHANGED — this role restriction applies only to the force-refresh/bypass-cache action, not the original lookup.

### Claude's Discretion
- Exact placement/column order of the new badge in VotersTable and the new field in VoterForm (follow existing toggleable/ordering conventions).
- Exact wording of the new StatsOverviewWidget's `Stat` label/description (match Spanish UI conventions already used, e.g. `FollowUpBacklogOverview.php`'s style).
- Whether the new SelectFilter uses `PollingPlaceSource::class` directly as `->options()` or an explicit array — whichever matches Filament v4 enum-filter conventions already in the codebase.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Schema & Enum (Phase 7)
- `app/Enums/PollingPlaceSource.php` — the source enum with `HasColor`/`HasLabel`/`HasIcon`/`HasDescription` already implemented (LIVE=success/green, DB_RECONSTRUCTION=info/blue, SNAPSHOT=warning/yellow, MANUAL=gray) and `precedence()`/`outranks()` for the no-downgrade guard (not touched by this phase).
- `.planning/phases/07-source-flag-schema-resolution-audit-trail/07-CONTEXT.md` — original schema decisions for `polling_place_source`/`polling_place_resolved_at`/audit trail.

### Resolver & Existing Actions (Phase 8)
- `app/Filament/Resources/Voters/Concerns/HasRegistraduriaPolling.php` — `openRegistraduriaBrowser()` (initial lookup) and `forceRefreshFromRegistraduria()` (force re-check, D-01/D-06 apply here) live here. Do not change the resolver-calling logic, only add the role gate (D-06) at the Filament Action `->visible()` level in VoterForm.php, not inside this trait's methods.
- `app/Filament/Resources/Voters/Schemas/VoterForm.php:195-239` — the `document_number` field with its two existing `suffixAction`s. The "Actualizar" suffix action (lines 210-224) is where D-06's role gate attaches.
- `.planning/phases/08-resilient-pollingplaceresolver-service/08-CONTEXT.md` — D-09 (pixel-identical behavior requirement from Phase 8) — this phase's role gate is a NEW restriction on top of that baseline, not a violation of D-09 (D-09 covered the Phase 8 refactor only).

### Requirements & Scope
- `.planning/REQUIREMENTS.md` §v2 Requirements — `WIDGET-01` (reconciliation-queue depth widget) and `WIDGET-03` (bulk re-check) are explicitly v2/out of scope for this phase. The new count widget (D-05) is narrower than WIDGET-01 (a simple fallback-count stat, not a reconciliation-queue-depth metric) and does not fulfill WIDGET-01 — it's a Phase 10 SRC-05 "triage visibility" deliverable, distinct from the deferred v2 item.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `app/Filament/Widgets/FollowUpBacklogOverview.php` — the exact `StatsOverviewWidget` + `CampaignContext` scoping pattern to copy for D-05's new widget.
- `PollingPlaceSource` enum already implements Filament's badge contracts — `->badge()` on a column/entry needs no extra `->color()`/`->icon()` mapping, Filament resolves it automatically from the enum.
- `EditVoter.php`'s `reassignDuplicateOwner` action (lines 31-39) — the exact `hasAnyRole([UserRole::ADMIN_CAMPAIGN->value, UserRole::SUPER_ADMIN->value])` pattern to extend with `UserRole::COORDINATOR->value` for D-06.
- `ViewVoter.php`'s `last_validation_source` TextEntry (lines 47-51) — copy-paste pattern for the new source badge entry.
- `VotersTable.php`'s `status` column (lines 83-97) and `census_validated_at` column (lines 127-131) — copy-paste patterns for the new badge column + resolved_at date column.
- `VotersTable.php`'s `status` SelectFilter (lines 166-170) — copy-paste pattern for D-04's new filter.

### Established Patterns
- Toggleable columns (`->toggleable(isToggledHiddenByDefault: true)`) are used for secondary/detail columns in VotersTable — consider for the new resolved_at column, follow existing density conventions.
- Spanish-language labels throughout (`'Fuente de Última Validación'`, `'Estado'`, etc.) — new labels must follow this convention (e.g., "Fuente del Puesto de Votación", "Actualizado el").

### Integration Points
- `VotersTable.php` (new column + new filter), `VoterForm.php` (new field + role gate on existing suffix action), `ViewVoter.php` (new infolist entry), and a new `app/Filament/Widgets/*.php` class (registered wherever `FollowUpBacklogOverview` is registered — check the panel provider).

</code_context>

<specifics>
## Specific Ideas

- The role restriction (D-06) should reuse the exact `hasAnyRole()` call style already in `EditVoter.php` rather than introducing a new authorization pattern (e.g., a Policy or Gate) — this phase is UI/visibility scoped, not an authorization-architecture change.

</specifics>

<deferred>
## Deferred Ideas

- Bulk re-check across multiple selected voters (WIDGET-03) — already v2 in REQUIREMENTS.md, not touched by this phase.
- Reconciliation-queue-depth widget / last-successful-reachability widget (WIDGET-01) — already v2, distinct from this phase's simpler fallback-count widget (D-05).
- Per-record "still trying" / "gave up" reconciliation narrative (already listed Out of Scope in REQUIREMENTS.md) — not relevant here since Phase 10 has no reconciliation job yet (that's Phase 11).

None else — discussion stayed within phase scope.

</deferred>

---

*Phase: 10-operator-provenance-fallback-controls*
*Context gathered: 2026-07-25*

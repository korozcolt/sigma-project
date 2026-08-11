# Phase 16: Metadata Catalog UI & Assignment - Context

**Gathered:** 2026-08-10
**Status:** Ready for planning

<domain>
## Phase Boundary

This phase builds the UI layer on top of Phase 12's schema (`metadata_keys`, `user_metadata_values`, both already migrated and modeled): (1) a superadmin-facing Filament resource to create/edit/deactivate metadata keys, and (2) an assignment mechanism so any eligible superior (coordinador, articulador, superadmin/admin_campaign) assigns a catalog value to their direct subordinates — individually or in bulk. No new schema, no schema changes — this phase is UI/business-logic only.

</domain>

<decisions>
## Implementation Decisions

### Alcance de Subordinado Directo
- **D-01:** Líder is excluded from the metadata assignment flow entirely — no menu item, tab, or action appears in the líder panel. Líder has zero User-type subordinates in the current data model (only registered Voters/Apoyos, which don't participate in the `users`-scoped metadata system). ROADMAP.md's success criterion 2 lists líder among "any superior," but since líder has no eligible subordinates, the assignment mechanism simply never has anyone for líder to act on — this is by design, not a gap. Downstream verifier should NOT flag "líder has no metadata UI" as a missing success criterion.
- **D-02:** Superadmin/admin_campaign can assign metadata to ANY user in the active campaign, without hierarchy restriction — not limited to "top of hierarchy only" (articuladores + orphaned coordinadores/leaders). Full visibility by design, consistent with their existing unrestricted access elsewhere in the app.
- **D-03:** "Direct subordinate" resolves per role as: coordinador → their `leaders()`; articulador → their `coordinators()`; superadmin/admin_campaign → any `User` in the active campaign. This is **explicitly different** from Phase 13's `User::teamCoordinatorUserIds()` (the transitive-team resolver built for AUTHZ-01/dashboard scoping) — META-03 requires direct subordinates only, not the full transitive team. The planner must NOT reuse `teamCoordinatorUserIds()` for this; a new resolution (a dedicated method or inline per-role logic) is needed.

### Ubicación de la UI de Asignación
- **D-04:** Individual assignment lives as a section/tab inside the ALREADY-EXISTING edit forms (`EditCoordinator`, `EditLeader`, `EditAreaCoordinator`) — not a standalone Filament resource, not a table-row modal action.
- **D-05:** The assignment capability must exist in 3 places: the Admin panel (superadmin/admin_campaign — building on what already exists there), the Coordinador panel (coordinador assigns to their own líderes), and the Articulador panel (articulador assigns to their own coordinadores).

### Asignación Masiva (META-04)
- **D-06:** Bulk assignment is a table `BulkAction` (same pattern already used in `CoordinatorsTable`) — select multiple rows, choose ONE key + ONE value, apply identically to every selected row.
- **D-07:** One key per bulk action — no multi-key repeater in a single bulk submit. Assigning two different keys means running the bulk action twice.

### Validación y Visualización por Tipo
- **D-08:** `numeric`-typed keys are validated in the form with `TextInput::numeric()`, allowing 2 decimal places. The DB column (`user_metadata_values.value`) remains a plain `string` (per Phase 12's actual migration, not the conceptual "decimal" language in 12-CONTEXT.md D-05) — decimal precision is enforced at the Filament validation layer, not the schema layer.
- **D-09:** The assignment UI shows ONLY the current value per key (the most recent row for `(user_id, metadata_key_id)`) plus who assigned it and when — no expandable history view in this phase. Full history remains queryable in the DB (append-only per Phase 12 D-02) for future phases (META-07/META-08) if ever surfaced.

### Claude's Discretion
- Exact naming of the new "direct subordinate" resolver on `User` (e.g. a new method vs inline per-role logic in the assignment component) — follow existing method-naming conventions (`coordinators()`, `leaders()`).
- For `select`-typed keys, the assignment form's value field renders a `Select` populated dynamically from that key's `options` JSON array — this follows directly from Phase 12's schema shape and META-01, not an open question.
- For `date`-typed keys, a Filament `DatePicker`; for `text`-typed keys, a plain `TextInput`.
- Exact Filament resource naming (e.g. `MetadataKeyResource`), mirroring `GremioResource`'s structure per Phase 12's code_context recommendation.
- Where exactly within each panel's navigation the metadata tab/section sits (ordering, icon, label wording).
- Bulk action target-user validation is implicit — each panel's own table is already scoped to that role's own subordinates (a coordinador's table only ever lists their own leaders), so no additional cross-checking is needed beyond what table scoping already provides.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Phase 12 schema (this phase builds directly on it)
- `.planning/phases/12-hierarchy-metadata-schema-foundation/12-CONTEXT.md` — D-01 through D-06: global catalog (no campaign_id), append-only value history, type enum (numeric/text/date/select)
- `database/migrations/2026_08_10_120100_create_metadata_keys_table.php` — exact catalog schema (key unique, label, type enum, options json, is_active)
- `database/migrations/2026_08_10_120200_create_user_metadata_values_table.php` — exact value schema (user_id cascade, metadata_key_id restrict, value as string, assigned_by nullable, assigned_at, no unique constraint)
- `app/Models/MetadataKey.php`, `app/Models/UserMetadataValue.php` — existing models with relations already in place

### Hierarchy resolution (Phase 13 — do NOT reuse for direct-subordinate scoping, see D-03)
- `app/Models/User.php` — `coordinator()`/`leaders()`/`areaCoordinator()`/`coordinators()` relation pairs; `teamCoordinatorUserIds()` (transitive team, NOT what this phase needs)

### Project-level
- `.planning/REQUIREMENTS.md` — META-01 through META-06 (this phase's mapped requirements); FILT-01/02/03 (Phase 17, depends on this phase's data shape); "Out of Scope" section (no hard delete of keys with assignments, no cross-campaign catalog sharing)
- `.planning/ROADMAP.md` — Phase 16 section (goal, success criteria, depends on Phase 12+13)
- `.planning/PROJECT.md` — Current Milestone section (v1.2 goal and target features)

### Code patterns to mirror
- `app/Filament/Resources/Gremios/*` — exact Filament resource structure to mirror for the new metadata-key catalog resource (Resource/Tables/Schemas/Pages split)
- `app/Filament/Resources/Coordinators/Tables/CoordinatorsTable.php` — existing `BulkAction::make()` usage, the pattern to mirror for D-06's bulk assignment action

No external specs/ADRs beyond the above — requirements and prior-phase context fully captured here.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `app/Filament/Resources/Gremios/*` (Resource/Table/Form/Pages) — direct structural template for the new metadata-key catalog resource
- `app/Filament/Resources/Coordinators/Tables/CoordinatorsTable.php` — has existing `BulkAction` usage to mirror for the bulk-assign action
- `app/Models/User.php` `leaders()`/`coordinators()` relations — the query source for each role's "direct subordinates" list

### Established Patterns
- Filament panel providers already exist for admin, coordinator, and articulador (Phases 1-15) — this phase adds sections/tabs to existing Edit pages within those panels, it does not create new panels.
- Global superadmin-managed catalogs (mirrors `Gremio`) — no `campaign_id`, unique natural key, `is_active` toggle for soft-deactivation (already established as the pattern in Phase 12).

### Integration Points
- `app/Filament/Resources/Coordinators/Pages/EditCoordinator.php` — add metadata assignment section here (coordinador's líderes)
- `app/Filament/Resources/AreaCoordinators/Pages/EditAreaCoordinator.php` — add metadata assignment section here (superadmin/admin_campaign panel, articulador's coordinadores are also assignable from here per D-02's "any user" scope)
- Coordinador panel's own Volt-based edit-leader page (`resources/views/livewire/coordinator/edit-leader.blade.php`) and articulador panel's edit-coordinator page (`resources/views/livewire/articulador/edit-coordinator.blade.php`, built in Phase 15) — these are Volt, not Filament, so the "tab/section" from D-04 needs a Volt-compatible implementation there, not just a Filament schema addition. Flag this for the researcher: the admin-panel assignment UI is Filament-native, but the coordinador/articulador panel assignment UI must be built into existing Volt components.
- `app/Filament/Resources/Coordinators/Tables/CoordinatorsTable.php` — add the bulk-assign action here for coordinador's líderes; equivalent Volt-side table on the coordinador/articulador panels needs the bulk mechanism translated to Livewire (no native Filament `BulkAction` available there).

</code_context>

<specifics>
## Specific Ideas

No specific visual/UX requirements beyond what's captured in decisions above — the user deferred to Claude's discretion on exact form field types (Select for `select` type, DatePicker for `date` type) since these follow directly from the schema types already locked in Phase 12.

</specifics>

<deferred>
## Deferred Ideas

- Point-in-time/effective-dated metadata queries and expandable assignment-history view in the UI — tracked as META-07 in REQUIREMENTS.md v2 section (D-09 explicitly keeps this phase to current-value-only).
- Metadata rollup/aggregation dashboards — tracked as META-08 in REQUIREMENTS.md v2 section.
- Extending metadata assignment to Voters/Apoyos (to give líder something to assign to) — considered and explicitly rejected for this phase (D-01); would require new schema, out of scope.

### Reviewed Todos (not folded)
None — no pending todos matched Phase 16 (`todo match-phase 16` returned 0 matches).

</deferred>

---

*Phase: 16-metadata-catalog-ui-assignment*
*Context gathered: 2026-08-10*

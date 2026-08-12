# Phase 17: Filter/Sort/Export Surfaces - Context

**Gathered:** 2026-08-11
**Status:** Ready for planning

<domain>
## Phase Boundary

This phase adds filter, sort, and export surfacing for assigned metadata (schema + assignment mechanism already built in Phase 12/16) across the four **admin-panel Filament tables** — `UsersTable`, `CoordinatorsTable`, `LeadersTable`, `AreaCoordinatorsTable` — and adds metadata columns to the existing CSV/xlsx exports for coordinadores, líderes, testigos, and anotadores. No new schema, no new assignment logic — this phase is table/export UI only, reading `MetadataKey`/`UserMetadataValue` written by Phase 16.

**Explicitly excluded:** ROADMAP.md's success criteria say "Filament tables" literally. The coordinador panel's líderes list (`resources/views/livewire/coordinator/leaders.blade.php`) and the articulador panel's coordinadores list (`resources/views/livewire/articulador/coordinators.blade.php`) are Volt/Livewire, not Filament — they are out of scope for this phase. Extending filter/sort to those self-service panels would be a new capability for a future phase.

</domain>

<decisions>
## Implementation Decisions

### UX del Filtro de Metadata (FILT-01)
- **D-01:** One generic cascading "Metadata" filter (not one filter per catalog key). The filter shows a Select of active metadata keys; once a key is chosen, the value field renders according to that key's type (Select of `options` for `select`, `TextInput::numeric()` for `numeric`, `DatePicker` for `date`, plain `TextInput` for `text`) — mirroring the field-type-per-type pattern already established in the Phase 16 assignment forms.
- **D-02:** Only one metadata condition can be applied at a time (no AND-combination across two different metadata keys in the same query). FILT-01 only requires "filter by key and value" — this keeps the filter panel from growing unbounded as the catalog grows. Combining multiple metadata filters simultaneously is explicitly out of scope for this phase.

### Semántica del Filtro por Tipo
- **D-03:** Filter matching is exact-value equality for ALL types — `numeric`, `text`, `date`, and `select` all filter on exact match of the current value. No range operators (≥/≤) for `numeric`/`date` in this phase. This satisfies FILT-01's literal wording ("filtrar por llave y valor") without adding range-query UI/plumbing.

### Columnas de Metadata en la Tabla (FILT-02)
- **D-04:** For each active metadata key in the catalog, dynamically generate one `TextColumn` in every one of the four tables (Users, Coordinators, Leaders, AreaCoordinators), showing that user's current value for the key. Each column is `toggleable(isToggledHiddenByDefault: true)` (hidden by default, same convention already used for secondary columns like `email`/`birth_date` in these tables) and `sortable()`. Sorting must resolve the "current value" per user (latest row by `assigned_at` DESC, `id` DESC tiebreak per Phase 16 D-09) and, for `numeric`-typed keys, sort numerically (cast) rather than lexicographically — this is what makes FILT-02's "10 > 2, not alphabetical" requirement concrete.
- **D-05:** Sorting is triggered by clicking the column header, standard Filament table-sort UX — no separate "sort by metadata" control outside the normal column system.

### Alcance y Columnas de los Exports (FILT-03)
- **D-06:** Exports in scope: `CoordinatorsExport`, `LeadersExport`, `AnnotatorsExport`, `WitnessesExport`. These are the only existing CSV/xlsx export classes that source from the `users` table (`AnnotatorsExport`/`WitnessesExport` are reachable from the admin Users list — "Exportar Anotadores"/"Exportar Testigos" — and were confirmed in scope as the "usuarios" exports FILT-03 refers to). No new export class is created for a generic "all users" export — none exists today and none is being added.
- **D-07:** `TopCoordinatorsExport`, `TopLeadersExport`, `TopPollingPlacesExport`, `DuplicatesExport`, `RejectionsExport`, `JurisdictionExport`, `VotersExport`, `ApoyosLideresCoordinadoresExport` are explicitly OUT of scope — they are reporting/analytics exports, not the "usuarios/coordinadores/líderes" management-table exports FILT-03 targets.
- **D-08:** Each in-scope export includes one column per **active** metadata key from the catalog — not just keys that happen to have an assigned value among the exported rows. Blank cell when the exported user has no value for that key. This keeps column sets stable across repeated exports/downloads regardless of which rows are included, at the cost of occasional blank columns when a key is new/unused.
- **D-09:** No Articuladores/AreaCoordinators export exists today and none is created in this phase — FILT-03's wording ("usuarios/coordinadores/líderes") does not mention articuladores, consistent with D-06's export scope.

### Claude's Discretion
- Exact SQL approach for resolving "current value per user per metadata key" at table-query scale (subquery, correlated join, or other) — `MetadataAssignmentService::currentValues()`'s existing PHP-side approach only works for a single user and does not scale to a whole table's worth of users; a query-level equivalent must be designed. Follow existing service-layer conventions; this is implementation detail, not a UX decision.
- Exact column key/id naming for the dynamically generated per-metadata-key TextColumns (e.g. `metadata_{id}` vs `metadata.{key}`), and where in the column list they're inserted relative to existing columns.
- Whether the generic metadata filter and the dynamic per-key columns share one underlying query-building helper (e.g. a `MetadataTableColumns`/`MetadataTableFilter` class in `app/Filament/Schemas/` or similar, mirroring the existing `App\Filament\Schemas\MetadataAssignment` naming pattern) — follow existing naming/organization conventions from Phase 16.
- Export column header wording for metadata columns (e.g. the key's `label` verbatim vs. a prefixed variant) — use the key's existing `label` field, consistent with how the assignment UI already displays labels.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Phase 16 (this phase reads the data Phase 16 writes)
- `.planning/phases/16-metadata-catalog-ui-assignment/16-CONTEXT.md` — full assignment-flow decisions, especially D-03 (direct-subordinate scoping, not needed here since filter/sort/export read across the whole table scope already established by each resource) and D-09 (current-value resolution semantics: latest by `assigned_at` DESC, `id` DESC tiebreak)
- `app/Services/MetadataAssignmentService.php` — `currentValues()`/`currentValueFor()` show the PHP-side current-value resolution pattern (single-user only); `validateValue()` shows per-type validation logic to mirror for filter-value validation
- `app/Models/MetadataKey.php`, `app/Models/UserMetadataValue.php` — schema/relations (key, label, type, options, is_active on MetadataKey; user_id, metadata_key_id, value as string, assigned_by, assigned_at on UserMetadataValue, no unique constraint, indexed on `[user_id, metadata_key_id, assigned_at]`)
- `database/migrations/2026_08_10_120200_create_user_metadata_values_table.php` — confirms `value` is a plain string column; numeric sort correctness (FILT-02) requires an explicit cast, the column itself sorts lexicographically by default

### Project-level
- `.planning/REQUIREMENTS.md` — FILT-01, FILT-02, FILT-03 (this phase's mapped requirements)
- `.planning/ROADMAP.md` — Phase 17 section (goal, success criteria, "Filament tables" wording that excludes Volt self-service panels)
- `.planning/PROJECT.md` — Current Milestone section (v1.2 goal and target features)

### Code patterns to mirror
- `app/Filament/Resources/Users/Tables/UsersTable.php` — existing `SelectFilter`/`TernaryFilter` usage and `toggleable(isToggledHiddenByDefault: true)` convention to mirror for the new metadata filter and columns
- `app/Filament/Resources/Coordinators/Tables/CoordinatorsTable.php`, `app/Filament/Resources/Leaders/Tables/LeadersTable.php`, `app/Filament/Resources/AreaCoordinators/Tables/AreaCoordinatorsTable.php` — the three other tables needing the same metadata filter + dynamic columns added
- `app/Filament/Schemas/MetadataAssignment.php` — existing shared Filament schema builder pattern (per-type field rendering: Select/TextInput::numeric()/DatePicker/TextInput) to mirror for the filter's value field
- `app/Exports/CoordinatorsExport.php`, `app/Exports/LeadersExport.php`, `app/Exports/AnnotatorsExport.php`, `app/Exports/WitnessesExport.php` — all four implement `FromQuery`+`WithHeadings`+`WithMapping`, currently with static `headings()`/`map()` arrays; these need to become dynamic (append one heading + one mapped cell per active `MetadataKey`)

No external specs/ADRs beyond the above — requirements and prior-phase context fully captured here.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `app/Filament/Schemas/MetadataAssignment.php` — per-type field-rendering logic already exists for the assignment form; the filter's cascading key→value field should follow the same type-dispatch pattern
- `app/Services/MetadataAssignmentService.php` — `activeKeys()`/`activeKeyOptions()`/`validateValue()` are directly reusable for populating the filter's key Select and validating the value field
- Four export classes (`CoordinatorsExport`, `LeadersExport`, `AnnotatorsExport`, `WitnessesExport`) already share an identical `FromQuery`+`WithHeadings`+`WithMapping`+`WithStyles` structure — a shared trait/base for the dynamic metadata-column logic would avoid duplicating it 4 times

### Established Patterns
- All four tables (Users, Coordinators, Leaders, AreaCoordinators) already use `toggleable(isToggledHiddenByDefault: true)` for secondary/detail columns — the new metadata columns should follow this exact convention
- `UsersTable` already has multiple `SelectFilter`s with `->relationship()`/`->preload()` — the new metadata filter differs from these (no direct relationship, needs a custom `Filter::make()` with a form schema and a `query()` callback), so it won't reuse `SelectFilter` directly
- `UserMetadataValue` is append-only (Phase 16 D-02/META-06) — "current value" is always "most recent row," never a direct column read

### Integration Points
- `app/Filament/Resources/Users/Tables/UsersTable.php`, `.../Coordinators/Tables/CoordinatorsTable.php`, `.../Leaders/Tables/LeadersTable.php`, `.../AreaCoordinators/Tables/AreaCoordinatorsTable.php` — all four need the new filter added to `->filters([...])` and the dynamic columns merged into `->columns([...])`
- `app/Exports/CoordinatorsExport.php`, `app/Exports/LeadersExport.php`, `app/Exports/AnnotatorsExport.php`, `app/Exports/WitnessesExport.php` — all four need `headings()`/`map()` to append one column per active `MetadataKey`
- `app/Http/Controllers/CampaignAdmin/CoordinatorsExportController.php`, `app/Http/Controllers/CampaignAdmin/AnnotatorsExportController.php`, `app/Http/Controllers/CampaignAdmin/WitnessesExportController.php`, `app/Http/Controllers/Coordinator/LeadersExportController.php` — the controllers instantiating those export classes; no changes expected here unless query eager-loading needs to include metadata values for N+1 avoidance

</code_context>

<specifics>
## Specific Ideas

No specific visual/UX requirements beyond what's captured in decisions above — user deferred to recommended defaults on every gray area discussed (single cascading filter, exact-match semantics, toggleable sortable columns, all-active-keys export columns).

</specifics>

<deferred>
## Deferred Ideas

- Combining multiple metadata-key filter conditions simultaneously (AND across keys) — deferred per D-02; would need the per-key-filter approach instead of the generic cascading filter.
- Range filtering (≥/≤) for `numeric`/`date`-typed metadata keys — deferred per D-03; exact-match only in this phase.
- Extending metadata filter/sort to the Volt-based self-service panels (coordinador's líderes list, articulador's coordinadores list) — explicitly out of scope per the domain boundary; ROADMAP.md's success criteria name "Filament tables" only.
- A generic "export all users" CSV/xlsx (not role-scoped) — does not exist today; not created in this phase (D-06).
- Metadata columns/filter/sort for Top-N reporting exports (`TopCoordinatorsExport`, `TopLeadersExport`, etc.) — deferred per D-07, out of FILT-03's scope.

### Reviewed Todos (not folded)
None — no pending todos matched Phase 17 (`todo match-phase 17` returned 0 matches).

</deferred>

---

*Phase: 17-filter-sort-export-surfaces*
*Context gathered: 2026-08-11*

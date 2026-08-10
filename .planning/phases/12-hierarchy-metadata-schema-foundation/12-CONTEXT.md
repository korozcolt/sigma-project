# Phase 12: Hierarchy & Metadata Schema Foundation - Context

**Gathered:** 2026-08-10
**Status:** Ready for planning

<domain>
## Phase Boundary

This phase delivers the database/model layer only — no UI. It creates: (1) the `area_coordinator` Spatie role and a dedicated `area_coordinator_user_id` self-referencing FK on `users`, structurally allowing exactly one extra hierarchy level above `coordinador` with no further nesting and no hard cap; and (2) a superadmin-managed metadata-key catalog (`metadata_keys`) plus the value-storage table (`user_metadata_values`), typed and ready for the authorization (Phase 13) and UI phases (14-17) to build on without further schema changes.

</domain>

<decisions>
## Implementation Decisions

### Metadata Catalog Scope
- **D-01:** The metadata-key catalog is **global** — one shared `metadata_keys` list across all of SIGMA, not scoped per campaign. `metadata_keys` gets no `campaign_id` column. Matches how the client described it and mirrors the existing global reference-catalog precedent (`Gremio`, `Subcategoria` — neither is campaign-scoped).

### Metadata Value Storage Model
- **D-02:** `user_metadata_values` is **append-only** — every assignment (including a reassignment of a key a subordinate already had) inserts a new row rather than updating an existing one. No unique constraint on `(user_id, metadata_key_id)`. The "current" value for a given user+key is the row with the latest `assigned_at` for that pair.
- **D-03:** This design gives native per-assignment audit history for free (who assigned what, to whom, when — satisfies META-05 directly from the table shape, no separate audit table needed for this data). It also means no future migration is needed if historical/point-in-time value viewing becomes a real requirement later (deferred as META-07/META-08 in REQUIREMENTS.md v2 section) — the data will already be there.
- **D-04:** Phase 17's filter/sort/export work must always resolve "current value" as the latest row per `(user_id, metadata_key_id)` — this is a query-shape constraint downstream phases must respect, not a Phase 12 build item, but the schema decision here is what makes it necessary. Flag this explicitly for the Phase 17 planner.

### Numeric Value Precision
- **D-05:** Numeric-typed metadata values are stored as **decimal**, not integer. Client's real examples (biaticos, almuerzo, incentivo) are whole pesos with no cents today, but the user explicitly asked to type it as decimal "por si las moscas" (in case a fractional value is ever needed) rather than lock into integer-only.
- **D-06:** The two core catalog value types to support are **string** and **decimal** (per the user's own framing). META-01 in REQUIREMENTS.md also lists `fecha`/`selección` as catalog types — these remain in scope for the `metadata_keys.type` enum, but string and decimal are the two the user called out as the primary/most-used pair.

### Claude's Discretion
- Exact migration/model/relation naming (e.g. `User::areaCoordinator()`, `User::coordinators()` relation method names) — follow the existing `coordinator_user_id`/`User::coordinator()`/`User::leaders()` naming convention exactly, per architecture research's explicit recommendation to mirror it.
- Whether `canAccessPanel()` is wired for `area_coordinator` in this phase or deferred to Phase 14/15 — this phase is schema-only per its own roadmap description ("no UI yet"); panel access decisions belong to the phases that build the panels.
- `metadata_keys` soft-deactivate column naming/shape (`active` boolean vs `deactivated_at` timestamp) — REQUIREMENTS.md Out of Scope already establishes hard-delete is prohibited (soft-deactivate only); the exact column shape is an implementation detail.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Milestone research (all written 2026-08-10, specific to this exact phase's schema decisions)
- `.planning/research/SUMMARY.md` — executive summary, recommended stack, phase-by-phase rationale
- `.planning/research/ARCHITECTURE.md` — exact recommendation for `area_coordinator_user_id` FK (dedicated, not reusing `coordinator_user_id`), `metadata_keys`/`Gremio`-precedent catalog shape, build order
- `.planning/research/PITFALLS.md` — critical pitfalls 1-9, especially #2 (never overload `coordinator_user_id`) and #6 (unindexed JSON scan) which this phase's schema choices must avoid by construction
- `.planning/research/STACK.md` — confirms no new package needed; native Laravel/MySQL JSON/query capabilities suffice (superseded in relevant part by D-02's append-only normalized-table decision, which was chosen over the JSON-column approach STACK.md originally evaluated)
- `.planning/research/FEATURES.md` — table-stakes/differentiator/anti-feature breakdown; anti-features (freeform keys, unlimited nesting, cross-campaign catalog sharing, hard-delete) already encoded in REQUIREMENTS.md Out of Scope

### Project-level
- `.planning/REQUIREMENTS.md` — ARTIC-04, ARTIC-05 (this phase's mapped requirements); META-01..06 (schema this phase must support); Out of Scope section (no hard delete, no cross-campaign sharing, no cascade on reassignment)
- `.planning/PROJECT.md` — Current Milestone section (v1.2 goal and target features)

No external specs/ADRs beyond the above — requirements and research fully captured here.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `database/migrations/2026_01_21_000002_add_coordinator_to_users_table.php` — exact migration shape to mirror for `area_coordinator_user_id` (nullable FK, `constrained('users')->nullOnDelete()`, indexed)
- `app/Models/User.php` — `coordinator()` (`belongsTo`) / `leaders()` (`hasMany`) relation pair to mirror for `areaCoordinator()`/`coordinators()`
- `app/Filament/Resources/Gremios/*` (`Gremio` model + `GremioResource`) — exact shape to mirror for the `metadata_keys` catalog table + future `MetadataKeyResource` (Phase 16), though this phase only needs the migration/model, not the Filament resource
- `app/Enums/UserRole.php` + `database/seeders/RoleSeeder.php` — where the new `area_coordinator` enum case and seeded Spatie role get added

### Established Patterns
- Self-referencing FK hierarchy pattern (`coordinator_user_id`) is flat by construction — no recursive/tree column, just one FK per level. The same shape (one FK, no recursion) is what makes ARTIC-04 (no further nesting) true structurally rather than needing application-level validation.
- `campaign_user` pivot table (not FK relationships) drives campaign isolation via `CampaignMembershipScope` — confirmed orthogonal to both the hierarchy FK and the metadata tables; neither needs `campaign_id` awareness for isolation purposes (the global-catalog decision, D-01, is consistent with this — isolation happens at the `users`/`campaign_user` layer, not the catalog layer).

### Integration Points
- `app/Enums/UserRole.php` — add `AREA_COORDINATOR = 'area_coordinator'` case with Spanish `->label()` ("Articulador")
- `database/seeders/RoleSeeder.php` — seed the new role
- `app/Models/User.php` — new relations + fillable/casts as needed
- New migrations: `add_area_coordinator_to_users_table`, `create_metadata_keys_table`, `create_user_metadata_values_table`

</code_context>

<specifics>
## Specific Ideas

No specific UI/visual requirements — this phase has no UI. The three locked decisions above (global catalog, append-only value history, decimal precision) are the concrete specifics that came out of discussion.

</specifics>

<deferred>
## Deferred Ideas

- Point-in-time/effective-dated metadata value queries ("what was biaticos on date X") — already tracked as META-07 in REQUIREMENTS.md v2 section. D-02's append-only design makes this cheap to add later without a schema change, but building the query/UI for it is explicitly out of v1.2 scope.
- Metadata rollup/aggregation dashboards (total biaticos per articulador's team) — already tracked as META-08 in REQUIREMENTS.md v2 section.

### Reviewed Todos (not folded)
None — no pending todos matched Phase 12 (`todo match-phase 12` returned 0 matches).

</deferred>

---

*Phase: 12-hierarchy-metadata-schema-foundation*
*Context gathered: 2026-08-10*

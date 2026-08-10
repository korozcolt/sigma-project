# Project Research Summary

**Project:** SIGMA v1.2 — Articuladores + Metadata de Usuario
**Domain:** Hierarchical field-operations platform extension (new org tier above `coordinador`) + superadmin-managed JSON metadata catalog with Filament v4 filter/sort (MySQL)
**Researched:** 2026-08-10
**Confidence:** HIGH

## Executive Summary

This milestone adds two related but separable capabilities to SIGMA's existing Laravel/Filament/Spatie hierarchy: a new `articulador` role that sits one level above `coordinador` (mirroring the existing `coordinador -> lider` self-referencing FK pattern, flat and non-nesting), and a superadmin-managed, typed metadata-key catalog (`biaticos`, `almuerzo`, `incentivo`, `asignacion`, etc.) whose values are assigned per-subordinate and must be filterable/sortable in Filament tables. Both features have direct, well-established precedent already in the codebase -- `coordinator_user_id` for the hierarchy shape, and `Gremio`/`Subcategoria` for the "superadmin-managed catalog + Filament CRUD resource" shape -- so no new core technology, package, or architectural pattern is required. Everything needed (MySQL 8.0.45 JSON columns, Laravel 12 JSON where/order clauses, Filament v4 `sortable(query:)`/custom filter closures) already exists in the current stack and is already used elsewhere in this codebase for structurally identical problems.

The recommended approach is: (1) a dedicated new `articulador_user_id` self-referencing FK on `users` -- never reuse or generalize `coordinator_user_id`, which is a narrowly-typed, heavily-consumed column referenced directly in ~6+ files and ~20 tests; (2) a new `metadata_keys` reference table (not a PHP enum, not config) with a `type` column (`numeric`/`string`) from day one, plus a `metadata` JSON column on `users` cast as `array`; (3) an `ArticuladorResource` that is a file-for-file mirror of `CoordinatorResource`; and (4) metadata filter/sort built via generated/indexed MySQL columns per catalog key rather than raw `JSON_EXTRACT`, to avoid full table scans on `users` -- the single most globally-queried table in the app.

The key risk is not technical novelty but **silent breakage of existing hierarchy-assumption code and silent data-integrity loss on money-like fields**. At least six existing surfaces (`TopLeadersTable`, `TopLeadersExport`, `LeadersExportController`, and others) hardcode "coordinator's own id = the scoping filter," and none of them know how to resolve an articulador's transitive team -- the failure mode is an empty dashboard with no error, not an exception. Separately, `AuditObserver` already auto-fires on `User` changes but only captures whole-column JSON diffs with actor-context campaign resolution, which is insufficient for "who set biaticos to X for whom, on which campaign" -- a real compliance gap for money-like data. Both risk classes are cheap to prevent in the schema/hierarchy phase and expensive (or impossible) to fully recover from after the fact, so the roadmap should treat "hierarchy call-site inventory" and "metadata audit/typing design" as blocking, early-phase work, not polish.

## Key Findings

### Recommended Stack

No new core technologies, packages, or dev tools are needed. The milestone is fully served by the existing stack: MySQL 8.0.45 native JSON column + JSON path querying, Laravel 12's `->`/`whereJsonContains`/`orderByRaw` JSON support, and Filament v4's `SelectFilter`/`Filter` custom `query()` closures and `TextColumn::sortable(query: Closure)` -- the exact same extension point already used in `CoordinatorsTable` for the `leaders_count` sort. A hand-built `metadata_keys` table (4-5 columns) replaces any need for a schemaless-attributes package; `spatie/laravel-schemaless-attributes` and `ptplugins/filament-auto-filters` were both evaluated and explicitly rejected as disproportionate for this milestone's scope (a handful of flat catalog keys).

**Core technologies:**
- MySQL 8.0.45 (confirmed via `SELECT VERSION()`) -- native `json` column + `JSON_EXTRACT`/path querying, comfortably above Laravel's MySQL 8.0+ minimum for JSON where/order clauses
- Laravel 12 query builder / Eloquent -- `->` JSON path operator, `whereJsonContains`, `orderByRaw`/`orderBy` for JSON paths -- no package required
- Filament v4 Tables -- `SelectFilter`/`Filter` with custom `query()`, `TextColumn::sortable(query: ...)` -- same mechanism already used for `leaders_count` in `CoordinatorsTable`

### Expected Features

**Must have (table stakes, P1 -- matches PROJECT.md's stated v1.2 scope):**
- `articulador` Spatie role + dedicated Filament resource mirroring `Coordinators`/`Leaders`
- `articulador_user_id` self-referencing FK on coordinador users -- no coordinador-to-coordinador nesting, no hard cap on coordinadores per articulador
- Superadmin CRUD for the metadata-key catalog, with a `data_type` field (numeric/text) shipped from day one even if only two types are used initially
- Per-subordinate metadata value assignment UI, scoped to the assigner's direct subordinates only (lider/coordinador/articulador)
- Filter and sort by metadata key/value in the relevant Filament listing tables (Users, Coordinators, Leaders, new Articuladores)
- Metadata value changes visible in the existing `AuditLogs` Filament resource -- but verify granularity (see Critical Pitfalls below)

**Should have (P2, natural fast-follow):**
- Bulk metadata assignment across multiple subordinates at once
- Additional catalog data types (date, select-with-options) beyond numeric/text
- CSV export of metadata alongside existing user/coordinator/leader exports

**Defer (v2+/P3):**
- Effective-dated/historical metadata values (point-in-time "what was this value on date X")
- Metadata rollup/aggregation dashboards (e.g., total biaticos per articulador's team)
- Deeper hierarchy nesting (articulador-of-articuladores) -- explicitly out of scope

**Explicit anti-features:** freeform/ad-hoc metadata keys, unlimited hierarchy depth, cross-campaign catalog/hierarchy sharing, hard-deleting metadata keys (soft-deactivate instead), and silent cascade of a coordinador's team when the coordinador is reassigned to a different articulador.

### Architecture Approach

Extend the existing single-table `User` model with two additive, independent pieces: a new self-referencing `articulador_user_id` FK (mirroring `coordinator_user_id`'s exact migration/relation shape) and a new `metadata` JSON column backed by a `metadata_keys` reference table (mirroring the `Gremio`/`Subcategoria` superadmin-catalog precedent). Neither touches `CampaignMembershipScope`, which is confirmed to be orthogonal to hierarchy -- campaign isolation is driven entirely by the `campaign_user` pivot, not by FK relationships. The build order matters: schema (3 additive migrations) -> role/model layer -> metadata catalog UI -> hierarchy UI (`ArticuladorResource` + `CoordinatorForm` addition) -> metadata assignment UI -> filter/sort surfaces, with an explicit decision checkpoint before further work on whether articulador needs a full self-service panel (mirroring `CoordinatorPanelProvider`) or just an admin-panel resource -- this is flagged as a materially larger, undecided scope item.

**Major components:**
1. `articulador_user_id` self-FK + `User::articulador()`/`coordinators()` relations -- new, additive, does not touch `coordinator_user_id`
2. `metadata_keys` table + `MetadataKey` model + Filament CRUD resource (copies `GremioResource` shape) -- superadmin-only catalog management
3. `ArticuladorResource` (mirrors `CoordinatorResource` file-for-file) + `CoordinatorForm` addition for the new `articulador_user_id` select
4. Metadata filter/sort layer built dynamically from active catalog rows into `TextColumn`/`Filter` closures across `UsersTable`, `CoordinatorsTable`, `LeadersTable`, `ArticuladoresTable`
5. **Open decision:** whether articulador gets a dedicated self-service Filament panel (like `coordinator`/`leader` today) or admin-panel-only access -- this determines whether a new `ArticuladorPanelProvider` + Livewire self-service views are in scope

### Critical Pitfalls

1. **~6 existing surfaces hardcode "coordinator's own id = team scope"** (`TopLeadersTable`, `TopLeadersExport`, `LeadersExportController`, and others) and will silently return empty results for articuladores instead of erroring. Centralize a "resolve my managed coordinador/leader ids" helper before touching any of these, and inventory every call site with `grep -rn "coordinator_user_id.*Auth::\|hasRole(UserRole::COORDINATOR"`.
2. **Never reuse/overload `coordinator_user_id` for the articulador link.** It already has role-dependent meaning (NULL for plain coordinators, self-referencing for `also_leader` coordinators, "my coordinator" for leaders); collapsing articulador onto it would make one column mean three different things and poison every existing `leaders()`/`coordinator()` relation call. Use a dedicated `articulador_user_id` FK.
3. **No `UserPolicy`/`CoordinatorPolicy` exists today** -- authorization is implicit in `getEloquentQuery()` scoping only. A naive articulador-facing coordinador resource that copies `CoordinatorResource::getEloquentQuery()` verbatim (no owner filter) would let every articulador see/edit every coordinador in the campaign. Add an explicit ownership scope + a real policy layer, and re-verify scoping independently on every hand-rolled `Select`/relation-manager, not just the top-level resource query.
4. **Unindexed `JSON_EXTRACT` filter/sort will full-table-scan `users`** -- the most globally-queried table in the app (every panel, every widget touches it via `CampaignMembershipScope`). Use MySQL generated/indexed columns per filterable catalog key, verified with `EXPLAIN`, not raw JSON path queries in `orderByRaw`/`whereRaw`.
5. **The existing `AuditObserver` is insufficient for money-like metadata provenance.** It captures whole-column JSON diffs (not per-key) and resolves `campaign_id` from the *actor's* active campaign context, not the *subordinate's* campaign -- wrong for cross-context edits by a super_admin. Design a dedicated per-key audit path (mirroring `campaign_user.assigned_at`/`assigned_by`) in the same phase as the metadata schema; retrofitting per-key attribution after whole-column diffs are already in production is the single most expensive recovery in this research (data cannot be reconstructed after the fact).

Two more pitfalls worth flagging for planning even though not in the "top 5": (a) whole-column read-modify-write on the `metadata` JSON blob creates a lost-update race when two different superiors edit the same subordinate concurrently (prefer `JSON_SET()`-based atomic per-key updates); (b) untyped JSON values for money-like keys (`biaticos`) sort lexicographically, not numerically, unless the catalog enforces a declared type and the generated column casts accordingly -- a `type` column on `metadata_keys` from day one prevents this cheaply.

## Implications for Roadmap

Based on combined research, suggested phase structure:

### Phase 1: Hierarchy & Metadata Schema Foundation
**Rationale:** Both new features start with additive, non-destructive migrations and model/relation layer work. This must land before any Filament UI can scope a `Select` against the `articulador` role or read/write `metadata`. Pitfalls 1, 2, 6, 7, 8, and 9 are all schema/data-model decisions that are cheap to get right now and expensive (or impossible) to fully recover from later -- this phase is where those calls get made, not deferred.
**Delivers:** `articulador_user_id` FK + relations, `metadata_keys` table (with `type` column) + `metadata` JSON column on `users`, `UserRole::ARTICULADOR` enum case, `canAccessPanel()` decision wired, dual role-storage sync (Spatie + `campaign_user.role_id`) tested.
**Addresses:** Table-stakes hierarchy tier + typed catalog foundation from FEATURES.md.
**Avoids:** Pitfalls 2 (overloaded FK), 4 (dual role storage drift), 5 (role with no panel access), 9 (untyped money fields).

### Phase 2: Hierarchy Call-Site Audit & Authorization Layer
**Rationale:** Before any new UI is built, the existing coordinator-scoped call sites (dashboards, exports, widgets) must be inventoried and centralized, and an explicit policy layer must exist for articulador-to-coordinador ownership -- otherwise the first UI phase will ship on top of silently-broken or silently-overscoped foundations. Architecture research explicitly separates "authorization/policy phase" from "UI phase" as a hard requirement, not a nice-to-have.
**Delivers:** Centralized "resolve my managed coordinador/leader ids" helper, updated `TopLeadersTable`/`TopLeadersExport`/`LeadersExportController` (and equivalents), a `UserPolicy` (or scoped policy) enforcing hierarchy ownership independent of any single resource's query.
**Addresses:** Ownership-scoped CRUD (table stakes) from FEATURES.md.
**Avoids:** Pitfalls 1 (silent empty-dashboard breakage) and 3 (missing policy layer / cross-tenant leak).

### Phase 3: Articulador Resource & Metadata Catalog UI
**Rationale:** With schema, roles, and authorization settled, the Filament-facing CRUD work is now low-risk mirroring of established patterns (`CoordinatorResource` -> `ArticuladorResource`, `GremioResource` -> `MetadataKeyResource`). This phase resolves the open architecture question (self-service articulador panel vs. admin-only resource) as a deliberate decision before building it.
**Delivers:** `ArticuladorResource` (full CRUD, campaign-scoped), `MetadataKeyResource` (superadmin-only catalog CRUD), `CoordinatorForm` addition for `articulador_user_id` select, decision on self-service panel scope.
**Uses:** MySQL JSON column + `array` cast, Filament v4 resource/form/table conventions from STACK.md and ARCHITECTURE.md.

### Phase 4: Metadata Assignment & Provenance
**Rationale:** Assignment UI depends on the catalog existing (Phase 1/3) and the ownership-scoping pattern (Phase 2). This is where the audit/concurrency pitfalls (7, 8) must be closed -- building assignment before provenance design is the exact trap PITFALLS.md flags as unrecoverable.
**Delivers:** Per-subordinate metadata assignment UI (dynamic per-key inputs, scoped to the assigner's direct subordinates), atomic per-key writes (avoiding whole-column read-modify-write races), a dedicated audit trail with correct campaign attribution (subordinate's campaign, not actor's).
**Implements:** Data-access-layer decisions from ARCHITECTURE.md Q2/Q4 and PITFALLS.md 7/8.

### Phase 5: Filter/Sort Surfaces & Performance Verification
**Rationale:** Filtering/sorting is explicitly the highest-complexity table-stakes requirement and depends on real assignable data existing first (no point filtering an always-empty column). Generated/indexed columns must be verified with `EXPLAIN` before shipping, per pitfall research -- this is the last phase precisely because retrofitting indexed columns after the filter UI ships means re-touching every filter definition a second time.
**Delivers:** Indexed generated columns per filterable catalog key, `SelectFilter`/`TextColumn::sortable(query:)` wiring across `UsersTable`, `CoordinatorsTable`, `LeadersTable`, `ArticuladoresTable`, `EXPLAIN`-verified query plans.
**Addresses:** "Filter and sort by metadata key/value" -- the explicit, non-negotiable requirement from PROJECT.md.
**Avoids:** Pitfall 6 (unindexed JSON full-table scan on `users`).

### Phase Ordering Rationale

- Schema-first ordering is dictated by dependency chains discovered in ARCHITECTURE.md's "Recommended Build Order" -- role/relation layer must exist before any Filament form can reference it, and the catalog must be populated before assignment UI has anything to render.
- Separating "hierarchy call-site audit + authorization" into its own phase (before new UI) directly reflects PITFALLS.md's explicit instruction: "Authorization/policy phase, explicitly separated from the UI phase."
- Metadata provenance/typing is placed before assignment UI ships (not after) because PITFALLS.md identifies whole-column-diff-only audit history as the single highest-cost-to-recover-from mistake in this research -- per-key attribution cannot be reconstructed retroactively once mixed writes have occurred.
- Filter/sort performance work is deliberately last because it depends on real data (Phase 4) and because retrofitting indexed generated columns after a filter UI ships doubles the work.

### Research Flags

Phases likely needing deeper research during planning:
- **Phase 3 (Articulador Resource):** the "self-service panel vs. admin-only resource" decision is explicitly flagged as unresolved in ARCHITECTURE.md -- this is a product/scope decision, not a technical unknown, but its answer materially changes the phase's size and should be confirmed with the requester before planning locks it in.
- **Phase 5 (Filter/Sort):** generated-column-per-key approach needs to be validated with `EXPLAIN` against realistic data volumes for this specific project's scale before committing to the exact indexing strategy -- flagged as MEDIUM-HIGH confidence in STACK.md pending real-data verification via `tinker`/`database-query`.

Phases with standard patterns (skip research-phase):
- **Phase 1 (Schema):** direct mirror of existing, already-shipped migrations (`add_coordinator_to_users_table`, `Gremio`/`Subcategoria` catalog pattern) -- HIGH confidence, no new research needed.
- **Phase 2 (Authorization):** pattern is well-understood (Filament `getEloquentQuery()` scoping + policy), the gap is applying it consistently, not discovering how -- LOW research need.
- **Phase 4 (Metadata Assignment):** `JSON_SET()` atomic updates and Filament form dehydration patterns are standard, documented Laravel/Filament mechanics.

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | All findings verified against official Laravel 12.x docs, confirmed project MySQL version via direct `SELECT VERSION()`, and cross-checked against existing codebase patterns (`CoordinatorsTable` sortable query). No new dependencies needed. |
| Features | MEDIUM-HIGH | Org-hierarchy and EAV/metadata patterns are well-documented industry practice; SIGMA-specific integration points verified directly against the codebase (HIGH). External sources are mostly vendor/community content (MEDIUM) rather than primary standards. |
| Architecture | HIGH | Every recommendation is sourced directly from reading the actual SIGMA codebase (`User.php`, `CampaignMembershipScope`, `CoordinatorResource`, `Gremio`, panel providers) rather than generic assumptions; the one genuinely open question (self-service panel scope) is explicitly flagged as a decision, not a gap. |
| Pitfalls | HIGH | Every pitfall cites the exact existing file(s)/line(s) where the risk pattern already lives in this codebase, not generic Laravel/Filament advice. Cross-checked against official MySQL docs for the JSON-indexing claims. |

**Overall confidence:** HIGH

### Gaps to Address

- **Self-service articulador panel scope is unresolved.** PROJECT.md's stated goal ("articuladores organize a set of coordinadores") could mean admin-resource-only or a full self-service panel mirroring `CoordinatorPanelProvider`. This must be resolved explicitly during roadmap/requirements definition, not assumed -- it's the single biggest scope swing in the whole milestone.
- **No existing regression test asserts "coordinador A cannot see coordinador B's leaders across different articuladores"** -- the equivalent test doesn't exist today for the coordinator-to-leader boundary either. This should be added as new baseline coverage as part of this milestone, not treated as pre-existing safety net.
- **JSON `orderBy` support is MEDIUM-HIGH (not HIGH) confidence specifically for the `orderBy` (not `where`) case** -- Laravel's docs don't separately demonstrate JSON-path `orderBy`, though it shares grammar infrastructure with `where`. Verify directly via `tinker`/`database-query` against real project data before relying on it in Phase 5.
- **Real campaign-scale data volumes for `users`** were not directly measured in this research (referenced only by analogy to `Voter`/`Apoyo` table scale). Confirm actual row counts before finalizing whether generated/indexed columns are strictly necessary at current scale or can be sequenced slightly later.

## Sources

### Primary (HIGH confidence)
- [Laravel 12.x Query Builder docs -- JSON Where Clauses, Updating JSON Columns, Ordering](https://laravel.com/docs/12.x/queries)
- Direct project verification: `SELECT VERSION()` against connected MySQL DB -> `8.0.45`
- Direct SIGMA codebase inspection: `app/Models/User.php`, `app/Models/CampaignUser.php`, `app/Models/Scopes/CampaignMembershipScope.php`, `app/Models/Concerns/HasCampaignMembershipScope.php`, `app/Filament/Resources/Coordinators/*`, `app/Filament/Resources/Leaders/*`, `app/Filament/Resources/Gremios/*`, `app/Filament/Widgets/TopLeadersTable.php`, `app/Filament/Widgets/TopCoordinatorsTable.php`, `app/Exports/TopLeadersExport.php`, `app/Http/Controllers/Coordinator/LeadersExportController.php`, `app/Observers/AuditObserver.php`, `app/Providers/Filament/AdminPanelProvider.php`, `app/Providers/Filament/CoordinatorPanelProvider.php`, `database/migrations/2026_01_21_000002_add_coordinator_to_users_table.php`, `.planning/PROJECT.md`
- [MySQL 8.4 Reference Manual: Secondary Indexes and Generated Columns](https://dev.mysql.com/doc/refman/8.4/en/create-table-secondary-indexes.html)
- [MySQL: Indexing JSON documents via Virtual Columns (official blog)](https://dev.mysql.com/blog-archive/indexing-json-documents-via-virtual-columns/)
- [Service Territory Fields for Field Service - Salesforce Help](https://help.salesforce.com/s/articleView?id=service.fs_territory_fields.htm&language=en_US&type=5)

### Secondary (MEDIUM confidence)
- [Kirschbaum -- Optimizing, sorting, and filtering JSON Columns in Laravel with Indexed Virtual Columns](https://kirschbaumdevelopment.com/insights/optimizing-json-columns-in-laravel)
- [Laravel Custom Fields: JSON, EAV Model, or Same Table - Laravel Daily](https://laraveldaily.com/post/laravel-custom-fields-json-eav-model-same-table)
- [Political Campaign Organizational Structure Guide - Aristotle](https://www.aristotle.com/campaign-guide/2023/08/political-campaign-organizational-structure-guide/)
- [Sponsor Change In MLM - HybridMLM](https://www.hybridmlm.io/blogs/sponsor-change-in-mlm-what-it-means-and-why-the-right-software-matters/)
- [7 Salesforce Territory Management Best Practices - TractionComplete](https://tractioncomplete.com/articles/salesforce-territory-management-best-practices/)
- [A Practical Guide to Indexing JSON in MySQL -- Pipedrive Engineering](https://medium.com/pipedrive-engineering/a-practical-guide-to-indexing-json-in-mysql-dccf10586204)
- [How to index JSON columns using MySQL -- Vlad Mihalcea](https://vladmihalcea.com/index-json-columns-mysql/)

### Tertiary (LOW confidence)
- General knowledge of MySQL `JSON_SET()` atomic partial updates and Laravel optimistic-locking patterns -- training-data-based, not independently re-verified against a specific MySQL version doc page in this session; validate directly before relying on it in Phase 4.

---
*Research completed: 2026-08-10*
*Ready for roadmap: yes*

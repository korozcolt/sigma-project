# Feature Research

**Domain:** Hierarchical field-operations / campaign-organizing platforms (canvassing tools, political campaign management, MLM/multi-level org structures, franchise & territory management software), applied to SIGMA's v1.2 milestone (new `articulador` tier + superadmin-catalog metadata key/value assignments)
**Researched:** 2026-08-10
**Confidence:** MEDIUM-HIGH (org-hierarchy patterns are well-documented across real campaign structures and CRM/field-service tooling; metadata/EAV typing and audit patterns are well-established engineering practice; SIGMA-specific integration points are HIGH confidence, verified directly against the current codebase)

## Feature Landscape

### Table Stakes (Users Expect These)

Features users assume exist once you introduce an additional organizational tier and a per-person attribute system. Missing these makes the feature feel unfinished or unsafe (unsafe matters more here, since values represent real money).

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Additional hierarchy tier that mirrors the existing self-referencing pattern (`articulador` → `coordinador`, one level up from today's `coordinador` → `líder`) | Real-world campaign orgs already have this exact tier ("articulador político" managing several coordinators); campaign field-org software universally supports Field Director → Regional/Area Coordinator → Field Organizer → Canvasser chains, i.e. 3+ tiers is the norm, not the exception | LOW-MEDIUM | SIGMA already has the `coordinator_user_id` self-FK + `coordinator()`/`leaders()` Eloquent relations on `User` (`app/Models/User.php`). Adding `articulador_user_id` (or reusing a generalized `superior_user_id` naming) on `coordinador` rows is a direct, low-risk extension of an existing pattern — not a new architectural concept. |
| New Spatie role (`articulador`) with its own Filament panel/resource scoping, same as `coordinator`/`leader` today | Every role in SIGMA today (`super_admin`, `admin_campaign`, `coordinator`, `leader`, `reviewer`, `reports_viewer`) gets a dedicated Filament resource + campaign-scoped table; users will expect articuladores to behave consistently with that convention | LOW-MEDIUM | Mirror `app/Filament/Resources/Coordinators/*` structure for `Articuladores`. Must integrate with `HasCampaignMembershipScope` trait and the existing campaign-isolation enforcement (`campaign_user` pivot with `role_id`). |
| Articulador can create/manage a bounded set of coordinadores (CRUD scoped to "my coordinadores") | This is literally the requested feature and matches how every reviewed hierarchical field-org tool scopes "manage the tier directly below me, not further down" | MEDIUM | No hard cap requested for v1.2 ("no hard limit enforced" per PROJECT.md), but the UI/query layer must filter coordinadores to `WHERE articulador_user_id = auth()->id()` the same way leader-scoping works today for coordinators. |
| No coordinador→coordinador nesting, no articulador→articulador nesting (flat one-level-per-tier, same as today's coordinator→leader) | Explicitly decided in PROJECT.md; every MLM/franchise system that supports arbitrary-depth nesting pays a permanent complexity tax (recursive queries, unbounded reporting rollups) that campaign field-ops tools deliberately avoid by capping depth | LOW | Enforce at the form/validation layer (a coordinador cannot be assigned as another coordinador's superior) — cheap guardrail, do not skip it. |
| Superadmin-only CRUD for the metadata key catalog (create/rename/deactivate keys like `biaticos`, `almuerzo`, `incentivo`, `asignacion`) | "Predefined, not freeform" is explicit in PROJECT.md; every EAV/custom-field system reviewed (Laravel schemaless-attributes, EAV packages, Salesforce custom fields) separates "who defines the schema" from "who assigns values," and definer is always a higher-trust role than assigner | LOW | New small reference table (e.g. `user_metadata_keys`: `key`, `label`, `data_type`, `is_active`) — this is the "predefined catalog" piece and should NOT be a freeform strings-only JSON schema-less blob at the definition layer, even though the *values* land in JSON. |
| Superior assigns key→value pairs to a direct subordinate only (líder/coordinador/articulador scoped to who they actually manage) | Matches existing campaign-isolation and ownership-boundary conventions (coordinators/leaders already can't touch users outside their scope) | MEDIUM | Reuses existing ownership-check patterns already hardened in v1.0/v1.1 (the PERM-02 gap closure taught SIGMA to name *why* an authorization failure happened — apply the same discipline here: "you can only assign metadata to your own subordinates"). |
| Filterable/sortable metadata columns in Filament user/coordinator/leader/articulador listing tables | Explicitly required in PROJECT.md ("Filter and sort by metadata key/value in the Filament tables") | MEDIUM-HIGH | This is the trickiest "table stakes" item technically. Filament's native `TextColumn`/`SelectFilter` sort/filter machinery works against real columns or simple relationships, not arbitrary JSON keys out of the box. Needs either (a) a virtual per-key column built with `Str::json_extract` compatible SQL for MySQL (`JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.biaticos'))`), or (b) materializing values into a normalized `user_metadata_values` table (`user_id`, `key_id`, `value`) which is trivially filterable/sortable/joinable and is what most production EAV-over-Filament implementations converge on once "filter and sort" is a hard requirement, not "display only." |
| Audit trail on metadata value changes (who set/changed/removed a `biaticos` value and when) | These key/values represent real compensation paid to real people (per-diem, lunch, incentive pay) — every payroll-adjacent system in the reviewed literature (MLM commission tooling, Salesforce field history) treats this as non-negotiable, not a nice-to-have | LOW (if reusing existing infra) | **SIGMA already has this.** `App\Observers\AuditObserver` is registered on the `User` model (`app/Providers/AppServiceProvider.php:68`) and generically diffs `old_values`/`new_values` into `AuditLog` (morphable, campaign-scoped) on every `updated()` event. If metadata lands as a JSON column directly on `users`, changes are audited automatically — but only as a whole-column diff (old full JSON blob vs new full JSON blob), not a per-key structured entry. If metadata instead lives in a normalized `user_metadata_values` table, that table needs its own `AuditLog::observe()` registration to get equivalent coverage — don't assume it's free in that design. |

### Differentiators (Competitive Advantage)

Not required for launch, but align with SIGMA's stated core value ("trustworthy, campaign-safe data and clear operational traceability").

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Typed metadata values (numeric for `biaticos`/`almuerzo`/`incentivo`, text for `asignacion`) with type enforced at the catalog-definition level, not just at input time | Generic EAV/JSON-blob systems that store everything as strings create silent data-quality debt (a `biaticos` value of `"50000 aprox"` breaks every future SUM/report). Typed catalog entries let Filament render the right input (number vs select vs text) and let reporting trust the values | MEDIUM | Add `data_type` enum (`numeric`, `text`, `date`, `select`) to the metadata-key catalog table now, even if v1.2 only ships `numeric` + `text`, so a later "sum all biáticos paid this month" report doesn't require a schema migration. This is the single highest-leverage differentiator relative to effort — costs almost nothing extra at catalog-definition time and prevents having to retrofit typing after real money data exists in a stringly-typed JSON blob. |
| Historical/point-in-time metadata (know what a leader's `biaticos` value *was* on a given date, not just what it is now) | Compensation values change over time (a per-diem rate that changes month to month); "current value only" JSON columns silently lose this the moment a superior updates a value, and the generic `AuditLog` diff only helps if someone thinks to go read it — it's not queryable as "give me all biaticos values as of March" | MEDIUM-HIGH | Defer unless the client explicitly asks for time-series reporting on stipends. The audit-log fallback (already free via `AuditObserver`) is "good enough" forensic coverage for v1.2; a first-class effective-dated metadata-values table is a real differentiator but is scope the milestone didn't ask for. Flag as a strong Phase 2 candidate if reporting on paid amounts ever becomes a requirement. |
| Bulk metadata assignment (superior applies the same value to all/many subordinates at once — e.g., "set `almuerzo = 15000` for every leader under me") | Coordinators/articuladores managing dozens of subordinates will find one-at-a-time assignment tedious very quickly, and SIGMA already has bulk patterns (admin-only CSV bulk import for Apoyos) | MEDIUM | Natural fast-follow, not MVP — the milestone's stated target features describe per-subordinate assignment via UI, not bulk. Note the dependency: bulk operations need the same ownership-scoping guard as single assignment (can't bulk-assign outside your own subordinate set). |
| Metadata rollups/aggregation surfaced on existing dashboards or reports (e.g., total `biaticos` committed per articulador's coordinador team) | This is where the "operational command center" value proposition from PROJECT.md would actually pay off — turning stipend assignments into a real budget-visibility tool, similar to how MLM commission dashboards roll payout data up the sponsor tree | MEDIUM-HIGH | Depends on typed values (numeric keys must be summable) and on the hierarchy being queryable (articulador → coordinador → líder chain). Don't build until typed metadata + hierarchy both ship — this is explicitly a v2+ item relative to the stated v1.2 scope. |

### Anti-Features (Commonly Requested, Often Problematic)

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|------------------|-------------|
| Freeform/ad-hoc metadata keys (letting a coordinador or líder invent their own key on the fly, e.g. "type any label you want") | Feels more flexible, avoids asking superadmin to pre-create every key | Directly contradicts the explicit PROJECT.md decision ("Superadmin-managed predefined catalog... not freeform"). Also the exact failure mode every EAV-pattern writeup warns about: uncontrolled key sprawl (`biatico`, `Biaticos`, `biaticos `, `viaticos`) makes filtering/sorting/reporting worthless within weeks | Superadmin-curated catalog only; if a subordinate needs a new attribute type, that's a request to superadmin to add a catalog entry — same trust model as everything else in SIGMA (roles, territorial structures) being centrally administered |
| Unlimited hierarchy depth (letting articuladores manage other articuladores, or coordinadores manage coordinadores) | "More flexible org modeling," mirrors real MLM/franchise depth | Every unbounded-depth system in the reviewed literature (MLM genealogy software) pays for it with recursive-query complexity, cascade-reassignment ambiguity, and reporting rollups that must handle arbitrary depth instead of a known 3-tier shape. SIGMA's entire existing hierarchy is deliberately flat (coordinator→leader, no coordinator nesting) and PROJECT.md explicitly rules this out for v1.2 ("one extra hierarchy level, no further nesting") | Keep the fixed 3-tier shape: articulador → coordinador → líder → apoyo. If a genuine need for deeper nesting emerges later, that's a distinct future milestone, not a v1.2 scope-creep item |
| Cross-campaign metadata catalog sharing or cross-campaign hierarchy (an articulador managing coordinadores across two campaigns) | Feels efficient for admins who run multiple campaigns | Directly violates SIGMA's hardened campaign-isolation default (strict by default per PROJECT.md, the one deliberate exception being the duplicates report). Reusing this pattern here would reopen the exact class of trust-breaking bug the platform spent phases 1-5 + 05.1 closing | The metadata-key catalog can be superadmin-defined once (global reference data, like `polling_places`), but *assignments* (key→value on a specific user) and the hierarchy relation must stay campaign-scoped exactly like `coordinator_user_id` does today |
| Deleting a metadata key from the catalog once it has been used (hard delete) | Seems like normal "remove what you don't need" catalog hygiene | Money/compensation semantics: a hard-deleted key silently erases historical context for what a `biaticos` value on some user's record even meant, and breaks any past `AuditLog` entries that referenced it by name only (or worse, orphans metadata values still stored against a JSON key that no longer resolves to a label) | Soft-deactivate keys (`is_active = false`) instead of hard delete; deactivated keys stop appearing for new assignment but remain resolvable for historical display/audit/reporting |
| Silent cascade of a coordinador's leaders/apoyos when the coordinador is reassigned to a different articulador | Feels like the "obvious" default behavior — reassign the coordinador, everything under them just follows | Territorial/CRM reassignment literature (Salesforce territory management) is consistent: reassigning an owner higher up the chain is a deliberate, visible action with review/notification, not a silent side effect, because the people affected (and their compensation/attribution history) need traceability. For SIGMA specifically, a coordinador's leaders and their apoyos are NOT touched by an articulador reassignment (líderes stay attached to their coordinador via `coordinator_user_id`, unaffected) — but the articulador-level rollup (which articulador "owns" that coordinador's team for reporting purposes) changes, and that transition itself should be an explicit, audited action, not incidental | Reassigning a coordinador's `articulador_user_id` is itself a first-class, audited action (already covered for free by `AuditObserver` on `User` since it's a column change) — no automatic downstream cascade to leaders/apoyos is needed because they don't have an `articulador_user_id` FK at all in the described data model; the hierarchy is queried by walking `coordinador → articulador`, not denormalized onto every leader/apoyo row |

## Feature Dependencies

```
[Articulador Spatie role + Filament panel/resource]
    └──requires──> [Existing coordinator/leader Filament resource pattern as template]
    └──requires──> [Existing campaign-isolation scoping (HasCampaignMembershipScope, campaign_user pivot)]

[articulador_user_id FK on coordinador users]
    └──requires──> [Existing coordinator_user_id self-referencing FK pattern on User model]
    └──enables──> [Articulador-scoped "my coordinadores" CRUD]
    └──enables──> [Reassignment-as-audited-action anti-cascade design]

[Superadmin metadata-key catalog (typed)]
    └──requires──> [New reference table: key, label, data_type, is_active]
    └──enables──> [Per-subordinate metadata assignment UI]
    └──enables──> [Typed input rendering in Filament forms]

[Per-subordinate metadata assignment]
    └──requires──> [Superadmin metadata-key catalog]
    └──requires──> [Existing ownership-scoping pattern (superior can only touch own direct subordinates)]
    └──enables──> [Filterable/sortable metadata columns in listings]

[Filterable/sortable metadata columns]
    └──requires──> [Per-subordinate metadata assignment]
    └──requires──(if JSON-on-users chosen)──> [DB-level JSON path querying, MySQL JSON_EXTRACT]
    └──requires──(if normalized table chosen)──> [user_metadata_values table with FK to catalog + user]

[Audit trail on metadata changes]
    └──enhances──> [Per-subordinate metadata assignment]
    └──already-satisfied-by──> [Existing AuditObserver registered on User model] (IF metadata stored as JSON column directly on users)
    └──requires-new-registration──> [AuditObserver on user_metadata_values table] (IF normalized-table design chosen instead)

[Bulk metadata assignment] ──enhances──> [Per-subordinate metadata assignment]
[Metadata rollups/reporting] ──requires──> [Typed metadata values] AND ──requires──> [Articulador hierarchy queryable]

[Freeform metadata keys] ──conflicts──> [Superadmin-predefined catalog] (explicitly ruled out)
[Unlimited hierarchy depth] ──conflicts──> [Flat one-extra-tier design] (explicitly ruled out)
```

### Dependency Notes

- **Filterable/sortable columns require a storage-design decision made early:** the milestone's stated target ("JSON metadata column on `users`") satisfies simple *display* and even basic filtering via JSON path predicates in MySQL, but *sorting* by an arbitrary JSON key across a paginated Filament table is materially harder to get right (and to keep performant) than sorting a real column. This is the one place where the stated target architecture and the "filter and sort" requirement are in mild tension — worth a deliberate call (accept JSON-path query complexity vs. add a thin normalized values table) before phase planning locks it in.
- **Audit coverage differs by storage design:** JSON-on-`users` gets audit trail "for free" via the already-registered `AuditObserver`, but only as an opaque whole-blob diff. A normalized `user_metadata_values` table gives per-key audit granularity but requires deliberately wiring up its own observer — don't assume parity without checking.
- **Reassignment does not cascade** to leaders/apoyos because they don't hold an `articulador_user_id` — the hierarchy is walked (coordinador → articulador), not denormalized. This keeps the reassignment operation cheap and matches the flat, no-nesting design decision already locked into PROJECT.md.
- **Campaign isolation applies to the assignment/hierarchy relation, not necessarily the catalog definition.** The catalog (`biaticos`, `almuerzo`, etc.) can reasonably be global reference data (superadmin-defined once, like `polling_places`), but every actual key→value assignment on a user and every `articulador_user_id` relation must stay strictly campaign-scoped — this is a place a future implementer could accidentally leak scope by treating "the catalog is global" as "assignments are global too."

## MVP Definition

### Launch With (v1.2, per PROJECT.md's stated target features)

- [ ] `articulador` Spatie role + dedicated Filament resource (mirrors Coordinators/Leaders resource structure) — the milestone's headline feature
- [ ] `articulador_user_id` self-referencing FK relation on coordinador users (mirrors `coordinator_user_id`) — no coordinador→coordinador nesting, no hard limit on coordinadores per articulador
- [ ] Superadmin CRUD for the metadata-key catalog, with `data_type` field even if only `numeric`/`text` ship first — cheap now, expensive to retrofit later
- [ ] Per-subordinate metadata value assignment UI (líder/coordinador/articulador as the assignable target, scoped to the assigner's direct subordinates only) — enforced with the same ownership-boundary discipline already hardened elsewhere in SIGMA (PERM-02 pattern: explain *why* denied)
- [ ] Filter and sort by metadata key/value in the relevant Filament user-listing tables — explicitly required; requires the storage-design decision above to be made deliberately, not defaulted
- [ ] Metadata value changes visible in the existing `AuditLogs` Filament resource (verify JSON-column diffs render usefully there, or confirm normalized-table observer wiring if that design is chosen)

### Add After Validation (v1.x)

- [ ] Bulk metadata assignment across multiple subordinates at once — add once single-assignment UX is proven and superiors start asking for it
- [ ] Additional catalog `data_type`s (date, select-with-options) beyond numeric/text — add when a real key needs it, not speculatively
- [ ] CSV export of metadata alongside existing user/coordinator/leader exports — add if reporting/finance teams need it offline (mirrors the existing admin-only CSV bulk-import pattern for Apoyos)

### Future Consideration (v2+)

- [ ] Effective-dated / historical metadata values (point-in-time "what was this value on date X") beyond what the generic audit log provides — defer until compensation reporting over time is an explicit requirement
- [ ] Metadata-based rollups/aggregation on dashboards (e.g., total biáticos committed per articulador's team) — defer until typed values + hierarchy are both stable in production
- [ ] Deeper hierarchy nesting (articulador-of-articuladores) — explicitly out of scope per PROJECT.md; would require a different data model (adjacency list or path-based) than the flat FK approach that fits v1.2

## Feature Prioritization Matrix

| Feature | User Value | Implementation Cost | Priority |
|---------|------------|----------------------|----------|
| Articulador role + resource + hierarchy FK | HIGH | MEDIUM | P1 |
| Superadmin typed metadata-key catalog | HIGH | LOW | P1 |
| Per-subordinate metadata assignment (scoped) | HIGH | MEDIUM | P1 |
| Filter/sort by metadata in listings | HIGH | MEDIUM-HIGH | P1 |
| Audit visibility for metadata changes | MEDIUM-HIGH | LOW (if JSON-on-users) / MEDIUM (if normalized) | P1 |
| Bulk metadata assignment | MEDIUM | MEDIUM | P2 |
| Extra data types (date/select) | LOW-MEDIUM | LOW | P2 |
| Metadata CSV export | MEDIUM | LOW-MEDIUM | P2 |
| Historical/effective-dated metadata | MEDIUM | HIGH | P3 |
| Metadata rollup dashboards | MEDIUM | MEDIUM-HIGH | P3 |
| Articulador-of-articuladores nesting | LOW (out of scope) | HIGH | Not planned |

**Priority key:**
- P1: Must have for v1.2 launch (matches PROJECT.md's stated target features)
- P2: Should have, natural fast-follow once P1 is proven in production
- P3: Nice to have, defer until a concrete trigger (reporting need, compensation-over-time requirement)

## Competitor / Analog Feature Analysis

| Feature | Political campaign field-org software (Field Director → Regional/Area Coordinator → Field Organizer → Canvasser) | MLM/multi-level org platforms | Salesforce territory/field-service management | Our approach (SIGMA v1.2) |
|---------|---------------------------------------------------------------------------------------------------------------|-------------------------------|-------------------------------------------------|----------------------------|
| Extra tier above existing "manager of workers" role | Standard: regional tier supervises multiple local field organizers | Standard: sponsor tree, typically capped/structured levels ("unilevel" plans) | Standard: territory hierarchies, some territories purely for grouping | Add `articulador` above `coordinador`, single extra flat tier, no further nesting — matches the "capped depth" pattern both campaign orgs and disciplined MLM/territory tools converge on |
| Per-person operational/compensation attributes | Ad hoc (spreadsheets, HR systems outside the canvassing tool itself) — rarely a first-class feature in canvassing software | Core feature: commission/bonus structures, often with audit and payout-cycle protections against retroactive changes | Custom fields with field-history tracking available as an add-on, not default | Superadmin-typed catalog + per-subordinate JSON/normalized values + reuse of SIGMA's existing generic `AuditLog` — closer to the MLM commission-tooling rigor (typed, audited) than the "ad hoc spreadsheet" norm in campaign tools specifically |
| Reassignment of a mid-tier manager to a different superior | Explicit, reviewed action (regional director change is an HR/ops event, not silent) | Explicit sponsor-change workflow with approval gates, notifications, and safeguards against abuse/gaming | Explicit reassignment via bulk-update tooling, cascades intentionally to owned records | Reassigning `articulador_user_id` on a coordinador is a first-class audited action (free via existing `AuditObserver`); no silent cascade to leaders/apoyos since they don't hold that FK |
| Predefined vs. freeform custom attributes | N/A (rarely modeled) | Mixed — commission *types* are usually predefined by the compensation plan, not freeform per-distributor | Freeform for admins (any user can create custom fields) unless locked down by profile | Predefined only, superadmin-owned catalog — deliberately stricter than default Salesforce behavior, matching SIGMA's existing "centrally administered reference data" convention (roles, territorial structures, polling places) |

## Sources

- [Regional Organizer - Idealist](https://www.idealist.org/en/nonprofit-job/f1f1602f5b034ab0b920a954dacc9141-regional-organizer-virginians-for-reproductive-freedom-richmond) — MEDIUM confidence, job posting describing real campaign org structure
- [Field Organizer role breakdown - CallHub](https://callhub.io/blog/campaign-organizing/field-organizer-job-responsibilities/) — MEDIUM confidence, canvassing-software vendor content
- [Regional Organizing Director - NC House Dems](https://nchousedems.com/regional-organizing-director/) — MEDIUM confidence, real campaign hierarchy description
- [Political Campaign Organizational Structure Guide - Aristotle](https://www.aristotle.com/campaign-guide/2023/08/political-campaign-organizational-structure-guide/) — MEDIUM confidence, campaign-management vendor content describing Field Director → Regional → Field Organizer → Canvasser chain
- [Political Campaign Staff - Numero](https://www.numero.ai/blog/campaign-staff/) — MEDIUM confidence
- [Laravel Custom Fields: JSON, EAV Model, or Same Table - Laravel Daily](https://laraveldaily.com/post/laravel-custom-fields-json-eav-model-same-table) — MEDIUM-HIGH confidence, widely cited Laravel-ecosystem reference on this exact tradeoff
- [Spatie Laravel-schemaless-attributes](https://github.com/spatie/Laravel-schemaless-attributes) — HIGH confidence, official package docs, JSON-column approach precedent
- [Sponsor Change In MLM - HybridMLM](https://www.hybridmlm.io/blogs/sponsor-change-in-mlm-what-it-means-and-why-the-right-software-matters/) — MEDIUM confidence, MLM-software vendor content on reassignment/cascade safeguards
- [MLM Commission Management Guide - GlobalMLMSolution](https://www.globalmlmsolution.com/blog/a-guide-to-mlm-commission-management) — MEDIUM confidence
- [7 Salesforce Territory Management Best Practices - TractionComplete](https://tractioncomplete.com/articles/salesforce-territory-management-best-practices/) — MEDIUM confidence, vendor content on reassignment/cascade behavior
- [Service Territory Fields for Field Service - Salesforce Help](https://help.salesforce.com/s/articleView?id=service.fs_territory_fields.htm&language=en_US&type=5) — HIGH confidence, official Salesforce documentation
- SIGMA codebase (direct inspection, HIGH confidence): `app/Models/User.php` (`coordinator_user_id` self-FK, `coordinator()`/`leaders()` relations, existing `witness_payment_amount` decimal field as precedent for a per-person compensation-like attribute), `app/Models/AuditLog.php` + `app/Observers/AuditObserver.php` + `app/Providers/AppServiceProvider.php` (generic morphable audit trail already registered on `User`), `app/Filament/Resources/Coordinators/*` and `Leaders/*` (resource/table/form structure to mirror for `Articuladores`), `.planning/PROJECT.md` (v1.2 milestone scope and explicit decisions)

---
*Feature research for: SIGMA v1.2 — articulador hierarchy tier + superadmin metadata-key catalog*
*Researched: 2026-08-10*

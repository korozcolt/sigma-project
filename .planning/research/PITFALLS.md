# Pitfalls Research

**Domain:** Inserting a new role tier into an existing self-referencing hierarchy + adding a JSON metadata column with Filament v4 filter/sort, in a Laravel/Spatie political-campaign platform (SIGMA)
**Researched:** 2026-08-10
**Confidence:** HIGH (grounded directly in this codebase's existing files, not generic advice)

This research was produced by reading the actual SIGMA codebase (`app/Models/User.php`, `CampaignMembershipScope`, `CoordinatorResource`/`LeaderResource`, `TopLeadersTable`/`TopCoordinatorsTable` widgets, `LeadersExportController`, `CreateCoordinator`/`CreateLeader`, `AuditObserver`, `CampaignUser` pivot, `Gremio`/`Subcategoria` catalogs) rather than from generic Laravel/Filament tutorials. Every pitfall below cites the exact file(s) where the risk pattern already exists today.

## Critical Pitfalls

### Pitfall 1: `coordinator_user_id` is treated as "the top-of-tree parent FK" in ~6 places — all of them silently break or under-scope when articulador is inserted above it

**What goes wrong:**
Several existing surfaces assume "if the logged-in user has role `coordinator`, `coordinator_user_id = auth()->id()` is the correct scoping filter for their team." Concretely:
- `app/Filament/Widgets/TopLeadersTable.php:46-49` — `->when($user?->hasRole(COORDINATOR), fn ($q) => $q->where('coordinator_user_id', $user->id))`
- `app/Exports/TopLeadersExport.php:38` — identical pattern
- `app/Http/Controllers/Coordinator/LeadersExportController.php:22` — identical pattern

None of these know how to answer "show me all leaders under all coordinadores that belong to *this* articulador." When an articulador role is added, these surfaces will either (a) throw no error and just return an **empty result set** for an articulador (because an articulador's own `id` never equals any `coordinator_user_id`), which reads as "no data" rather than a visible failure, or (b) if someone naively adds `'articulador'` to the `hasRole()` check without changing the `where()` clause, will scope by the articulador's own id instead of the transitive set of coordinador ids they manage — also wrong, also silent.

**Why it happens:**
The codebase was built around a strictly two-level tree (coordinator → leader), so "current user's own id = the parent FK value" was a correct shortcut at the time. Adding a third level breaks the shortcut, but the shortcut is duplicated (not centralized), so it's easy to fix it in one place and miss the others.

**How to avoid:**
Before writing the articulador feature, `grep -rn "coordinator_user_id.*Auth::\|coordinator_user_id.*\$user->id\|hasRole(UserRole::COORDINATOR" app` and treat every hit as a candidate that needs a "resolve my managed coordinador ids" step. Centralize that resolution in one place (e.g. a `$user->managedCoordinatorIds()` method or a scope) instead of re-deriving it inline at each call site — this is exactly the kind of logic that will need a third variant later and should not be copy-pasted a 7th time.

**Warning signs:**
An articulador account logs in and sees zero leaders/coordinadores/apoyos in a dashboard widget or export that a coordinador would see data in. Empty state with no error is the signature of this bug — it will not show up in Filament's exception logs.

**Phase to address:**
Hierarchy/schema phase, before any UI work. The resolution helper must exist and be unit-tested against all three roles (coordinador, articulador, and — already-broken-today risk — a coordinador who is `also_leader`) before touching widgets/exports/resources.

---

### Pitfall 2: `coordinator_user_id` is an overloaded column (its meaning depends on the row's *role*, not just its presence) — reusing that pattern for articulador compounds the ambiguity

**What goes wrong:**
On a `leader`-only user, `coordinator_user_id` means "my coordinator." On a `coordinator` user who is also flagged `also_leader` (`CreateCoordinator.php:40-43`), `coordinator_user_id` is a **self-reference** (`$this->record->update(['coordinator_user_id' => $this->record->id])`). On a plain coordinator, it's `NULL`. Any code that walks "one level up" by reading `coordinator_user_id` must already special-case this, and mostly doesn't (e.g. `User::leaders()` — `hasMany(User::class, 'coordinator_user_id')` — will include a self-referencing also-leader-coordinator as its "own leader" in counts like `leaders_count`).

If the articulador→coordinador relation is modeled as "just add `articulador_user_id` and treat it the same generic way," the same self-loop trap will resurface for a coordinador who is also flagged as their own articulador equivalent, or worse, someone will be tempted to save time by reusing `coordinator_user_id` itself for the articulador link (e.g. "articulador's coordinador row points at the articulador via `coordinator_user_id`") — which would make `coordinator_user_id` mean three different things depending on role, and permanently poison every `leaders()`/`coordinator()` relationship call already in use across `VoterForm`, `LeaderForm`, `CreateLeader`, `TopLeadersTable`, etc.

**Why it happens:**
The self-loop was a pragmatic shortcut for "a coordinator who also acts as a leader" and was never meant to generalize. Under time pressure, the fastest-looking path for articulador is "reuse the FK pattern that already works," but that pattern only works because the two-level tree never needed to distinguish "my parent" from "myself."

**How to avoid:**
Give articulador→coordinador its own, separate, dedicated FK column (e.g. `articulador_user_id` on `users`), with its own `belongsTo`/`hasMany` relation pair, and do **not** collapse it onto `coordinator_user_id`. If an articulador can also act as a coordinador (mirroring the existing `also_leader` flag), model that with an explicit boolean flag + explicit self-reference decision, tested the same way `also_leader` already is, rather than inferring it from FK equality.

**Warning signs:**
Any query or relationship that assumes `coordinator_user_id IS NOT NULL` implies "this is a leader" — check this assumption doesn't silently start including/excluding coordinadores-who-are-also-leaders once a third tier exists.

**Phase to address:**
Schema/hierarchy design phase — this is a data-modeling decision, not a UI decision, and is expensive to reverse once leader/coordinator forms and exports depend on the column's exact semantics.

---

### Pitfall 3: No `UserPolicy`/`CoordinatorPolicy`/`LeaderPolicy` exists today — authorization is 100% implicit in `getEloquentQuery()` scoping, which is easy to bypass when new CRUD surfaces are added for articulador

**What goes wrong:**
`app/Policies/` only contains `InvitationPolicy.php` and `VoterPolicy.php`. `CoordinatorResource::getEloquentQuery()` (`role('coordinator')`) and `LeaderResource::getEloquentQuery()` (`role('leader')`) provide **no owner-scoping at all** today — they rely on the fact that only `coordinator`/`admin_campaign`/`super_admin` can reach the coordinator panel (`EnsureUserHasRole` middleware), and the coordinator panel currently registers no `Resource` classes at all (`CoordinatorPanelProvider` only registers `Dashboard`/`DiaD` pages + widgets, not `LeaderResource`). In other words, "a coordinador can only manage their own leaders" is **not currently enforced by any resource-level query** — it happens to be true only because the panel doesn't expose that resource yet.

When articulador gets "create/manage coordinadores" as a first-class capability, the natural move is to add a Filament resource for it. If that resource's `getEloquentQuery()` copies the existing pattern (`role('coordinator')` with no owner filter, following `CoordinatorResource`'s exact precedent), **every articulador would see and be able to edit every coordinador in the campaign**, not just their own — a direct violation of the "one extra hierarchy level, no further nesting" requirement, and it would look identical to the working `CoordinatorResource` code, so it would pass a casual review.

**How to avoid:**
Do not copy `CoordinatorResource::getEloquentQuery()` verbatim for the articulador-facing coordinador management surface. It must add `->where('articulador_user_id', Auth::id())` (or equivalent) unless the acting user is `admin_campaign`/`super_admin`. Additionally, create an explicit `UserPolicy` (or scoped policy) for "can this user create/edit/delete this specific subordinate" so the authorization rule exists once, independent of which panel/resource/relation-manager/API endpoint reaches the record — Filament's default route-model-binding respects `getEloquentQuery()`, but any hand-rolled `Select::make(...)->relationship(..., modifyQueryUsing: ...)` (as already seen in `LeaderForm.php:31-35` for the coordinator picker) is a second place the same scoping rule must be independently re-applied, and it is trivial to forget on the second (articulador→coordinador) picker.

**Warning signs:**
A `Select` field listing "which coordinador to assign a leader/metadata to" that isn't scoped to the acting articulador's own coordinadores — check every new `Select::make(...)->relationship('...')` and every `modifyQueryUsing` closure introduced for this milestone, not just the top-level resource query.

**Phase to address:**
Authorization/policy phase, explicitly separated from the UI phase. Verification should include a test where an articulador is logged in and asserts they *cannot* see/edit a coordinador belonging to a different articulador — this exact test does not exist today for the coordinator→leader boundary either, so it should be added retroactively as part of this milestone's regression coverage.

---

### Pitfall 4: `campaign_user.role_id` and Spatie's `model_has_roles` are two separate, independently-writable sources of truth for "what role does this user have" — the articulador rollout must update both or they will disagree

**What goes wrong:**
`CampaignUser` (the `campaign_user` pivot) has its own `role_id` column, separately populated in `CreateCoordinator::attachActiveCampaign()` (`'role_id' => Role::findByName(UserRole::COORDINATOR->value)->id`) — this is **in addition to** `$this->record->assignRole(UserRole::COORDINATOR->value)` (Spatie). These are two different tables (`campaign_user.role_id` vs. Spatie's `model_has_roles`) that happen to be kept in sync today by convention, not by a database constraint or a single write path. Any new articulador-creation flow that forgets to set `role_id` on the pivot (easy, since most `hasRole()`/`canAccessPanel()` checks throughout the app only read Spatie) will produce users who pass every `hasRole('articulador')` check but are invisible to anything that joins on `campaign_user.role_id` (e.g. future per-campaign role reporting).

**How to avoid:**
When adding the `articulador` role, update both write paths in the same transaction/method (mirror `CreateCoordinator::afterCreate()` + `attachActiveCampaign()` exactly), and add a test asserting `$user->hasRole('articulador')` and the pivot's `role_id` agree after creation. Do not introduce a third, independent write path.

**Warning signs:**
A report or query joins `campaign_user` on `role_id` and gets a different headcount than `User::role('articulador')->count()`.

**Phase to address:**
Same phase as the role/migration work — this is a one-line omission risk that's cheap to prevent up front (checklist item in the plan) and annoying to detect later (requires a manual data reconciliation script).

---

### Pitfall 5: Adding `articulador` to `UserRole` enum and `EnsureUserHasRole` without deciding its `canAccessPanel()` behavior leaves the role either panel-locked-out or over-privileged

**What goes wrong:**
`User::canAccessPanel()` (`app/Models/User.php:215-224`) is a hardcoded `match` over panel id → allowed roles (`admin`, `leader`, `coordinator`, `reports`). There is no `articulador` panel today. If articulador is only added to the `UserRole` enum and given permissions but never added to any `match` arm, an articulador user can be created and assigned coordinadores in the admin panel (if `admin_campaign`/`super_admin` does it on their behalf) but **cannot log in anywhere themselves** — a "looks done but isn't" trap, since the enum/migration/Filament-resource work will all look complete while the actual login path 404s or 403s.

**How to avoid:**
Decide explicitly, as part of scoping this milestone (not as an implementation afterthought): does articulador get its own panel, reuse the `coordinator` panel with elevated resource visibility, or reuse `admin`? Whatever is decided, `canAccessPanel()` and `CoordinatorPanelProvider`/a new panel provider must be updated together with the role addition, and a login-smoke-test for the new role should exist before calling the role "done."

**Warning signs:**
`php artisan tinker` shows the articulador user has the role and permissions, but visiting any panel as that user redirects to a 403.

**Phase to address:**
Same phase as role/permission setup — this is not a "later" concern, it blocks the entire feature from being usable.

---

### Pitfall 6: JSON metadata filter/sort on unindexed `JSON_EXTRACT` will silently full-table-scan, and degrades exactly on the tables (`users`) that already have the most columns and widest usage across every panel

**What goes wrong:**
MySQL cannot index a `JSON` column directly. A naive Filament `Filter`/`SelectFilter`/sortable column built with `orderByRaw("JSON_EXTRACT(metadata, '$.biaticos') ...")` or `whereRaw` works correctly in dev with a handful of rows, then does a full table scan on `users` once real campaign data accumulates — and because `users` is already scoped by the global `CampaignMembershipScope` (a `whereHas('campaigns', ...)` subquery) on every single query, a second unindexed JSON scan stacked on top compounds cost on a table every panel touches (Users, Coordinators, Leaders resources, every dashboard widget that counts/lists users).

**How to avoid:**
Do not filter/sort directly against `JSON_EXTRACT` on the raw `metadata` column for keys that need to be filterable/sortable in a Filament table. For each catalog key that must support filter/sort, add a MySQL **generated column** (`VIRTUAL` or `STORED`) extracting that key with an explicit index, and point the Filament `SelectFilter`/`TextColumn::sortable()` at the generated column, not at raw JSON functions. Since the metadata catalog is superadmin-managed and presumably small/bounded (a handful of keys like `biaticos`, `almuerzo`, `incentivo`), generated-column-per-key is tractable; it stops being tractable only if the catalog is expected to grow unbounded, in which case a normalized `user_metadata_values` table (one row per user/key/value, indexable on `key` + `value`) is the correct alternative to a JSON blob entirely — worth deciding explicitly rather than defaulting to JSON because the requirement says "JSON metadata column."

**Warning signs:**
A Filament table sort/filter on a metadata key works fine locally, then a `EXPLAIN` on the generated query shows `type: ALL` (full scan) instead of `ref`/`range`. Load-test or `EXPLAIN` any metadata sort/filter before shipping, don't just visually confirm it returns correct results.

**Phase to address:**
Schema phase for the metadata catalog + column, verified with `EXPLAIN` before the Filament UI phase begins — retrofitting generated columns after the filter UI ships means re-touching every filter/column definition a second time.

---

### Pitfall 7: Read-modify-write on the whole `metadata` JSON blob loses concurrent edits when two different superiors (e.g. a coordinador and an articulador, or two coordinadores under the same articulador editing different subordinates) save at nearly the same time

**What goes wrong:**
The natural Filament implementation reads `$user->metadata` (full array), mutates the one key being edited in the form, and saves the whole column back (`$user->update(['metadata' => $fullArray])`). If two superiors independently open the same subordinate's metadata form and each save a different key (or even the same key) within the same request window, the second save overwrites the first save's array wholesale — the first superior's edit silently disappears with no error, no conflict message, and (depending on how the audit observer captures it) a confusing audit trail where the "old" value shown for the second write is not what the first superior actually set.

**Why it's specifically likely here:** the requirement explicitly allows *any* superior in the chain (líder/coordinador/articulador/superadmin) to assign metadata to *their own* subordinates — but nothing in the requirement prevents two different superiors' subordinate sets from overlapping in edge cases (e.g. a coordinador who is `also_leader`, or a superadmin editing anyone), so "only one person can ever edit a given user's metadata" cannot be assumed.

**How to avoid:**
Either (a) update the JSON column atomically at the database level per-key using MySQL `JSON_SET()` inside the update (`UPDATE users SET metadata = JSON_SET(metadata, '$.biaticos', ?) WHERE id = ?`) instead of a PHP read-modify-write of the whole array, or (b) if a normalized `user_metadata_values` table is used instead (see Pitfall 6), the race disappears naturally because each key is its own row and each write is a single-row `UPDATE`/`UPSERT`. Avoid `$user->update(['metadata' => $wholeArray])` as the save mechanism for anything more than single-superadmin-editing-alone use.

**Warning signs:**
Two superiors report "I set a value and it disappeared" with no error on screen. This is very hard to reproduce after the fact from a flat JSON blob, since the audit log (Pitfall 9) may only show "someone changed metadata" at the whole-column level, not which key.

**Phase to address:**
Schema/data-access-layer phase — the write mechanism (atomic per-key vs. whole-column) is a foundational decision that determines whether this bug class can happen at all; it's not something a later UI polish pass can fix without re-touching every save path.

---

### Pitfall 8: `AuditObserver` already exists and auto-fires on `User` model changes, but it captures whole-column diffs and resolves `campaign_id` from the *acting* user's active campaign context — both are wrong defaults for money-like metadata assigned across campaign/role boundaries

**What goes wrong:**
`app/Observers/AuditObserver.php` is already registered on `User::observe(AuditObserver::class)` (`AppServiceProvider.php:68`) and will automatically log every `metadata` column change once it's added to `users`, using `old_values`/`new_values` = the model's `getChanges()`/`getOriginal()`. Two problems specific to this feature:
1. **Whole-column granularity**: if `metadata` is a single JSON column, the audit log records "the whole JSON array changed from X to Y," not "coordinador Juan set `biaticos` = 50000 for leader María on 2026-08-10." For a money-like field, "who assigned *this specific value* when" is exactly the compliance question this milestone anticipates, and the existing generic observer cannot answer it at the granularity needed without per-key audit rows.
2. **`campaign_id` resolution is wrong for cross-context edits**: `AuditObserver::resolveCampaignId()` falls back to `CampaignContext::currentCampaignId()` — i.e., **the acting user's own currently-selected active campaign**, not the campaign the *edited subordinate* actually belongs to. A `super_admin` (who can override active campaign) editing a subordinate's `biaticos` value while their own UI context is set to a different campaign than the subordinate's would produce an audit row tagged to the wrong campaign — a real gap for a money field where "which campaign approved this incentivo" matters for reconciliation.

**How to avoid:**
Do not rely on the generic `AuditObserver` alone for metadata-value provenance. Add a dedicated, explicit audit trail for metadata assignments — either a `user_metadata_values` table with its own `assigned_by`/`assigned_at`/`previous_value` columns (mirroring the exact pattern SIGMA already uses for role assignment on `campaign_user.assigned_at`/`assigned_by` — `CampaignUser.php:19-20`), or a dedicated audit action/event fired explicitly at the point of assignment (not relying on the passive model-diff observer) that resolves campaign from the *subordinate's* campaign membership, not the actor's active context.

**Warning signs:**
Querying `audit_logs` for "who set biaticos to X on user Y" returns a JSON diff of the entire metadata blob rather than an isolated key change, or returns a `campaign_id` that doesn't match the subordinate's actual campaign.

**Phase to address:**
This must be designed in the same phase as the metadata schema, before any UI ships — retrofitting per-key provenance after superiors have already been assigning values through a whole-column-diff-only audit trail means the historical data cannot be reconstructed with per-key attribution (see Recovery Strategies below).

---

### Pitfall 9: Money-like fields (biáticos/incentivos) stored as untyped JSON values lose the type/precision guarantees the rest of the codebase already relies on for money — and sort as *strings*, not numbers, unless explicitly cast

**What goes wrong:**
The one existing money field in this codebase, `witness_payment_amount`, is a real `decimal:2` cast column (`User.php:76`) — not JSON. JSON has no native decimal type; PHP's `json_encode`/`json_decode` round-trips numbers as float/int, which is a well-known source of floating-point precision drift for currency values (e.g. `0.1 + 0.2` class errors) if any arithmetic is ever done on these values (totals, exports, reconciliation). Separately, if a metadata value is saved once as a number (`50000`) and later, for a different user or by a different superior, accidentally saved as a numeric string (`"50000"`) — which is trivial to happen from a Filament `TextInput` unless explicitly cast/validated server-side — MySQL's `JSON_EXTRACT`-based sort will order these inconsistently (numeric JSON values sort numerically, string JSON values sort lexicographically; `"9000"` can sort after `"50000"` as a string), silently corrupting any "sort leaders by biaticos amount" table column.

**How to avoid:**
Enforce a declared type per catalog key (the superadmin-managed key catalog should carry a `type` attribute — `numeric`/`text`/`boolean`/etc — not just a free key name), validate and coerce on save (cast to a fixed-precision representation, e.g. store money as integer cents rather than decimal JSON numbers, exactly the way currency is commonly stored to avoid float drift), and enforce that type consistently both in the Filament form (numeric input with min/step) and in the generated-column extraction used for sorting (`CAST(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.biaticos')) AS UNSIGNED)` in the generated column definition, not a bare string extraction).

**Warning signs:**
A Filament table sorted by a money-like metadata key shows an order that doesn't match visual inspection of the values (a giveaway that string-sort is being applied to what looks like numbers).

**Phase to address:**
Catalog-design phase (the key catalog's schema needs a `type` column from day one) + schema phase (generated columns must cast, not just extract). Retrofitting a `type` onto an existing freeform key catalog after superiors have already entered mixed-type values for the same key is expensive (Pitfall/Recovery below).

---

## Technical Debt Patterns

| Shortcut | Immediate Benefit | Long-term Cost | When Acceptable |
|----------|--------------------|-----------------|------------------|
| Reuse `coordinator_user_id` semantics/self-loop pattern for articulador instead of a dedicated FK | Faster to ship, "looks the same as existing code" | Permanently ambiguous parent-resolution logic across 3 roles; every future `leaders()`/`coordinator()`-style relation needs role-aware special-casing | Never — the dedicated-FK cost is small and paid once |
| Filter/sort JSON metadata via raw `JSON_EXTRACT`/`whereRaw` with no generated column | No migration needed, works immediately in dev | Full table scan on `users` at scale; SQL string-building from filter input is also an injection surface if any dynamic key name reaches raw SQL | Only acceptable for a temporary admin-only debug view never exposed as a real Filament table filter |
| Whole-column JSON read-modify-write for metadata saves | Simplest Eloquent code (`$user->update(['metadata' => $array])`) | Race conditions between concurrent superiors; audit trail can't attribute a specific key change to a specific actor | Only acceptable if the product genuinely guarantees single-writer-per-subordinate (not true here — líder/coordinador/articulador/superadmin can all touch the same chain) |
| Skip a dedicated `UserPolicy`/scoped authorization and rely purely on `getEloquentQuery()` copy-paste for the new articulador-facing coordinador resource | Fast, matches existing `CoordinatorResource`/`LeaderResource` pattern | Any hand-rolled `Select`/relation-manager/API path that doesn't independently re-apply the same scope becomes a silent cross-tenant leak | Never for this milestone — campaign isolation and hierarchy isolation are both explicit hard requirements |
| Add `articulador` to the `UserRole` enum without deciding panel access in the same PR | Enum/migration work feels "done" quickly | Role exists but is unusable until `canAccessPanel()` is updated — creates a false sense of completion | Never — panel access must land with the role |

## Integration Gotchas

Not a third-party-service integration in the traditional sense, but two *internal* systems this feature must integrate correctly with:

| "Integration" | Common Mistake | Correct Approach |
|----------------|-----------------|-------------------|
| Spatie `HasRoles` + `campaign_user.role_id` pivot (dual role storage) | Update only the Spatie side when creating an articulador, forget the pivot `role_id` (or vice versa) | Write both in the same method, in the same transaction, mirroring `CreateCoordinator::afterCreate()` + `attachActiveCampaign()` exactly; add a test asserting both agree |
| Existing `CampaignMembershipScope` global scope on `User` | Assume the scope already limits a Filament table to "my subordinates" — it only limits to "users in the currently active campaign," not to hierarchy | Layer an explicit hierarchy-scoping `where` (or a dedicated local scope) *on top of* the campaign scope for any articulador/coordinador-facing "my team" view; the two scopes solve different problems and neither substitutes for the other |
| `AuditObserver` (already registered on `User`) | Assume it's sufficient audit coverage for metadata since "audit logging already exists" | It exists and will fire, but only at whole-column granularity with actor-context campaign resolution — insufficient for per-key, correctly-campaign-attributed provenance on money-like fields (see Pitfall 8); needs a dedicated audit path alongside it, not instead of it |
| `Gremio`/`Subcategoria` catalog pattern (already-shipped precedent for "superadmin-managed predefined catalog") | Reinvent catalog management from scratch for metadata keys instead of following the existing, already-validated pattern | Model the metadata-key catalog as a real table (mirroring `Gremio`/`Subcategoria`: a Filament resource for CRUD, FK/lookup relation from usage), not as a hardcoded config array or freeform strings — this also gives a natural place to store the per-key `type` from Pitfall 9 |

## Performance Traps

| Trap | Symptoms | Prevention | When It Breaks |
|------|----------|------------|-----------------|
| Sorting/filtering metadata via raw `JSON_EXTRACT` with no index | Filament table sort/filter feels instant in dev, degrades in prod | Generated + indexed column per filterable/sortable catalog key (Pitfall 6) | Breaks once `users` grows past the size where MySQL's query planner stops preferring a scan anyway — for this app's scale (campaign staff, likely low thousands of rows), this can already be noticeable, not a "someday" concern |
| Stacking a JSON filter on top of the existing `CampaignMembershipScope` subquery (`whereHas('campaigns', ...)`) on every `User` query | Every users-listing page (already used heavily — Users/Coordinators/Leaders resources, multiple dashboard widgets) gets slower simultaneously | Keep the generated-column index tight (single key, correct type) so the optimizer can still use it despite the additional `whereHas` join | Compounds specifically because `users` is the most globally-scoped, most-queried table in the app — this is not an isolated feature table |
| `withCount`/`counts()` patterns already used heavily for leaders/apoyos counts (`leaders_count`, `registered_voters_count`) extended naively to also aggregate JSON metadata per row | N+1-style subquery cost multiplies further once a third hierarchy tier's rollups (articulador-level totals) are added | Prefer a single well-indexed rollup query (or a scheduled aggregate) over stacking more `withCount` subqueries onto an already wide `User` query | Becomes visible once "cobertura" style dashboards (already shown to be the app's highest-risk report category per `TopCoordinatorsTable`) are extended to a third tier |

## Security Mistakes

| Mistake | Risk | Prevention |
|---------|------|------------|
| Building a Filament custom `Filter` that interpolates a user-selected metadata *key name* directly into raw SQL (`DB::raw("JSON_EXTRACT(metadata, '$.{$key}')")`) | SQL injection if the key ever originates from anything less trusted than a hardcoded, superadmin-curated enum (e.g. if the catalog is ever exposed as a free-text field, or if key names aren't validated against the catalog before being interpolated) | Only ever interpolate key names that are validated against the superadmin-managed catalog (allowlist, not just "assume it's from a dropdown" — validate server-side too); prefer parameterized `whereJsonContains`/generated-column `where` clauses over raw SQL string-building entirely |
| Trusting the Filament form's `Select` scoping alone to prevent an articulador from assigning metadata to a coordinador/leader outside their managed chain | A crafted request (bypassing the rendered `Select` options) could still hit the underlying save action for an out-of-scope subordinate if the save action doesn't independently re-verify authorization | Re-verify "is this target user actually my subordinate" server-side in the save/action handler itself, not only in the option list the UI renders (same class of gap as Pitfall 3) |
| Assuming `getEloquentQuery()` scoping on the new articulador-facing coordinador resource is sufficient without an explicit policy | Direct URL access to `edit/{record}` for a coordinador outside the articulador's managed set — protected today only if every entry point (resource route binding, relation managers, custom actions) independently re-applies the same scope | Add an explicit `UserPolicy::update()`/`view()` check as a second, independent layer, not just query-scoping (defense in depth, and the only layer that also protects any future non-Filament entry point, e.g. an API) |

## UX Pitfalls

| Pitfall | User Impact | Better Approach |
|---------|-------------|-------------------|
| Articulador logs in and existing dashboards/exports (`TopLeadersTable`, `TopCoordinatorsTable`, `LeadersExportController`) show empty or campaign-wide-unscoped data because the role wasn't added to their scoping logic (Pitfall 1) | Looks like a bug or "no data," undermines the exact "operational trust" value proposition this whole platform is built around per `PROJECT.md` | Explicitly inventory and update every existing coordinator-scoped widget/export as part of this milestone, don't treat them as "existing, not touched" |
| A superior assigns a metadata value, another superior's earlier assignment silently disappears (Pitfall 7) with no on-screen indication anything was overwritten | Erodes trust in the exact category of data (money/incentivos) most likely to cause a real dispute between team members | Show "last updated by X at Y" directly in the metadata edit UI (which also requires Pitfall 8's per-key audit trail to exist) so a second editor sees they're overwriting a recent change before saving |
| Metadata catalog keys are free-typed per row with no declared type, so the same key (`biaticos`) ends up with a mix of `"50000"`, `50000`, `"50,000"` across different users entered by different superiors over time | Sort/filter looks broken/inconsistent to end users, and totals (if ever computed) silently miscalculate | Enforce type at the catalog level and at form-input level from the start (Pitfall 9) |

## "Looks Done But Isn't" Checklist

- [ ] **Articulador role added:** Often missing `canAccessPanel()` wiring — verify an articulador user can actually log into *some* panel, not just that the role/permissions exist in the DB.
- [ ] **Articulador→coordinador hierarchy:** Often missing updates to the ~6 existing "if hasRole(coordinator) then where coordinator_user_id = auth id" call sites — verify `TopLeadersTable`, `TopCoordinatorsExport`/`TopLeadersExport`, `LeadersExportController`, and any coordinator-scoped dashboard widget all correctly resolve an articulador's transitive team, not just a coordinador's direct team.
- [ ] **Articulador-facing coordinador CRUD:** Often missing an explicit ownership scope (`articulador_user_id = auth id`) on the resource's `getEloquentQuery()` — verify by logging in as two different articuladores and confirming neither can see/edit the other's coordinadores.
- [ ] **Metadata JSON column filter/sort:** Often missing an underlying indexed generated column — verify with `EXPLAIN` that the Filament table's filter/sort query doesn't full-scan `users`.
- [ ] **Metadata value provenance:** Often missing per-key `assigned_by`/`assigned_at`/previous-value tracking — verify you can answer "who set biaticos to X for user Y and when" without reconstructing it from a whole-column JSON diff.
- [ ] **Metadata value typing:** Often missing a declared type per catalog key — verify a money-like key can't be saved as a string in one place and a number in another, and that sorting behaves numerically, not lexicographically.
- [ ] **Dual role storage sync:** Often missing the `campaign_user.role_id` pivot write when the Spatie role is assigned via a new (articulador) creation flow — verify both agree after creation.

## Recovery Strategies

| Pitfall | Recovery Cost | Recovery Steps |
|---------|----------------|-----------------|
| Coordinator-scoped widgets/exports not updated for articulador (Pitfall 1) | LOW | Centralize the "resolve my managed coordinador/leader ids" helper and swap each call site to use it; no data migration needed, purely a query-logic fix |
| `coordinator_user_id` reused/overloaded for articulador instead of a dedicated FK (Pitfall 2) | MEDIUM | Add the correct dedicated column, backfill it from the misused column where inferable, then stop writing the overloaded value going forward — requires careful backfill since the overloaded meaning can't always be disambiguated after the fact |
| Missing policy/authorization layer discovered after ship (Pitfall 3) | LOW–MEDIUM | Add the policy/scope retroactively; audit `audit_logs` for any cross-tenant reads/writes that may have already occurred under the gap |
| Whole-column JSON metadata with no per-key audit trail already in production before the gap is noticed (Pitfall 8) | HIGH | Historical per-key attribution generally **cannot be reconstructed** from whole-column diffs alone if multiple keys changed in the same save — this is the single most expensive pitfall to recover from and should be prevented up front, not patched later |
| Mixed-type metadata values already saved inconsistently (Pitfall 9) | MEDIUM | Requires a one-time data-cleanup migration coercing existing values to the declared type per key, plus catalog-level type enforcement going forward — feasible but must be done carefully for money fields to avoid silently changing a value's meaning |

## Pitfall-to-Phase Mapping

| Pitfall | Prevention Phase | Verification |
|---------|-------------------|----------------|
| Coordinator-scoped call sites break for articulador (1) | Hierarchy/schema phase, before UI | Test: articulador sees correct transitive team data in every existing widget/export that a coordinador role currently reaches |
| Overloaded `coordinator_user_id` reused for articulador (2) | Hierarchy/schema phase | Code review checklist: dedicated FK column exists, no self-loop reuse across roles |
| No policy layer for articulador→coordinador CRUD (3) | Authorization/policy phase, separate from UI phase | Test: articulador A cannot view/edit coordinador belonging to articulador B, via both the resource UI and direct record access |
| Dual role storage (Spatie vs. `campaign_user.role_id`) drifts (4) | Same phase as role/migration setup | Test: `hasRole('articulador')` and pivot `role_id` agree immediately after creation |
| Articulador role has no panel access wired (5) | Same phase as role/migration setup | Manual login smoke test as a freshly-created articulador |
| Unindexed JSON filter/sort (6) | Schema phase for metadata column/catalog, before Filament UI phase | `EXPLAIN` on every metadata filter/sort query shows an index is used, not a full scan |
| Concurrent metadata write race (7) | Schema/data-access-layer phase | Test: two concurrent updates to different keys on the same user's metadata both persist (no lost update) |
| Audit trail insufficient for per-key/per-campaign provenance (8) | Same phase as metadata schema, before UI ships | Test: can answer "who assigned key K to user U with value V and when" without ambiguity, and campaign attribution matches the subordinate's campaign, not the actor's active context |
| Money-like values untyped/mis-sorting (9) | Catalog-design phase (type field) + schema phase (cast in generated column) | Test: sorting a numeric metadata key across mixed legacy/new rows produces numeric, not lexicographic, order |

## Sources

- Direct codebase inspection (HIGH confidence, primary source for all hierarchy/authorization/audit findings): `app/Models/User.php`, `app/Models/CampaignUser.php`, `app/Models/Scopes/CampaignMembershipScope.php`, `app/Models/Concerns/HasCampaignMembershipScope.php`, `app/Filament/Resources/Coordinators/*`, `app/Filament/Resources/Leaders/*`, `app/Filament/Resources/Users/*`, `app/Filament/Widgets/TopLeadersTable.php`, `app/Filament/Widgets/TopCoordinatorsTable.php`, `app/Exports/TopLeadersExport.php`, `app/Http/Controllers/Coordinator/LeadersExportController.php`, `app/Observers/AuditObserver.php`, `app/Models/AuditLog.php`, `app/Enums/UserRole.php`, `app/Http/Middleware/EnsureUserHasRole.php`, `database/migrations/2026_01_21_000002_add_coordinator_to_users_table.php`, `.planning/PROJECT.md`
- [MySQL JSON Columns — PHP Architect](https://www.phparch.com/2026/06/mysql-json-columns/) — MEDIUM, corroborates generated-column indexing need
- [MySQL 8.4 Reference Manual: Secondary Indexes and Generated Columns](https://dev.mysql.com/doc/refman/8.4/en/create-table-secondary-indexes.html) — HIGH, official docs on generated-column indexing for JSON
- [MySQL: Indexing JSON documents via Virtual Columns (official blog)](https://dev.mysql.com/blog-archive/indexing-json-documents-via-virtual-columns/) — HIGH, official source
- [A Practical Guide to Indexing JSON in MySQL — Pipedrive Engineering](https://medium.com/pipedrive-engineering/a-practical-guide-to-indexing-json-in-mysql-dccf10586204) — MEDIUM, corroborating community source
- [How to index JSON columns using MySQL — Vlad Mihalcea](https://vladmihalcea.com/index-json-columns-mysql/) — MEDIUM, corroborating community source
- General knowledge of MySQL `JSON_SET()` atomic partial updates and Laravel `lockForUpdate()`/optimistic-locking patterns for concurrent JSON writes — MEDIUM confidence (training-data-based, standard/well-documented MySQL behavior, not independently re-verified against a specific MySQL version doc page in this session)

---
*Pitfalls research for: SIGMA v1.2 — Articuladores + Metadata de Usuario*
*Researched: 2026-08-10*

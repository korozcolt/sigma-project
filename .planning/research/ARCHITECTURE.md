# Architecture Research: Articuladores + User Metadata (v1.2)

**Domain:** Integration architecture — extending an existing Laravel 12 / Filament 4 / Spatie-Permission hierarchy and admin-panel resource pattern
**Researched:** 2026-08-10
**Confidence:** HIGH (all findings sourced directly from the current codebase, not training-data assumptions)

## Current Architecture (as-is)

```
┌────────────────────────────────────────────────────────────────────┐
│ Filament Panels (app/Providers/Filament/*PanelProvider.php)         │
│                                                                       │
│  admin/  ──────── discoverResources(app/Filament/Resources) ─────►  │
│    canAccessPanel: super_admin, admin_campaign, reviewer            │
│    CoordinatorResource, LeaderResource, UserResource, GremioResource│
│                                                                       │
│  coordinator/  ── path 'coordinator', custom pages, NOT Filament   │
│    canAccessPanel: coordinator, admin_campaign, super_admin         │
│    authMiddleware: EnsureUserHasRole:coordinator                    │
│    Livewire/Volt self-service: resources/views/livewire/coordinator/│
│      dashboard, leaders, create-leader, edit-leader, ...            │
│                                                                       │
│  leader/   ── similar self-service shape, role: leader              │
│  reports/  ── canAccessPanel: reports_viewer, ->resources([...])   │
└────────────────────────────────────────────────────────────────────┘
                              │
┌────────────────────────────▼────────────────────────────────────────┐
│ App\Models\User (single table, role via Spatie HasRoles, no teams)  │
│                                                                       │
│  users.coordinator_user_id  (self-FK, nullOnDelete)                 │
│    User::coordinator(): BelongsTo(User, 'coordinator_user_id')      │
│    User::leaders(): HasMany(User, 'coordinator_user_id')            │
│    Semantic meaning is fixed: "this leader's coordinator"           │
│                                                                       │
│  campaign_user (pivot): user_id, campaign_id, role_id, assigned_*   │
│    → drives HasCampaignMembershipScope / CampaignMembershipScope    │
│      global scope (whereHas('campaigns', campaign_id = current))    │
│    → INDEPENDENT of coordinator_user_id — campaign isolation does   │
│      not care about hierarchy, only campaign_user membership        │
└────────────────────────────────────────────────────────────────────┘
```

`coordinator_user_id` is **not** a generic "reports-to" pointer — it is a specific, narrowly-typed, heavily-consumed field meaning "the coordinator this leader belongs to." It is directly referenced (not just via the `coordinator()`/`leaders()` relations) in:

- `app/Filament/Resources/Leaders/Schemas/LeaderForm.php` — the coordinator `Select` is scoped `->role(UserRole::COORDINATOR->value)`
- `app/Filament/Resources/Leaders/Pages/CreateLeader.php`, `EditLeader.php` — municipality sync and campaign-inheritance logic keyed on `coordinator_user_id`
- `app/Filament/Resources/Coordinators/Tables/CoordinatorsTable.php` — `leaders_count` via `counts('leaders')`
- `app/Filament/Resources/Invitations/Schemas/InvitationForm.php`, `app/Models/Invitation.php`, `app/Services/InvitationService.php` — leader self-registration invitation links carry a `coordinator_user_id`
- `app/Filament/Resources/Users/Schemas/UserForm.php`, `app/Filament/Resources/Voters/Schemas/VoterForm.php` — generic forms reference it for context
- `app/Exports/TopLeadersExport.php`, `app/Filament/Widgets/TopLeadersTable.php`, `app/Http/Controllers/Coordinator/LeadersExportController.php` — reports/exports filter `where('coordinator_user_id', Auth::id())` for the logged-in coordinator's own team
- `resources/views/livewire/coordinator/*.blade.php` — the entire self-service coordinator panel (dashboard, leaders list, create/edit leader) is built around "my leaders = `leaders()` where `coordinator_user_id = me`"
- ~20 Pest test files assert this exact semantics (`CoordinatorLeaderRelationshipTest`, `DashboardLeadersScopeTest`, `TopLeadersExportTest`, `OwnershipScopedWidgetsTest`, `WidgetDrillThroughTest`, etc.)

This is the single most important fact for the roadmap: **`coordinator_user_id` is a load-bearing, well-tested, narrowly-scoped column.** Repurposing it (renaming, or making it polymorphic to mean "any superior") would touch ~25 files and every test above, for a column whose current name and FK target (`coordinator` role) is asserted directly in multiple places.

## Q1 — Articulador→Coordinador Link: New Column, Not a Reuse of `coordinator_user_id`

**Recommendation: add a new dedicated `articulador_user_id` self-referencing FK on `users`, mirroring the exact migration pattern used for `coordinator_user_id`. Do not rename, generalize, or repurpose `coordinator_user_id`.**

### Tradeoffs

| Option | Pros | Cons | Verdict |
|---|---|---|---|
| New `articulador_user_id` column (mirror pattern) | Zero risk to existing `coordinator_user_id` consumers/tests; migration is a copy-paste of `2026_01_21_000002_add_coordinator_to_users_table.php`; `User::articulador()`/`User::coordinators()` relations are additive; existing `LeaderForm`, exports, dashboards, invitations untouched | One more FK column on `users`; two parallel self-FK columns to reason about | **Recommended** |
| Rename/generalize `coordinator_user_id` → e.g. `parent_user_id` with a `parent_role` discriminator | "Cleaner" schema in the abstract | Breaks every literal `coordinator_user_id` reference above; requires touching Leader/Coordinator resource forms, exports, coordinator self-service panel, invitations, and ~20 tests; also semantically wrong — the milestone explicitly says "coordinadores keep working exactly as today, no coordinador→coordinador nesting," i.e. the *shape* of the hierarchy is not symmetric (articulador→coordinador is a different, additive level, not a recursive "parent" concept) | Rejected — violates the "harden in place, no rewrites" project constraint and the milestone's own "keep working exactly as today" requirement |
| Single polymorphic `superior_id` + `superior_type`/level enum | Extensible to arbitrary depth | Massive overkill: the milestone explicitly caps this at one new flat level (articulador→coordinador, no further nesting) and the project constraint says avoid large schema expansion for a hardening milestone | Rejected — YAGNI, and campaign isolation (`CampaignMembershipScope`) is already independent of hierarchy depth, so there's no scaling need it solves today |

### Concrete migration shape (mirrors `2026_01_21_000002_add_coordinator_to_users_table.php`)

```php
Schema::table('users', function (Blueprint $table) {
    $table->foreignId('articulador_user_id')
        ->nullable()
        ->after('coordinator_user_id')
        ->constrained('users')
        ->nullOnDelete();

    $table->index('articulador_user_id');
});
```

### Model additions (`app/Models/User.php`)

```php
public function articulador(): BelongsTo
{
    return $this->belongsTo(User::class, 'articulador_user_id');
}

public function coordinators(): HasMany
{
    return $this->hasMany(User::class, 'articulador_user_id');
}
```

Do **not** add `articulador_user_id` to `CoordinatorMembershipScope`/`CampaignMembershipScope` — that scope is driven entirely by `campaign_user`, and hierarchy has never been part of campaign isolation. Confirmed by reading `app/Models/Scopes/CampaignMembershipScope.php`: it only does `whereHas('campaigns', ...)`, nothing FK/hierarchy related.

## Q2 — Metadata-Key Catalog: New Table (not enum, not config)

**Recommendation: a new `metadata_keys` table, managed via a Filament resource (super_admin only), following the exact `Gremio`/`GremioResource` precedent already in the codebase — plus a `metadata` JSON column on `users`.**

### Why a table (not `UserRole`-style PHP enum, not `config/*.php`)

The milestone explicitly says: *"Superadmin-managed predefined catalog of metadata keys ... not freeform."* That requirement — a catalog **manageable through a UI at runtime by a super_admin, without a deploy** — rules out both alternatives:

- **PHP enum** (`app/Enums/UserRole.php` pattern): enum cases are compile-time; adding/renaming a metadata key would require a code change + deploy, which directly contradicts "superadmin-managed."
- **`config/*.php` array**: same problem — config changes require deploy/cache-clear, and there's no existing pattern in this codebase for admin-editable config (settings that are user-editable at runtime, e.g. `campaigns.settings`, live in DB columns, not config files).
- **New DB table**: this is the codebase's existing precedent for exactly this shape of requirement. `Gremio` (`database/migrations/2026_07_22_000001_create_gremios_table.php`, `app/Models/Gremio.php`, `app/Filament/Resources/Gremios/*`) is a superadmin-managed, name-only lookup catalog with a full Filament CRUD resource under the `Configuración` navigation group. `metadata_keys` should copy this shape exactly, extended with a `type` column for typed filtering/sorting.

### Schema

```php
Schema::create('metadata_keys', function (Blueprint $table) {
    $table->id();
    $table->string('key')->unique();       // e.g. "biaticos", "almuerzo", "asignacion"
    $table->string('label');                // display label, e.g. "Biáticos"
    $table->enum('type', ['numeric', 'string']); // drives cast + filter widget
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});

Schema::table('users', function (Blueprint $table) {
    $table->json('metadata')->nullable()->after('is_special_coordinator');
});
```

Why `type` as a simple `numeric|string` enum column rather than trying to encode arbitrary JSON-schema typing: the milestone's own examples (`biaticos`, `almuerzo` = numeric; `asignacion` = string) are the full requirement surface — don't over-engineer a generic type system for two type buckets. This mirrors the project's existing preference for narrow, purpose-built enums (`UserRole`, `VoterStatus`) over generic frameworks.

### Model

```php
// app/Models/MetadataKey.php
class MetadataKey extends Model
{
    protected $fillable = ['key', 'label', 'type', 'is_active'];
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
```

```php
// app/Models/User.php additions
protected function casts(): array
{
    return [
        // ...existing...
        'metadata' => 'array',   // same pattern as SurveyMetrics/MessageBatch/Message::metadata
    ];
}
```

`'metadata' => 'array'` is the exact existing cast pattern already used in `app/Models/SurveyMetrics.php`, `app/Models/MessageBatch.php`, and `app/Models/Message.php` — no new cast infrastructure needed. Values should be stored consistently as JSON scalars keyed by the catalog's `key` string, e.g. `{"biaticos": 50000, "asignacion": "zona-norte"}`; the numeric/string distinction is enforced at the **form layer** (Filament `TextInput::numeric()` vs plain `TextInput`, chosen dynamically per `MetadataKey::type`), not by a custom Eloquent cast, since a single JSON column necessarily mixes types across keys.

### Assignment UI

A "Metadata" `Repeater` or a dynamically-built `Section` in the Coordinator/Leader/Articulador Filament forms, keyed off `MetadataKey::where('is_active', true)->get()`, is the natural fit — each active catalog key renders one input (numeric or text, per its `type`), read/written against `users.metadata->{key}`. This is additive to `CoordinatorForm`/`LeaderForm`/new `ArticuladorForm`; it does not require a separate table for values (`user_metadata` values-table) since the milestone frames this as a single JSON column, and Filament forms can dehydrate/populate JSON sub-paths directly via `dehydrateStateUsing`/`formatStateUsing` per field, or via `KeyValue`-style dynamic component generation.

## Q3 — New `ArticuladorResource`: Exact Mirror of `CoordinatorResource`/`LeaderResource`

**Recommendation: new resource directory `app/Filament/Resources/Articuladores/` with the identical five-file shape as `Coordinators/`/`Leaders/`, auto-discovered by the `admin` panel's `discoverResources()` call — no manual panel registration needed.**

Confirmed from `app/Providers/Filament/AdminPanelProvider.php`: `->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')` — any resource class under `app/Filament/Resources/**` is auto-registered for the `admin` panel. `CoordinatorResource`, `LeaderResource`, and `GremioResource` all rely on this; none are manually listed. So `ArticuladorResource` needs zero panel-provider changes.

### File-by-file mirror

| New file | Mirrors | Key difference |
|---|---|---|
| `app/Filament/Resources/Articuladores/ArticuladorResource.php` | `CoordinatorResource.php` | `getEloquentQuery()` → `parent::getEloquentQuery()->role('articulador')`; new `$navigationSort` (suggest `1`, above Coordinadores at `2`, so hierarchy reads top-down in the nav) |
| `app/Filament/Resources/Articuladores/Schemas/ArticuladorForm.php` | `CoordinatorForm.php` | Same personal-info/contact/location/access sections; no `also_leader`-style toggle needed unless product wants "también coordinador" parity (not requested) |
| `app/Filament/Resources/Articuladores/Tables/ArticuladoresTable.php` | `CoordinatorsTable.php` | Replace `leaders_count` (`counts('leaders')`) with `coordinators_count` (`counts('coordinators')`, the new relation from Q1) |
| `app/Filament/Resources/Articuladores/Pages/{Create,Edit,List}Articulador.php` | `Coordinators/Pages/*` | `CreateArticulador::afterCreate()` → `$this->record->assignRole(UserRole::ARTICULADOR->value)` + `attachActiveCampaign()` using `Role::findByName(UserRole::ARTICULADOR->value)` |

### `CoordinatorForm`/`CoordinatorResource` also need one addition

Once `articulador_user_id` exists, `CoordinatorForm` should gain an `articulador_user_id` `Select` scoped `->role(UserRole::ARTICULADOR->value)`, directly mirroring how `LeaderForm` scopes its `coordinator_user_id` select to `->role(UserRole::COORDINATOR->value)` (`app/Filament/Resources/Leaders/Schemas/LeaderForm.php:29-44`). This is a **modification** to an existing file, not new — flag it explicitly in planning since it's easy to miss (the milestone says "coordinadores keep working exactly as today" for the leader-facing side, but the coordinator *record itself* needs a new optional field to be assignable to an articulador).

### Role/enum/seeder changes (all additive)

- `app/Enums/UserRole.php`: add `case ARTICULADOR = 'articulador';` with label/color/icon/description — `RoleSeeder` picks it up automatically (`foreach (UserRole::cases())`), no seeder change needed.
- `app/Models/User.php::canAccessPanel()`: decide whether `articulador` gets `admin` panel access (to use `ArticuladorResource`/`CoordinatorResource` directly) and/or a **new self-service `articulador` panel** — see the open question below.

### Open architecture question the roadmap must resolve explicitly

The existing `coordinator` role has **two separate surfaces**: (a) `CoordinatorResource` in the `admin` panel, for admins/reviewers to manage coordinator records, and (b) a wholly separate self-service `coordinator` Filament panel (`app/Providers/Filament/CoordinatorPanelProvider.php`, gated by `EnsureUserHasRole:coordinator`, built on custom Livewire/Volt pages under `resources/views/livewire/coordinator/*` — dashboard, leaders list, create/edit leader) where a logged-in coordinator manages *their own* leaders.

The milestone's stated goal — *"Articuladores organize a set of coordinadores (creating and managing them)"* — reads like the same shape as the coordinator's self-service capability, not just an admin-panel CRUD resource. If so, this milestone implies a **third new component**: an `ArticuladorPanelProvider` + `resources/views/livewire/articulador/*` self-service views, mirroring the coordinator panel exactly (dashboard, coordinadores list, create/edit coordinador — each create/edit setting `articulador_user_id = Auth::id()` the same way `create-leader.blade.php` implicitly sets `coordinator_user_id = Auth::id()`). This is a bigger scope item than "just a Filament resource" and should be an explicit roadmap decision/phase, not an assumption — flag it for the roadmap author rather than silently building only the admin-panel resource.

## Q4 — Filter/Sort by JSON Key: No Interaction With Campaign Isolation; Targeted Impact on Reporting Surfaces

### Campaign-isolation scope: unaffected

`CampaignMembershipScope` (`app/Models/Scopes/CampaignMembershipScope.php`) applies unconditionally to every `User::query()` via the `HasCampaignMembershipScope` trait's global scope, constraining to `whereHas('campaigns', campaign_id = current)`. It has no knowledge of, and no interaction with, `metadata`/JSON columns, `coordinator_user_id`, or the new `articulador_user_id`. Any Filament `SelectFilter`/custom filter/`orderBy` added against `users.metadata->{key}` composes on top of this scope exactly like every existing filter in `UsersTable`/`CoordinatorsTable`/`LeadersTable` does — because Filament resource tables always operate on `Resource::getEloquentQuery()`, which already includes the global scope. **No change to the scope itself is needed or should be made.**

### Filament implementation for filter/sort by JSON key

Laravel's query builder supports JSON path filtering via arrow syntax (`where('metadata->biaticos', ...)`, `whereJsonContains`) across MySQL 8.0+, confirmed current in the Laravel 12.x docs ("JSON Where Clauses" — MariaDB 10.3+, MySQL 8.0+, PostgreSQL 12.0+, SQL Server 2017+, SQLite 3.39.0+ all supported via the `->` operator). `orderBy('metadata->biaticos')` uses the same column-wrapping mechanism as `where()` in Laravel's grammar layer and is a well-established pattern (MEDIUM-HIGH confidence — not explicitly demoed in the docs' Ordering section, but the JSON arrow-path wrapping is shared infrastructure between `where`/`orderBy`/`groupBy` in Laravel's query grammars). Given the project's confirmed `DB_CONNECTION=mysql`, this is directly usable.

Practical Filament pattern, since the catalog is dynamic (keys aren't known at compile time):

```php
// In ArticuladoresTable / CoordinatorsTable / LeadersTable / UsersTable
...MetadataKey::where('is_active', true)->get()->map(fn (MetadataKey $key) =>
    TextColumn::make("metadata_{$key->key}")
        ->label($key->label)
        ->state(fn (User $record) => data_get($record->metadata, $key->key))
        ->sortable(query: fn (Builder $query, string $direction) =>
            $query->orderBy("metadata->{$key->key}", $direction))
)
```

and for filters, a per-key `Filter::make()` with a custom form input (numeric range for `type=numeric`, text/select for `type=string`) applying `->where("metadata->{$key->key}", ...)`.

**Performance note (flag for a later phase, not this one's blocker):** MySQL cannot index a raw JSON path directly; if a metadata key like `biaticos` becomes a common sort/filter target at scale, the standard mitigation is a MySQL **generated/virtual column** (`ALTER TABLE users ADD biaticos_numeric DECIMAL(10,2) GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.biaticos'))) VIRTUAL, ADD INDEX (biaticos_numeric)`) — but the current campaign-scale data volumes in this project (hundreds to low-thousands of users per campaign, per the existing `Voter`/`Apoyo` scale) don't warrant this up front. Treat as a documented future optimization, not a v1.2 requirement.

### Reporting/export surfaces that touch `users` and need explicit review (not necessarily code changes, but scope decisions)

These are the concrete surfaces the milestone's "filterable and sortable in Filament listings" claim could reasonably extend to, or that assume a 2-level (coordinator→leader) hierarchy and may need articulador-awareness:

| File | Current assumption | Impact of this milestone |
|---|---|---|
| `app/Filament/Resources/Users/Tables/UsersTable.php` | Generic, all roles, no hierarchy awareness | Explicitly named in the milestone ("users/coordinators/leaders/articuladores") — add metadata filter/sort columns here |
| `app/Filament/Resources/Coordinators/Tables/CoordinatorsTable.php` | 1-level (`leaders_count`) | Add metadata columns; optionally add an `articulador.name` column now that coordinators can belong to one |
| `app/Filament/Resources/Leaders/Tables/LeadersTable.php` | 1-level (`coordinator.name`) | Add metadata columns; no hierarchy change needed (leaders still only know their coordinator) |
| `app/Exports/TopLeadersExport.php`, `app/Filament/Widgets/TopLeadersTable.php`, `app/Http/Controllers/Coordinator/LeadersExportController.php` | Coordinator-scoped via literal `coordinator_user_id = Auth::id()` | **Not in the milestone's explicit scope** (filter/sort is scoped to "Filament tables for users/coordinators/leaders/articuladores," not exports) — leave untouched; only revisit if the roadmap decides articuladores need an equivalent "my coordinadores' rollup" export, which would be new code analogous to `TopLeadersExport`, not a modification of it |
| `app/Filament/Widgets/TopCoordinatorsTable.php`, dashboard widgets (`TerritorialOwnershipTable`, `ApoyosLideresCoordinadoresTable`) | Coordinator/leader rollups, campaign-scoped | Out of explicit scope for v1.2; flag as a likely v1.3 ask if articuladores need dashboard visibility into "their" coordinadores' teams |

None of these require changes to `CampaignMembershipScope` itself. The isolation model and the new hierarchy/metadata features are orthogonal by design (confirmed by reading the scope's actual implementation), which is exactly why this feature set is safe to add without touching the campaign-safety guarantees the v1.0/v1.1 milestones spent most of their effort hardening.

## Recommended Build Order (dependency-driven)

1. **Schema first** — three additive migrations, no destructive changes to existing tables:
   a. `add_articulador_to_users_table` (mirrors `2026_01_21_000002_add_coordinator_to_users_table.php`)
   b. `create_metadata_keys_table`
   c. `add_metadata_to_users_table` (JSON column)
2. **Role + model layer** — `UserRole::ARTICULADOR` enum case (seeder picks it up automatically); `User::articulador()`/`coordinators()` relations; `MetadataKey` model + `'metadata' => 'array'` cast on `User`. This must land before any Filament form can scope a `Select` against the `articulador` role or read/write `metadata`.
3. **Metadata catalog UI** — `MetadataKeyResource` (copy `GremioResource` shape exactly) so a super_admin can create/edit keys before any assignment UI needs them populated.
4. **Hierarchy UI** — `ArticuladorResource` (copy `CoordinatorResource` shape) + the `articulador_user_id` `Select` addition to `CoordinatorForm`. This depends on step 2's role/relation existing.
5. **Metadata assignment UI** — dynamic per-key inputs added to `ArticuladorForm`/`CoordinatorForm`/`LeaderForm`/`UserForm`, depends on step 3's catalog existing (forms iterate `MetadataKey::active()`).
6. **Filter/sort surfaces** — add metadata-driven `TextColumn`s + `Filter`s to `UsersTable`, `CoordinatorsTable`, `LeadersTable`, and the new `ArticuladoresTable`; depends on step 5's data actually being assignable (no point filtering an always-empty column).
7. **Decision checkpoint before further work**: resolve the open Q3 question (self-service `articulador` panel vs. admin-only resource) — this determines whether steps beyond 6 include a new `ArticuladorPanelProvider` + Livewire views, which is a materially larger scope item than the rest of this list combined.

Each step is independently testable (Pest, mirroring the existing `CoordinatorLeaderRelationshipTest`/`DashboardLeadersScopeTest` shape for the new `articulador`/`coordinators()` relation, and a new `MetadataKey`/`users.metadata` filter/sort test), consistent with the project's "every change must have a test" rule.

## Sources

- Direct codebase reads (HIGH confidence, current as of 2026-08-10): `app/Models/User.php`, `app/Enums/UserRole.php`, `app/Models/Scopes/CampaignMembershipScope.php`, `app/Models/Concerns/HasCampaignMembershipScope.php`, `database/seeders/RoleSeeder.php`, `database/migrations/2026_01_21_000002_add_coordinator_to_users_table.php`, `app/Filament/Resources/Coordinators/*`, `app/Filament/Resources/Leaders/*`, `app/Filament/Resources/Users/Tables/UsersTable.php`, `app/Filament/Resources/Gremios/*`, `app/Models/Gremio.php`, `database/migrations/2026_07_22_000001_create_gremios_table.php`, `app/Models/SurveyMetrics.php`/`MessageBatch.php`/`Message.php` (existing `'metadata' => 'array'` cast precedent), `app/Providers/Filament/AdminPanelProvider.php`, `app/Providers/Filament/CoordinatorPanelProvider.php`, `resources/views/livewire/coordinator/*`, `app/Exports/TopLeadersExport.php`, `config/permission.php` (`'teams' => false`), `.env` (`DB_CONNECTION=mysql`)
- [Database: Query Builder | Laravel 12.x](https://laravel.com/docs/12.x/queries) — JSON Where Clauses section (arrow-syntax JSON querying, MySQL 8.0+ support) — MEDIUM-HIGH confidence for `orderBy` JSON-path support specifically (shared grammar mechanism with `where`, not separately demoed in this doc version)

---
*Architecture research for: SIGMA v1.2 (Articuladores + Metadata de Usuario)*
*Researched: 2026-08-10*

# Phase 14: Articulador Admin Resource & Hierarchy Wiring - Research

**Researched:** 2026-08-10
**Domain:** Filament 4 Resource scaffolding (mirroring an existing Resource) + campaign-scoped `relationship()` Select on an existing form
**Confidence:** HIGH

## Summary

This phase is a mechanical mirror-and-extend job, not new architecture. `App\Filament\Resources\Coordinators\*` is a complete, working, tested reference implementation for exactly what `AreaCoordinatorResource` needs to be — same model (`User`), same panel (`admin`), same `getEloquentQuery()->role(...)` scoping shape, same form-section layout, same `afterCreate()`/campaign-attachment pattern. The only structural difference is the Spatie role filtered on (`coordinator` → `area_coordinator`) and dropping the `also_leader` toggle per D-01.

The more interesting finding is on the `CoordinatorForm` Select (ARTIC-01's other half): the codebase already has a **better precedent than `municipality_id`** for this exact case — `LeaderForm::coordinator_user_id` and `UserForm::coordinator_user_id` are both `relationship()` Selects that point at another `User` record, filtered only by `->role(UserRole::COORDINATOR->value)`, with **no manual campaign-scoping closure**. That's not an oversight: `User` has a global Eloquent scope (`CampaignMembershipScope`, applied via `HasCampaignMembershipScope` trait) that automatically restricts every `User::query()` — including relationship queries built by Filament's `relationship()` Select — to the campaign currently active for the acting admin (`CampaignContext::currentCampaignId(Auth::user())`), whenever a campaign context is set. `municipality_id`'s closure is manual precisely because `Municipality` has no such global scope. The correct mirror for `area_coordinator_user_id` is the `coordinator_user_id` pattern (role-only `modifyQueryUsing`), not the `municipality_id` pattern — even though the *outcome* (campaign-scoped dropdown) is identical, which is what D-04 actually asked for.

**Primary recommendation:** Scaffold `app/Filament/Resources/AreaCoordinators/*` as a near-exact copy of `app/Filament/Resources/Coordinators/*` (Resource, Pages/Create+Edit+List, Schemas/Form, Tables/Table), swapping `role('coordinator')` → `role('area_coordinator')` and `UserRole::COORDINATOR` → `UserRole::AREA_COORDINATOR`, dropping the `also_leader` Toggle. Add `Select::make('area_coordinator_user_id')->relationship('areaCoordinator', 'name', modifyQueryUsing: fn (Builder $q) => $q->role(UserRole::AREA_COORDINATOR->value)->orderBy('name'))->searchable()->preload()` to `CoordinatorForm` — campaign scoping is automatic via `CampaignMembershipScope`, no manual closure needed. Add `TextColumn::make('coordinators_count')->counts('coordinators')` to the new table, mirroring `CoordinatorsTable`'s existing `leaders_count`/`counts('leaders')` column exactly.

## User Constraints

### Locked Decisions (from CONTEXT.md)

- **D-01:** `AreaCoordinatorResource`'s form mirrors `CoordinatorForm` exactly (Información personal, Contacto, Ubicación, Acceso sections) **minus** the "también será líder" toggle — dropped entirely, not hidden.
- **D-02:** Assignment happens via a single `Select` field added to `CoordinatorForm` (not a dedicated reassignment Action, not a bulk-action) — same UX pattern as `municipality_id`. One place to set/change it: the coordinador's own create/edit form.
- **D-03:** The selector is **optional/nullable** — a coordinador can be saved with no articulador assigned. Matches ARTIC-03 and the already-nullable `area_coordinator_user_id` FK from Phase 12.
- **D-04:** The articulador dropdown on `CoordinatorForm` is filtered to the active/current campaign, mirroring the campaign-scoping *outcome* of the `municipality_id` Select. An admin cannot pick a Campaign-B articulador while editing a Campaign-A coordinador.
- **D-05:** `AreaCoordinatorsTable` adds one extra column: a count of assigned coordinadores per articulador (`withCount('coordinators')` or equivalent).

### Claude's Discretion

- Exact resource/page/schema/table class names and directory layout for `AreaCoordinatorResource` — mirror `app/Filament/Resources/Coordinators/*`'s exact structure unless codebase conventions suggest otherwise.
- Role/campaign-attachment logic for `CreateAreaCoordinator::afterCreate()` — follow `CreateCoordinator::afterCreate()`'s exact pattern (`assignRole()`, `attachActiveCampaign()` via `campaigns()->syncWithoutDetaching()`), adapted for `UserRole::AREA_COORDINATOR`.
- Whether the `CoordinatorForm` articulador Select uses `relationship()` (Filament-native) or a plain `Select::make('area_coordinator_user_id')->options(...)` — planner's call based on which composes better with the existing `municipality_id` closure-filtering pattern.
- Navigation label, icon, sort order for `AreaCoordinatorResource` in the admin panel's "Gestión" nav group — follow `CoordinatorResource`'s conventions (Spanish labels, `Heroicon::OutlinedUsers` family).

### Deferred Ideas (OUT OF SCOPE)

- `area_coordinator`'s own self-service panel access (`canAccessPanel()` wiring) — explicitly Phase 15's scope, not touched here.
- Bulk-reassignment action (reassign multiple coordinadores to one articulador at once) — explicitly not chosen; single-record Select on `CoordinatorForm` is sufficient.
- Making `TopCoordinatorsTable`/`ApoyosLideresCoordinadoresTable`/`TerritorialOwnershipTable` display/label articulador rows — still deferred from Phase 13's D-02, remains deferred until Phase 15.

## Project Constraints (from CLAUDE.md)

- **Explicit `use` statements only** — never namespace aliases, never inline `\App\Models\User::class` paths. Applies to every new class in `AreaCoordinators/*`.
- **Filament 4 conventions**: static `make()` methods, `relationship()` on form components for select options, layout components (`Section`, etc.) live under `Filament\Schemas\Components`, actions extend `Filament\Actions\Action` (no `Filament\Tables\Actions` namespace), icons via `Filament\Support\Icons\Heroicon` enum.
- **Filament 4 structure convention** (already followed by `Coordinators/`): `Resources/{Name}/{Name}Resource.php`, `Pages/`, `Schemas/`, `Tables/` — not the old single-file-per-resource layout.
- **No `all` pagination page method by default** (v4 change) — not directly relevant here but keep in mind if list customization is touched.
- **Grid/Section/Fieldset no longer span all columns by default** (v4 change) — already handled correctly in `CoordinatorForm`'s explicit `->columns(2)` calls; mirror the same explicit columns.
- **PHP**: always curly braces, constructor property promotion, explicit return types + param type hints, PHPDoc over inline comments.
- **Laravel**: use `php artisan make:` for all new files (`--no-interaction`), Eloquent with return type hints, avoid `DB::` facade.
- **Pint**: run `vendor/bin/pint --dirty` before finalizing.
- **Tests**: every change must have a Pest test (`php artisan make:test --pest <name>`); this project's existing regression style for this exact model is `tests/Feature/Filament/CoordinatorResourceCampaignTest.php` (Livewire component tests via `Livewire::test(...)`) and `tests/Feature/AreaCoordinatorHierarchyTest.php` (plain model-relation Pest tests) — new tests should follow one of these two shapes depending on what's being verified.
- **GSD workflow enforcement**: this research feeds `/gsd:plan-phase` → `/gsd:execute-phase`; no direct repo edits outside that flow.
- **Naming**: user's memory says "English backend, Spanish UI" — new class/method/property names in English, all Filament labels/notifications in Spanish (matches every existing resource: `'Coordinadores'`, `'Nombre completo'`, etc.).

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| ARTIC-01 | Superadmin/admin_campaign puede crear un usuario con rol Articulador (`area_coordinator`) | `AreaCoordinatorResource` mirrors `CoordinatorResource` (already admin-panel-accessible to `super_admin`/`admin_campaign`/`reviewer` via existing `canAccessPanel()` — no panel change needed); `CreateAreaCoordinator::afterCreate()` mirrors `CreateCoordinator::afterCreate()`'s `assignRole(UserRole::AREA_COORDINATOR->value)` + campaign attachment; `CoordinatorForm`'s new `area_coordinator_user_id` Select lets the admin wire the assignment, filtered by role + auto campaign-scoped via `CampaignMembershipScope` |
| ARTIC-03 | Coordinador sigue funcionando exactamente igual que hoy, tenga o no un articulador asignado | Phase 13 already made the 3 audited call sites + `CoordinatorPolicy` correct for this — this phase only adds a nullable, purely-organizational FK selector; `area_coordinator_user_id` is not read by any coordinador-facing dashboard/export/panel code (confirmed: `teamCoordinatorUserIds()`, `CoordinatorPolicy`, and all Phase 13 audited call sites are additive-only and gated on the *actor* having the `area_coordinator` role, never on the coordinador's own `area_coordinator_user_id` value affecting their own experience) |

</phase_requirements>

## Standard Stack

No new dependencies. This phase uses only what's already installed and used identically elsewhere in the codebase.

### Core (already installed, verified in use)
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| filament/filament | ^4.0 (v4) | Admin Resource/Form/Table scaffolding | Project standard, `Coordinators/*` is the exact reference implementation |
| spatie/laravel-permission | 6.22 | `->role()` query scope, `assignRole()` | Already used identically by `CoordinatorResource`/`LeaderResource` |
| pestphp/pest | v4 | Regression tests | Project test standard; `Livewire::test()` + plain Pest for model relations |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `relationship()`-based Select for `area_coordinator_user_id` | Plain `Select::make('area_coordinator_user_id')->options(fn () => ...)` | `relationship()` is what every comparable User→User FK Select in this codebase uses (`coordinator_user_id` in `LeaderForm`, `UserForm`); a plain `options()` closure would need to manually replicate role filtering, ordering, and searchability that `relationship()` gets for free, plus manual `dehydrated()`/save wiring. No evidence in the codebase of a reason to deviate — recommend `relationship()`. |

**Installation:** None required — no new packages.

## Architecture Patterns

### Recommended Project Structure
```
app/Filament/Resources/AreaCoordinators/
├── AreaCoordinatorResource.php          # mirrors CoordinatorResource.php, role('area_coordinator')
├── Pages/
│   ├── CreateAreaCoordinator.php        # mirrors CreateCoordinator.php, no also_leader branch
│   ├── EditAreaCoordinator.php          # mirrors EditCoordinator.php
│   └── ListAreaCoordinators.php         # mirrors ListCoordinators.php
├── Schemas/
│   └── AreaCoordinatorForm.php          # mirrors CoordinatorForm.php minus the also_leader Toggle
└── Tables/
    └── AreaCoordinatorsTable.php        # mirrors CoordinatorsTable.php + coordinators_count column
```

### Pattern 1: Resource scoped to a single Spatie role, self-referential User FK
**What:** A Filament Resource whose model is `User` but whose list/edit scope is narrowed to one Spatie role via `getEloquentQuery()`.
**When to use:** Any admin-manageable "sub-type" of `User` (coordinador, líder, articulador all follow this).
**Example (verbatim from `CoordinatorResource.php`, swap role string):**
```php
// Source: app/Filament/Resources/Coordinators/CoordinatorResource.php (existing code, verified in repo)
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()->role('area_coordinator');
}
```

### Pattern 2: Campaign-scoped `relationship()` Select pointing at another User (the correct precedent for D-04)
**What:** A `Select` on a `User`-model form that lets you pick another `User` filtered by role, relying on `User`'s global `CampaignMembershipScope` for campaign isolation instead of a manual closure.
**When to use:** Any User→User FK select (coordinador→articulador, líder→coordinador). This is a *stronger* precedent than `municipality_id` for this specific case because the related model is `User` (globally scoped), not `Municipality` (not globally scoped).
**Example (verbatim from `LeaderForm.php`, the exact structural twin of what's needed):**
```php
// Source: app/Filament/Resources/Leaders/Schemas/LeaderForm.php (existing code, verified in repo)
Select::make('coordinator_user_id')
    ->label('Coordinador')
    ->relationship(
        name: 'coordinator',
        titleAttribute: 'name',
        modifyQueryUsing: fn (Builder $query) => $query->role(UserRole::COORDINATOR->value)->orderBy('name'),
    )
    ->required()
    ->searchable()
    ->preload()
```
**Adapted for this phase (recommended shape, not yet in codebase):**
```php
Select::make('area_coordinator_user_id')
    ->label('Articulador')
    ->relationship(
        name: 'areaCoordinator',
        titleAttribute: 'name',
        modifyQueryUsing: fn (Builder $query) => $query->role(UserRole::AREA_COORDINATOR->value)->orderBy('name'),
    )
    ->searchable()
    ->preload()
    // no ->required() — D-03 says nullable/optional
```
**Why the campaign filter is automatic:** `User` uses `HasCampaignMembershipScope` (boots `CampaignMembershipScope` as a global scope). That scope calls `CampaignContext::currentCampaignId(Auth::user())` — the *acting* admin's current campaign context — and applies `whereHas('campaigns', fn ($q) => $q->where('campaigns.id', $campaignId))` to every `User` query, including the one Filament builds internally for a `relationship()` Select's related-model query. This is verified by `LeaderForm`/`UserForm`'s existing `coordinator_user_id` Selects, which have zero manual campaign-scoping code and are already in production.

### Pattern 3: `withCount`-style related-count table column
**What:** A `TextColumn` showing the count of a `hasMany` relationship, using Filament's `counts()` method (from `CanAggregateRelatedModels` concern) rather than manually calling `withCount()` on the query.
**When to use:** Exactly D-05's requirement.
**Example (verbatim from `CoordinatorsTable.php`, the direct precedent):**
```php
// Source: app/Filament/Resources/Coordinators/Tables/CoordinatorsTable.php (existing code, verified in repo)
TextColumn::make('leaders_count')
    ->counts('leaders')
    ->label('Líderes')
    ->sortable()
    ->toggleable(),
```
**Adapted for this phase:**
```php
TextColumn::make('coordinators_count')
    ->counts('coordinators')
    ->label('Coordinadores')
    ->sortable()
    ->toggleable(),
```
`counts('coordinators')` targets `User::coordinators(): HasMany` (already exists from Phase 12, `hasMany(User::class, 'area_coordinator_user_id')`). No `->visible(fn () => Schema::hasColumn(...))` guard needed — unlike `leaders_count`'s guard (which exists because `coordinator_user_id` migration history required a defensive check), `area_coordinator_user_id` and its migration are already fully applied (Phase 12, `2026_08_10_120000_add_area_coordinator_to_users_table.php`).

### Pattern 4: `afterCreate()` role assignment + campaign attachment
**What:** After a new `User` record is created via a Filament `CreateRecord` page, assign the Spatie role and attach the acting admin's active campaign via the `campaign_user` pivot.
**Example (verbatim from `CreateCoordinator.php`, direct precedent):**
```php
// Source: app/Filament/Resources/Coordinators/Pages/CreateCoordinator.php (existing code, verified in repo)
protected function afterCreate(): void
{
    $this->record->assignRole(UserRole::COORDINATOR->value);
    $this->attachActiveCampaign();
}

private function attachActiveCampaign(): void
{
    $campaignId = CampaignContext::resolveUnambiguousCampaignId();
    if (! $campaignId) {
        return;
    }
    $this->record->campaigns()->syncWithoutDetaching([
        $campaignId => [
            'role_id' => Role::findByName(UserRole::COORDINATOR->value)->id,
            'assigned_at' => now(),
            'assigned_by' => auth()->id(),
        ],
    ]);
}
```
Adapt by replacing `UserRole::COORDINATOR` with `UserRole::AREA_COORDINATOR` and dropping the `also_leader` branch entirely (D-01 — articulador is never also a líder). Also mirror `mutateFormDataBeforeCreate()`'s "no active campaign selected" guard (`Halt` + notification) and `EditCoordinator`'s `afterSave()` self-healing campaign-attachment logic — both are role-agnostic patterns that should carry over unchanged.

### Anti-Patterns to Avoid
- **Manually re-implementing campaign scoping on the articulador Select:** Don't copy `municipality_id`'s explicit `CampaignContext::currentCampaign()`-based closure verbatim — it's solving a problem (`Municipality` has no global scope) that doesn't exist for `User`→`User` selects. Doing so would be redundant, and worse, subtly different logic (department-fallback branches that make no sense for role-scoped User picks) that's easy to get wrong. Use the `coordinator_user_id` pattern (role-filter only) instead.
- **Adding `->required()` to the articulador Select:** D-03 is explicit — it must stay optional/nullable. Don't copy `LeaderForm`'s `coordinator_user_id`'s `->required()` since that's a different (mandatory) relationship.
- **Re-touching `CoordinatorPolicy` or the Phase 13 audited call sites:** Explicitly out of scope per CONTEXT.md's Phase Boundary — Phase 13 already made them correct; this phase is UI-only.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Campaign-scoped User dropdown | Manual `CampaignContext`-based query closure | Filament's `relationship()` + role filter, relying on `CampaignMembershipScope` global scope | Already solved, already tested, already the pattern for the two other User→User FK selects in this codebase (`coordinator_user_id` in `LeaderForm`/`UserForm`) |
| Related-record count column | Manual `withCount()` + `TextColumn::make(...)->state(fn ($record) => ...)` | `TextColumn::make('{relation}_count')->counts('{relation}')` | Filament's `CanAggregateRelatedModels` concern (`counts()`) already handles eager-loading and sortability; `leaders_count` is the exact working precedent |
| Role assignment + campaign pivot attach on create | Custom logic in a new Action/Observer | `afterCreate()` on the `CreateRecord` page, mirroring `CreateCoordinator::afterCreate()` | Same pattern used by every "create a role-scoped User" resource in this codebase; changing the approach here would introduce an inconsistent second pattern for no benefit |

**Key insight:** This phase has essentially zero net-new logic. Every piece needed (role-scoped Resource, role-filtered User Select, related-count column, afterCreate role+campaign attachment) already exists as a working, tested pattern elsewhere in this exact codebase for the `coordinador`/`líder` case. The job is disciplined mirroring, not invention — deviating from the existing patterns is the primary risk, not missing functionality.

## Common Pitfalls

### Pitfall 1: Copying `municipality_id`'s closure instead of `coordinator_user_id`'s closure for D-04
**What goes wrong:** CONTEXT.md's D-04 explicitly says to mirror `municipality_id`'s pattern. A literal reading could lead to porting `CampaignContext::currentCampaign()`-based department/municipality fallback branches onto a User select, which don't apply (there's no "department" concept for a User FK) and would be redundant with the automatic global scope.
**Why it happens:** The discuss-phase agent chose `municipality_id` as the analogy because it's the only *visible* campaign-scoped Select in `CoordinatorForm` at discussion time — it didn't need to reason about `User`'s global scope.
**How to avoid:** Use the `coordinator_user_id` pattern (`LeaderForm`/`UserForm`) — role-filter only, `CampaignMembershipScope` handles the rest. Verify by testing: create two campaigns, an articulador in Campaign A only, and confirm they don't appear in the Select while editing a Campaign-B coordinador.
**Warning signs:** If the closure references `$campaign?->department_id` or `$campaign?->municipality_id` for a User select, that's a sign of over-copying the wrong precedent.

### Pitfall 2: Forgetting `AreaCoordinatorForm` needs `->columns(2)` per section and explicit column spans (Filament v4)
**What goes wrong:** In Filament v4, `Section`/`Grid`/`Fieldset` no longer span all columns by default (breaking v3 behavior). Copying `CoordinatorForm`'s structure without also copying its explicit `->columns(2)` calls per `Section` produces a visually broken single-column form.
**How to avoid:** Copy the `->columns(2)` calls on every `Section::make(...)` verbatim, same as `CoordinatorForm`.

### Pitfall 3: `also_leader` Toggle removal leaving orphaned `dehydrated(false)` wiring
**What goes wrong:** D-01 says drop the toggle entirely from `AreaCoordinatorForm`. If `CreateAreaCoordinator::afterCreate()` is copy-pasted from `CreateCoordinator::afterCreate()` without also removing the `if (! empty($this->data['also_leader']))` branch, it silently does nothing (since `also_leader` is never in `$this->data` for this form) — not a functional bug, but dead code that will confuse future readers and fail a code-review-quality bar.
**How to avoid:** Explicitly delete the `also_leader` branch in `AreaCoordinatorForm`'s port of `afterCreate()`, don't just leave the Toggle out of the schema.

### Pitfall 4: `CoordinatorPolicy` (`User::class` global policy) accidentally affecting `AreaCoordinatorResource`
**What goes wrong:** `AuthServiceProvider` registers `User::class => CoordinatorPolicy::class` globally — Filament auto-authorizes `view`/`update` on any `User`-model Resource against it if a policy exists for the model. A naive assumption might be "will this block admin actions on articulador records?"
**Why it's actually fine:** `CoordinatorPolicy::authorizeOwnership()` only restricts when the **actor** `hasRole(AREA_COORDINATOR)` — `super_admin`/`admin_campaign`/`reviewer` (the only roles with `admin` panel access, and the only ones who'd touch `AreaCoordinatorResource`) hit `Response::allow()` immediately on the first check. No change needed, but this is worth one explicit regression test (an admin can view/edit/delete an articulador record without policy interference) since it's a "prove the negative" claim, not something visually obvious in the UI.
**Warning signs:** If a super_admin/admin_campaign user gets an unexpected 403 on `AreaCoordinatorResource` actions, check `CoordinatorPolicy` first before assuming it's a new bug.

### Pitfall 5: `EditCoordinator`/`CreateCoordinator`'s existing `afterCreate()`/`afterSave()` don't need to touch `area_coordinator_user_id` at all
**What goes wrong:** Assuming the new Select needs custom save-handling logic (like the pivot-table `attachActiveCampaign()` pattern) because it "feels" like a relationship field.
**Why it's actually fine:** `area_coordinator_user_id` is a **plain column** on `users` (already `$fillable`, already nullable, already migrated in Phase 12), not a pivot/many-to-many relationship. A `relationship()`-configured Select still ultimately dehydrates to a plain `area_coordinator_user_id` value in form state, which Filament's base `CreateRecord`/`EditRecord` `handleRecordCreation()`/`save()` persist automatically via mass-assignment — no code needed in `afterCreate()`/`afterSave()` beyond what already exists. Confirmed by comparing to `municipality_id`, which is the same shape (plain nullable FK column, `relationship()` Select, zero special handling in `CreateCoordinator`/`EditCoordinator`).
**How to avoid:** Don't add unnecessary save-hook code for this field. If it turns out not to persist correctly, the bug is almost certainly in the Select's `relationship()` config (e.g. `dehydrated(false)` accidentally set), not in the page class.

## Runtime State Inventory

Not applicable — this is a greenfield Resource-and-form-field addition, not a rename/refactor/migration phase. No existing data, external service config, OS-registered state, secrets, or build artifacts need updating.

## Code Examples

### Full mirrored `AreaCoordinatorResource.php` shape
```php
// Adapted from app/Filament/Resources/Coordinators/CoordinatorResource.php
namespace App\Filament\Resources\AreaCoordinators;

use App\Filament\Resources\AreaCoordinators\Pages\CreateAreaCoordinator;
use App\Filament\Resources\AreaCoordinators\Pages\EditAreaCoordinator;
use App\Filament\Resources\AreaCoordinators\Pages\ListAreaCoordinators;
use App\Filament\Resources\AreaCoordinators\Schemas\AreaCoordinatorForm;
use App\Filament\Resources\AreaCoordinators\Tables\AreaCoordinatorsTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AreaCoordinatorResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Articuladores';

    protected static UnitEnum|string|null $navigationGroup = 'Gestión';

    protected static ?string $modelLabel = 'Articulador';

    protected static ?string $pluralModelLabel = 'Articuladores';

    protected static ?int $navigationSort = 2; // see Open Questions — nav ordering vs. CoordinatorResource's 2

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->role('area_coordinator');
    }

    public static function form(Schema $schema): Schema
    {
        return AreaCoordinatorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AreaCoordinatorsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAreaCoordinators::route('/'),
            'create' => CreateAreaCoordinator::route('/create'),
            'edit' => EditAreaCoordinator::route('/{record}/edit'),
        ];
    }
}
```

### Pest test pattern for ARTIC-01 (Livewire, mirroring `CoordinatorResourceCampaignTest.php`)
```php
test('creating an area coordinator attaches it to the active campaign', function () {
    CampaignContext::setCampaignId($this->campaign->id);

    Livewire::test(CreateAreaCoordinator::class)
        ->fillForm(areaCoordinatorFormData($this->municipality))
        ->call('create')
        ->assertHasNoFormErrors();

    $areaCoordinator = User::where('email', 'articulador@example.com')->first();

    expect($areaCoordinator)->not->toBeNull();
    expect($areaCoordinator->hasRole(UserRole::AREA_COORDINATOR->value))->toBeTrue();
    expect($areaCoordinator->campaigns->pluck('id'))->toContain($this->campaign->id);
});
```

### Pest test pattern for the CoordinatorForm Select + ARTIC-03 regression (Livewire)
```php
test('admin can assign an articulador to a coordinador via CoordinatorForm select', function () {
    CampaignContext::setCampaignId($this->campaign->id);
    $areaCoordinator = User::factory()->create();
    $areaCoordinator->assignRole(UserRole::AREA_COORDINATOR->value);
    $areaCoordinator->campaigns()->attach($this->campaign->id);

    Livewire::test(CreateCoordinator::class)
        ->fillForm(coordinatorFormData($this->municipality, [
            'area_coordinator_user_id' => $areaCoordinator->id,
        ]))
        ->call('create')
        ->assertHasNoFormErrors();

    $coordinator = User::where('email', 'coordinador@example.com')->first();
    expect($coordinator->area_coordinator_user_id)->toBe($areaCoordinator->id);
});

test('coordinador without an articulador behaves identically (ARTIC-03, no regression)', function () {
    // existing coordinatorFormData() with no area_coordinator_user_id key — must still
    // create/save successfully with area_coordinator_user_id left null.
});

test('articulador dropdown on CoordinatorForm only shows articuladores from the active campaign', function () {
    // campaignA articulador visible while CampaignContext::setCampaignId(campaignA),
    // campaignB-only articulador must NOT appear in the Select's options.
});
```

## State of the Art

Not applicable — no external ecosystem/library version drift concerns here; this is 100% intra-codebase pattern-following, and the reference patterns (`CoordinatorResource`, `LeaderForm`, `counts()`) are all current (last touched in Phase 12/13, days before this research).

## Open Questions

1. **Where in `CoordinatorForm` should the `area_coordinator_user_id` Select live — inside the existing "Ubicación" section, or a new dedicated section?**
   - What we know: D-02 only specifies "a single Select field added to `CoordinatorForm`", not a section. `LeaderForm` uses a dedicated top section ("Coordinador") for its analogous required Select, but that Select also drives municipality auto-sync (`afterStateUpdated`), which the articulador Select does not need.
   - What's unclear: Whether a new minimal section (e.g. "Articulador" or "Jerarquía") is warranted for a single optional field, or whether it fits better appended to "Ubicación" (organizational-hierarchy fields already live there: `municipality_id`, `neighborhood_id`) or "Acceso".
   - Recommendation: Planner's/implementer's call — placing it in "Ubicación" (grouping it with other hierarchy/scoping fields) is a reasonable default that avoids adding a section for one field; a dedicated section is equally defensible if Spanish-UI clarity is prioritized. Not a functional risk either way.

2. **Navigation sort order for `AreaCoordinatorResource` within "Gestión"** (currently: Campaigns=1, Coordinators=2, Leaders=3, Voters=4, Invitations=5).
   - What we know: Articulador is hierarchically *above* Coordinador (articulador → coordinador → líder), which would argue for inserting it before Coordinators (i.e., renumbering Coordinators/Leaders/Voters/Invitations up by one) for nav-order-matches-hierarchy consistency.
   - What's unclear: Whether renumbering four existing resources' `$navigationSort` values is in-scope for this phase (it's a one-line change per file, low risk) or whether appending `AreaCoordinatorResource` at the end (sort=6) is preferred to minimize diff surface.
   - Recommendation: Given D-01–D-05 don't mention nav-order renumbering and CONTEXT.md's discretion note only asks to "follow `CoordinatorResource`'s conventions," the low-risk default is inserting at sort=2 and bumping Coordinators/Leaders/Voters/Invitations to 3/4/5/6 respectively (small mirror-and-shift diff) — matches hierarchy intuitively. If minimizing diff is preferred, append at sort=6 instead. Either is acceptable; flag for planner decision.

3. **Icon choice: `Heroicon::OutlinedUserGroup` vs. reusing `Heroicon::OutlinedUsers`.**
   - What we know: `CoordinatorResource`/`LeaderResource` both use `Heroicon::OutlinedUsers`. `UserRole::AREA_COORDINATOR->getIcon()` (used for role badges elsewhere) is `'heroicon-m-user-group'` — a different icon already associated with this role in the UI (e.g. role selector, badges).
   - What's unclear: Whether resource-level nav icon should match the role's existing badge icon (`user-group`) for visual consistency, or match sibling resources' icon (`users`) for nav-group consistency.
   - Recommendation: `Heroicon::OutlinedUserGroup` is a reasonable, low-risk choice (already the role's associated icon elsewhere in the UI, and visually distinct enough from Coordinadores/Líderes in the nav to signal "this is the grouping/organizational one"). Not a hard requirement — Claude's discretion per CONTEXT.md.

## Sources

### Primary (HIGH confidence — direct repo inspection, verified code in the actual codebase)
- `app/Filament/Resources/Coordinators/CoordinatorResource.php`, `Schemas/CoordinatorForm.php`, `Tables/CoordinatorsTable.php`, `Pages/CreateCoordinator.php`, `Pages/EditCoordinator.php`, `Pages/ListCoordinators.php` — the primary mirror target
- `app/Filament/Resources/Leaders/Schemas/LeaderForm.php`, `app/Filament/Resources/Users/Schemas/UserForm.php` — precedent for `coordinator_user_id`-style role-filtered `relationship()` Select on a `User` FK (the correct D-04 analogy)
- `app/Models/User.php` — `areaCoordinator()`, `coordinators()` relations, `HasCampaignMembershipScope` trait usage, `canAccessPanel()`
- `app/Models/Scopes/CampaignMembershipScope.php`, `app/Models/Concerns/HasCampaignMembershipScope.php` — confirms automatic campaign scoping on all `User` queries
- `app/Services/CampaignContext.php` — `currentCampaignId()`/`resolveUnambiguousCampaignId()` resolution logic
- `app/Enums/UserRole.php` — confirms `AREA_COORDINATOR` case, Spanish label "Articulador"
- `app/Policies/CoordinatorPolicy.php`, `app/Providers/AuthServiceProvider.php` — confirms policy doesn't affect admin actions on `AreaCoordinatorResource`
- `database/migrations/2026_08_10_120000_add_area_coordinator_to_users_table.php` — confirms `area_coordinator_user_id` is nullable, indexed, `constrained('users')->nullOnDelete()`
- `database/seeders/RoleSeeder.php` — confirms `area_coordinator` role auto-seeded via `UserRole::cases()` loop
- `vendor/filament/support/src/Concerns/CanAggregateRelatedModels.php` — confirms `counts()` API signature (`string|array|Closure|null $relationships`)
- `tests/Feature/Filament/CoordinatorResourceCampaignTest.php`, `tests/Feature/AreaCoordinatorHierarchyTest.php`, `tests/Feature/ArticuladorTeamResolutionTest.php`, `tests/Feature/Policies/CoordinatorPolicyTest.php`, `tests/Feature/Filament/TopCoordinatorsTableTest.php` — established Pest test patterns and precedent assertions (`assertTableColumnStateSet`, `Livewire::test()->fillForm()->call('create')`)
- `.planning/phases/14-articulador-admin-resource-hierarchy-wiring/14-CONTEXT.md`, `.planning/REQUIREMENTS.md`, `.planning/phases/12-.../12-CONTEXT.md` references, `.planning/phases/13-.../13-CONTEXT.md`/`13-VERIFICATION.md` references

### Secondary (MEDIUM confidence)
None needed — this phase required no external/ecosystem research; everything is verifiable directly in the repo.

### Tertiary (LOW confidence)
None.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — zero new dependencies, everything already installed and in use
- Architecture: HIGH — every pattern needed has a direct, working, tested precedent in this exact codebase (not just "similar" — structurally identical: `User`-model role-scoped Resource, `User`→`User` role-filtered `relationship()` Select, `counts()` table column, `afterCreate()` role+campaign attachment)
- Pitfalls: HIGH — derived from direct code inspection (global scope behavior, policy registration, Filament v4 Section column-span change) rather than general Filament knowledge

**Research date:** 2026-08-10
**Valid until:** Stable — this research is anchored to the current state of this specific codebase, not external library versions. Re-validate only if `Coordinators/*`, `User.php`, or `CampaignMembershipScope` change materially before this phase is planned/executed.

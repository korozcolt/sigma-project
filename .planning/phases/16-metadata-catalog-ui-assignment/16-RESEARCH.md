# Phase 16: Metadata Catalog UI & Assignment - Research

**Researched:** 2026-08-10
**Domain:** Filament v4 resources/schemas/bulk actions + Livewire-Volt/Flux UI + shared service layer (Laravel 12)
**Confidence:** HIGH (every API claim below verified against this repo's own `vendor/` source and existing code, not training data)

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Alcance de Subordinado Directo**
- **D-01:** Líder is excluded from the metadata assignment flow entirely — no menu item, tab, or action appears in the líder panel. Líder has zero User-type subordinates in the current data model (only registered Voters/Apoyos, which don't participate in the `users`-scoped metadata system). ROADMAP.md's success criterion 2 lists líder among "any superior," but since líder has no eligible subordinates, the assignment mechanism simply never has anyone for líder to act on — this is by design, not a gap. Downstream verifier should NOT flag "líder has no metadata UI" as a missing success criterion.
- **D-02:** Superadmin/admin_campaign can assign metadata to ANY user in the active campaign, without hierarchy restriction — not limited to "top of hierarchy only" (articuladores + orphaned coordinadores/leaders). Full visibility by design, consistent with their existing unrestricted access elsewhere in the app.
- **D-03:** "Direct subordinate" resolves per role as: coordinador → their `leaders()`; articulador → their `coordinators()`; superadmin/admin_campaign → any `User` in the active campaign. This is **explicitly different** from Phase 13's `User::teamCoordinatorUserIds()` (the transitive-team resolver built for AUTHZ-01/dashboard scoping) — META-03 requires direct subordinates only, not the full transitive team. The planner must NOT reuse `teamCoordinatorUserIds()` for this; a new resolution (a dedicated method or inline per-role logic) is needed.

**Ubicación de la UI de Asignación**
- **D-04:** Individual assignment lives as a section/tab inside the ALREADY-EXISTING edit forms (`EditCoordinator`, `EditLeader`, `EditAreaCoordinator`) — not a standalone Filament resource, not a table-row modal action.
- **D-05:** The assignment capability must exist in 3 places: the Admin panel (superadmin/admin_campaign — building on what already exists there), the Coordinador panel (coordinador assigns to their own líderes), and the Articulador panel (articulador assigns to their own coordinadores).

**Asignación Masiva (META-04)**
- **D-06:** Bulk assignment is a table `BulkAction` (same pattern already used in `CoordinatorsTable`) — select multiple rows, choose ONE key + ONE value, apply identically to every selected row.
- **D-07:** One key per bulk action — no multi-key repeater in a single bulk submit. Assigning two different keys means running the bulk action twice.

**Validación y Visualización por Tipo**
- **D-08:** `numeric`-typed keys are validated in the form with `TextInput::numeric()`, allowing 2 decimal places. The DB column (`user_metadata_values.value`) remains a plain `string` (per Phase 12's actual migration, not the conceptual "decimal" language in 12-CONTEXT.md D-05) — decimal precision is enforced at the Filament validation layer, not the schema layer.
- **D-09:** The assignment UI shows ONLY the current value per key (the most recent row for `(user_id, metadata_key_id)`) plus who assigned it and when — no expandable history view in this phase. Full history remains queryable in the DB (append-only per Phase 12 D-02) for future phases (META-07/META-08) if ever surfaced.

### Claude's Discretion

- Exact naming of the new "direct subordinate" resolver on `User` (e.g. a new method vs inline per-role logic in the assignment component) — follow existing method-naming conventions (`coordinators()`, `leaders()`).
- For `select`-typed keys, the assignment form's value field renders a `Select` populated dynamically from that key's `options` JSON array — this follows directly from Phase 12's schema shape and META-01, not an open question.
- For `date`-typed keys, a Filament `DatePicker`; for `text`-typed keys, a plain `TextInput`.
- Exact Filament resource naming (e.g. `MetadataKeyResource`), mirroring `GremioResource`'s structure per Phase 12's code_context recommendation.
- Where exactly within each panel's navigation the metadata tab/section sits (ordering, icon, label wording).
- Bulk action target-user validation is implicit — each panel's own table is already scoped to that role's own subordinates (a coordinador's table only ever lists their own leaders), so no additional cross-checking is needed beyond what table scoping already provides.

### Deferred Ideas (OUT OF SCOPE)

- Point-in-time/effective-dated metadata queries and expandable assignment-history view in the UI — tracked as META-07 in REQUIREMENTS.md v2 section (D-09 explicitly keeps this phase to current-value-only).
- Metadata rollup/aggregation dashboards — tracked as META-08 in REQUIREMENTS.md v2 section.
- Extending metadata assignment to Voters/Apoyos (to give líder something to assign to) — considered and explicitly rejected for this phase (D-01); would require new schema, out of scope.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| META-01 | Superadmin puede crear/editar/desactivar llaves del catálogo (nombre + tipo: numérico, texto, fecha, selección con opciones) | `GremioResource` structure (verified, 6 files); `Repeater::make(...)->simple(TextInput)` for the `options` JSON array (verified in `QuestionsRelationManager.php:110`); `AuditLogResource::canAccess()` super-admin gate (verified `AuditLogResource.php:47`); `Select` + `->live()` + `Get` for type-conditional visibility (verified `QuestionsRelationManager.php:106,118`) |
| META-02 | Las llaves no se pueden crear libremente fuera del catálogo (freeform prohibido) | Structural: every assignment surface uses `Select`/`flux:select` sourced from `MetadataKey::query()->where('is_active', true)`; no free-text key input exists. `metadata_key_id` FK is `restrictOnDelete` (verified migration). Verification = a grep-style test asserting no `TextInput`/`flux:input` binds to a key *name* outside `MetadataKeyResource`, plus a service-level `exists:metadata_keys,id` + `is_active` validation |
| META-03 | Un superior asigna un valor del catálogo a un subordinado directo | `User::leaders()` / `User::coordinators()` (verified `User.php:143,153`); new `directSubordinateQuery()` resolver (D-03); `CoordinatorPolicy` + `Gate::before` campaign guard (verified `AuthServiceProvider.php:45`); `HasCampaignMembershipScope` global scope on `User` (verified) |
| META-04 | Asignación masiva del mismo valor a varios subordinados | Filament: `BulkAction::make()` inside `BulkActionGroup` in `->toolbarActions()` — exact working pattern at `MessageTemplatesTable.php:172-186`. Volt: no existing multi-select table exists — must be built with `flux:checkbox` array binding (precedent `my-voters.blade.php:236`) + `flux:checkbox.all` (verified present in Flux free) + `flux:modal` (precedent `leaders.blade.php:126`) |
| META-05 | Cada asignación queda auditada (quién, qué, a quién, cuándo) | Native to the schema — `assigned_by` + `assigned_at` columns already exist and `UserMetadataValue::assignedByUser()` relation is already defined (verified `UserMetadataValue.php`). Append-only design (Phase 12 D-02) means the audit trail IS the value table. `AuditObserver` is NOT needed here (see "Don't Hand-Roll") |
| META-06 | Escrituras atómicas por llave (sin condiciones de carrera) | Guaranteed by construction if writes are pure `INSERT` (`UserMetadataValue::create()`), never `updateOrCreate`/`UPDATE`. One real gap found: `assigned_at` has **1-second resolution**, so `ORDER BY assigned_at DESC` alone is non-deterministic for same-second writes — must be `ORDER BY assigned_at DESC, id DESC`. Full analysis in the "META-06: Atomicity Analysis" section below |
</phase_requirements>

## Project Constraints (from CLAUDE.md)

The planner MUST verify plans comply with every directive below. These carry the same authority as locked decisions.

| Directive | Applies to this phase |
|-----------|----------------------|
| **Explicit `use` statements only — NEVER namespace aliases (`Forms\Components\Select`) or inline paths (`\App\Models\User::class`)** | Every new Filament schema/table file. Violation causes runtime errors per CLAUDE.md |
| `php artisan make:` for all new files, always `--no-interaction` | `make:filament-resource`, `make:livewire`, `make:test --pest` |
| Use Filament Artisan commands; verify params via `list-artisan-commands` | Creating `MetadataKeyResource` |
| Filament v4: all actions extend `Filament\Actions\Action`; NO `Filament\Tables\Actions` namespace | `BulkAction` imports come from `Filament\Actions\BulkAction` |
| Filament v4: layout components live in `Filament\Schemas\Components` (Section, Grid, Tabs…) | `Section`, `View`, `Livewire` imports |
| Filament v4 structure: `Schemas/Components/` \| `Tables/Columns/` \| `Tables/Filters/` \| `Actions/` | New resource file layout |
| Thin controllers — logic in Actions/Services with `handle()` or `execute()` | The shared `MetadataAssignmentService` |
| Never `env()` outside config files | N/A but applies |
| Eloquent with return type hints; avoid `DB::`, prefer `Model::query()`; eager load to prevent N+1 | Current-values query must eager-load `metadataKey` + `assignedByUser` |
| New models get factories + seeders | Models already exist (Phase 12); factories exist. A **seeder for the initial catalog keys** is a gap — see Open Questions |
| Validation in Form Request classes only, never inline in controllers | No controllers here; Livewire `#[Validate]` / Filament field rules are the equivalent |
| Every change must have a test; run affected tests before finishing | Pest 4 |
| `php artisan make:test --pest <name>`; use factories | `MetadataKeyFactory` / `UserMetadataValueFactory` exist |
| Status assertions: `assertForbidden()` / `assertNotFound()`, not `assertStatus(403)` | Authorization tests |
| Run `vendor/bin/pint --dirty` before finalizing | Every plan's last step |
| Naming: `PascalCase` classes, `kebab-case.blade.php` views, `camelCase` methods, `snake_case` columns | New files |
| **English backend identifiers, Spanish user-facing text — never mixed** (project memory, reinforced by every existing file) | All labels/notifications in Spanish; all class/method/property names in English |
| Use Flux components when available, fallback to Blade | Volt side only — see the Flux/Filament asset boundary pitfall below |
| Reuse existing components before creating new ones; no new base folders without approval | Mirror `Gremios/`; don't invent a new top-level dir |
| Frontend not updating? ask user to run `npm run build` | Vite manifest is required for the HTTP-level tests |

## Summary

This phase is **UI + business logic only** — the schema, models, relations, and factories all landed in Phase 12 and are verified working (`MetadataCatalogSchemaTest` passes 6/6 in 0.77s). Nothing new needs installing.

The single most important architectural finding is that **this codebase has a hard rendering boundary between its Filament panels and its Volt/Flux pages, and one shared UI component cannot cross it.** Filament panels load `resources/css/filament/theme.css`, which does not import `flux.css`, and Filament's layout never emits `@fluxScripts`. Empirically: there are **zero** `flux:` usages anywhere under `resources/views/filament/`, and the one Livewire component that renders inside a Filament panel (`App\Livewire\SaldosBadge`) uses `x-filament::dropdown` / `x-filament::badge` exclusively. Any plan that proposes "build the metadata panel once in Flux and drop it into both" will produce a visually broken, non-interactive widget inside `/admin`.

The correct answer to "how do we build this once" is therefore **share the logic, not the markup** — and this codebase already has an exact, load-bearing precedent for that: `App\Services\IdentityLookupService` is resolved via `app(IdentityLookupService::class)` from four Filament schema files (`CoordinatorForm`, `LeaderForm`, `AreaCoordinatorForm`, `VoterForm`) *and* five Volt blade components (`create-leader`, `create-coordinator`, `register-leader`, `register-voter`, `leader-add-voter`). Follow that pattern verbatim: one `App\Services\MetadataAssignmentService` holding subordinate resolution, catalog lookup, type-aware validation, current-value resolution, and the append-only write; then two thin presentation layers that both call into it.

Two corrections to assumptions carried in `16-CONTEXT.md` need flagging to the planner. First, `CoordinatorsTable.php` contains **no custom `BulkAction`** — only `DeleteBulkAction::make()`. The real working custom-BulkAction pattern lives in `MessageTemplatesTable.php:172-186` and `MessagesTable.php:216-224`, and both of those use the **deprecated** `->bulkActions()` method; Filament v4.2.0's `HasBulkActions.php:33` marks it `@deprecated Use toolbarActions() instead`. New code must use `->toolbarActions()`, which is what `CoordinatorsTable`/`LeadersTable`/`AreaCoordinatorsTable` already do. Second, `Action::form()` is deprecated in v4 (`HasSchema.php:124`) — bulk-action modal fields go in `->schema([...])`.

**Primary recommendation:** Build `App\Services\MetadataAssignmentService` first (Wave 0/1), then fan out four independent UI plans that all consume it — `MetadataKeyResource` (mirror `Gremios/`), the Filament `Section`-with-`headerActions` assignment block plus `BulkAction` on the admin tables, the Flux `App\Livewire\MetadataAssignmentPanel` embedded in both Volt edit pages, and the Flux checkbox+modal bulk UI on both Volt list pages. Resolve "current value" with `ORDER BY assigned_at DESC, id DESC` everywhere — that tie-break is the actual substance of META-06.

## Standard Stack

### Core — nothing new to install

| Library | Installed Version | Purpose | Why Standard |
|---------|-------------------|---------|--------------|
| `filament/filament` | **v4.2.0** (verified `composer.lock`) | Admin panel resource, schemas, tables, bulk actions | Already the admin panel for all 5 panels |
| `laravel/framework` | v12 | Eloquent, validation, transactions | — |
| `livewire/livewire` | v3 | Reactive components (`App\Livewire\*`) | — |
| `livewire/volt` | v1 | Volt pages for coordinador/articulador panels | Existing convention for those two panels |
| `livewire/flux` | v2 (FREE tier) | UI components on Volt pages only | Existing convention; `checkbox`, `checkbox.all`, `checkbox.group`, `modal`, `select`, `input`, `badge`, `button` all verified present |
| `pestphp/pest` | v4 | Tests | Project mandate |
| `spatie/laravel-permission` | 6.22 | `hasRole()` / `User::role()` scope | — |

**Installation:** none. `composer require` / `npm install` are NOT needed for this phase.

### Verified Flux free-tier components available for the Volt side

Confirmed by inspecting `vendor/livewire/flux/stubs/resources/views/flux/`:
- `checkbox/index.blade.php`, `checkbox/all.blade.php`, `checkbox/group/index.blade.php`, `checkbox/indicator.blade.php`
- plus `modal`, `select`, `input`, `badge`, `button`, `heading`, `text`, `icon`, `field` (per CLAUDE.md's free list)

`flux:checkbox.all` is the "select all in this group" control — it exists in the free tier and is the right primitive for the bulk-selection header checkbox.

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Shared `MetadataAssignmentService` + 2 UI layers | One `App\Livewire\MetadataAssignmentPanel` embedded via `Filament\Schemas\Components\Livewire::make()` AND `<livewire:...>` | The Filament embed API is real and verified (`vendor/filament/schemas/src/Components/Livewire.php`), and the nesting precedent exists (`<livewire:saldos-badge />` inside a Filament render hook). But a single blade view cannot serve both — Flux CSS/JS is absent in Filament panels. It would need a `$variant` view-switch, which duplicates the markup anyway while adding indirection. **Rejected.** |
| `Section` + `headerActions([Action::make('assign')->schema([...])])` for the Filament individual UI | Plain form fields bound to non-model keys with `->dehydrated(false)`, harvested in `afterSave()` via `$this->form->getRawState()` | The dehydration route works (`mutateFormDataBeforeFill` and `afterSave` hooks verified in `EditRecord.php:143,166`) but couples the metadata write to `EditRecord::save()`'s enclosing DB transaction (`EditRecord.php:151` `beginDatabaseTransaction()`), forces a user-record save just to change metadata, and muddies the per-key atomicity story. **Use the Action-in-Section route.** |
| Filament `RelationManager` on `metadataValues` | — | A relation manager renders as a CRUD table over the append-only history, directly contradicting D-09 (current value only) and inviting edit/delete of audit rows. **Rejected.** |
| `Repeater::simple(TextInput)` for `metadata_keys.options` | `TagsInput`, `KeyValue` | `KeyValue` is used in this repo (`MessageBatchForm.php:97`) but produces a map, not a list — wrong shape for `options`. `Repeater::simple()` produces a flat JSON array, matches `MetadataKey::$casts['options' => 'array']`, and there is a working in-repo example at `QuestionsRelationManager.php:110`. **Use `Repeater::simple()`.** |

## Architecture Patterns

### Recommended Structure

```
app/
├── Services/
│   └── MetadataAssignmentService.php       # SHARED LOGIC — both UI layers call this
├── Filament/Resources/MetadataKeys/         # mirror app/Filament/Resources/Gremios/
│   ├── MetadataKeyResource.php
│   ├── Schemas/MetadataKeyForm.php
│   ├── Tables/MetadataKeysTable.php
│   └── Pages/{ListMetadataKeys,CreateMetadataKey,EditMetadataKey}.php
├── Filament/Schemas/Components/             # (optional) shared Section builder
│   └── MetadataAssignmentSection.php        # static configure() returning a Section
├── Livewire/
│   └── MetadataAssignmentPanel.php          # Flux-side panel (Volt pages only)
└── Models/User.php                          # + metadataValues() relation
                                             # + directSubordinatesQuery() (D-03)

resources/views/livewire/
└── metadata-assignment-panel.blade.php      # Flux markup — NEVER rendered in Filament

resources/views/filament/components/
└── metadata-current-values.blade.php        # x-filament:: markup for the Section body
```

### Pattern 1: Shared service, two presentations (THE core pattern)

**What:** All domain logic lives in a plain `App\Services\*` class resolved with `app(...)`. Both the Filament schema closures and the Livewire/Volt components call it.

**When to use:** Every piece of behavior that must be identical across the admin panel and the coordinador/articulador panels — subordinate resolution, active-key lookup, type-aware validation, current-value resolution, the write itself.

**Why this exact shape:** It is the codebase's existing, proven cross-boundary sharing mechanism. `IdentityLookupService` is called from `CoordinatorForm.php:64`, `LeaderForm.php:108`, `AreaCoordinatorForm.php:62`, `VoterForm.php:342` (Filament) and from `create-leader.blade.php:71`, `create-coordinator.blade.php:77`, `register-leader.blade.php:77`, `register-voter.blade.php:96`, `leader-add-voter.blade.php:107` (Volt). Same problem, same solution.

```php
<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\MetadataKey;
use App\Models\User;
use App\Models\UserMetadataValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MetadataAssignmentService
{
    /** @return Collection<int, MetadataKey> */
    public function activeKeys(): Collection
    {
        return MetadataKey::query()
            ->where('is_active', true)
            ->orderBy('label')
            ->get();
    }

    /**
     * D-03: DIRECT subordinates only. Deliberately NOT
     * User::teamCoordinatorUserIds() (that is Phase 13's transitive-team
     * resolver for AUTHZ-01 dashboard scoping).
     */
    public function directSubordinatesQuery(User $superior): Builder
    {
        return match (true) {
            $superior->hasAnyRole([UserRole::SUPER_ADMIN->value, UserRole::ADMIN_CAMPAIGN->value])
                => User::query(),
            $superior->hasRole(UserRole::AREA_COORDINATOR->value)
                => User::query()->where('area_coordinator_user_id', $superior->id),
            $superior->hasRole(UserRole::COORDINATOR->value)
                => User::query()->where('coordinator_user_id', $superior->id),
            default
                => User::query()->whereRaw('1 = 0'),
        };
    }

    /**
     * META-06: the ONLY write path. Pure INSERT — never updateOrCreate,
     * never UPDATE. Two concurrent writes to the same key produce two rows;
     * the later row wins by (assigned_at, id) ordering, and neither is lost.
     */
    public function assign(User $subject, MetadataKey $key, string $value, User $assignedBy): UserMetadataValue
    {
        return UserMetadataValue::create([
            'user_id' => $subject->id,
            'metadata_key_id' => $key->id,
            'value' => $value,
            'assigned_by' => $assignedBy->id,
            'assigned_at' => now(),
        ]);
    }

    /**
     * D-09: current value per key = most recent row.
     * `id` DESC is the load-bearing tie-break — assigned_at is a MySQL
     * TIMESTAMP with 0 fractional-second precision, so two assignments in
     * the same second are indistinguishable by timestamp alone.
     *
     * @return Collection<int, UserMetadataValue> keyed by metadata_key_id
     */
    public function currentValues(User $subject): Collection
    {
        return UserMetadataValue::query()
            ->with(['metadataKey', 'assignedByUser'])
            ->where('user_id', $subject->id)
            ->orderByDesc('assigned_at')
            ->orderByDesc('id')
            ->get()
            ->unique('metadata_key_id')
            ->keyBy('metadata_key_id');
    }
}
```

Note `User::query()` for the super_admin branch is already campaign-safe: `User` uses `HasCampaignMembershipScope` (verified `User.php:24`), which adds `CampaignMembershipScope` globally — so "any user" means "any user in the active campaign" (D-02) without extra code. This is the same mechanism Phase 14 Plan 02 confirmed for the `area_coordinator_user_id` Select.

### Pattern 2: Filament individual assignment — `Section` + `headerActions`

**What:** A `Filament\Schemas\Components\Section` appended to `CoordinatorForm` / `AreaCoordinatorForm`, whose body renders current values read-only and whose header action opens a modal with the key + type-conditional value fields.

**When to use:** The admin panel only (`EditCoordinator`, `EditAreaCoordinator`, and any other Filament edit form covered by D-05).

**Why not plain form fields:** `EditRecord::save()` wraps `handleRecordUpdate` + `afterSave` in a transaction (verified `EditRecord.php:151-166`). An Action has its own `action()` closure outside that transaction, so each key write stays an independent INSERT — which is exactly what META-06 wants.

**Critical:** `CoordinatorForm` is shared by `CreateCoordinator` and `EditCoordinator`. The section must be `->visibleOn('edit')` (verified `CanBeHidden.php:124`) — a user that doesn't exist yet has no id to assign metadata to.

```php
// Source: verified APIs — Section::headerActions() (HasHeaderActions.php:19),
// Section::visibleOn() (CanBeHidden.php:124), Action::schema() (HasSchema.php:26),
// Schemas\Components\View::make() (Components/View.php)
use App\Models\MetadataKey;
use App\Models\User;
use App\Services\MetadataAssignmentService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;

Section::make('Metadata')
    ->description('Valores asignados a este usuario desde el catálogo.')
    ->visibleOn('edit')
    ->schema([
        View::make('filament.components.metadata-current-values')
            ->viewData(fn (User $record): array => [
                'currentValues' => app(MetadataAssignmentService::class)->currentValues($record),
            ]),
    ])
    ->headerActions([
        Action::make('assignMetadata')
            ->label('Asignar metadata')
            ->icon('heroicon-o-tag')
            ->modalSubmitActionLabel('Asignar')
            ->schema([
                Select::make('metadata_key_id')
                    ->label('Llave')
                    ->options(fn (): array => app(MetadataAssignmentService::class)
                        ->activeKeys()
                        ->pluck('label', 'id')
                        ->all())
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (callable $set) => $set('value', null)),

                TextInput::make('value')
                    ->label('Valor')
                    ->required()
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => static::keyType($get('metadata_key_id')) === 'text'),

                TextInput::make('value')
                    ->label('Valor')
                    ->required()
                    ->numeric()
                    ->step(0.01)                       // D-08: 2 decimales
                    ->visible(fn (Get $get): bool => static::keyType($get('metadata_key_id')) === 'numeric'),

                DatePicker::make('value')
                    ->label('Valor')
                    ->required()
                    ->displayFormat('d/m/Y')
                    ->native(false)
                    ->visible(fn (Get $get): bool => static::keyType($get('metadata_key_id')) === 'date'),

                Select::make('value')
                    ->label('Valor')
                    ->required()
                    ->options(fn (Get $get): array => static::keyOptions($get('metadata_key_id')))
                    ->visible(fn (Get $get): bool => static::keyType($get('metadata_key_id')) === 'select'),
            ])
            ->action(function (array $data, User $record): void {
                app(MetadataAssignmentService::class)->assign(
                    subject: $record,
                    key: MetadataKey::findOrFail($data['metadata_key_id']),
                    value: (string) $data['value'],
                    assignedBy: auth()->user(),
                );
            }),
    ]);
```

The four same-named `value` fields with mutually exclusive `->visible()` is the pattern already used in this repo at `QuestionsRelationManager.php:106-145` (scale/options/text/yes-no configuration blocks keyed off `$get('question_type')`). Reuse it rather than inventing a dynamic-component approach.

### Pattern 3: Filament bulk assignment — `BulkAction` in `toolbarActions()`

**What:** A custom `BulkAction` with a modal `schema()` and an `action(fn (Collection $records, array $data))` that loops.

**Where:** `CoordinatorsTable`, `LeadersTable`, `AreaCoordinatorsTable`, `UsersTable` — per D-05/D-02.

```php
// Source: pattern from MessageTemplatesTable.php:172-186 (modernised —
// that file uses the deprecated ->bulkActions(); use ->toolbarActions()).
// BulkAction::setUp() calls accessSelectedRecords() (BulkAction.php:12),
// so the $records Collection is injectable.
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Support\Collection;

->toolbarActions([
    BulkActionGroup::make([
        DeleteBulkAction::make(),

        BulkAction::make('assignMetadata')
            ->label('Asignar metadata')
            ->icon('heroicon-o-tag')
            ->schema([ /* same key Select + 4 conditional value fields as Pattern 2 */ ])
            ->action(function (Collection $records, array $data): void {
                $key = MetadataKey::findOrFail($data['metadata_key_id']);
                $service = app(MetadataAssignmentService::class);
                $assignedBy = auth()->user();

                foreach ($records as $record) {
                    $service->assign($record, $key, (string) $data['value'], $assignedBy);
                }
            })
            ->deselectRecordsAfterCompletion(),
    ]),
])
```

`deselectRecordsAfterCompletion()` is verified at `vendor/filament/actions/src/Concerns/CanDeselectRecordsAfterCompletion.php:11`.

### Pattern 4: Volt/Flux individual assignment — nested Livewire component

**What:** `App\Livewire\MetadataAssignmentPanel` (class-based, `app/Livewire/`), rendered with `<livewire:metadata-assignment-panel :user="$coordinator" />` inside `articulador/edit-coordinator.blade.php` and `coordinator/edit-leader.blade.php`.

**Why a class component and not inline Volt:** Nesting precedent already exists (`<livewire:settings.delete-user-form />` in `settings/profile.blade.php:114`, `<livewire:settings.two-factor.recovery-codes :$requiresConfirmation/>` in `two-factor.blade.php:198`). A single class component avoids duplicating the panel across the two Volt pages.

**Isolation is a feature:** the nested component has its own `save`, so assigning metadata does not require submitting the parent user-edit form, and each write is its own INSERT.

**Feedback:** use inline state (`public ?string $successMessage`) rendered with Flux, **not** `Filament\Notifications\Notification`. The Volt layouts (`components/layouts/app.blade.php` → `app/sidebar.blade.php`) contain no `@livewire('notifications')` — verified by grep, zero matches for `notifications` in `resources/views/components/layouts/`. A Filament notification dispatched from a Volt page silently vanishes.

### Pattern 5: Volt/Flux bulk selection — checkbox array + modal

**What:** `public array $selectedUserIds = []` bound with `flux:checkbox wire:model.live="selectedUserIds" value="{{ $u->id }}"`, a `flux:checkbox.all` header control, a bar that appears when `count($selectedUserIds) > 0`, and a `flux:modal` for key+value.

**Why hand-rolled here and not elsewhere:** there is **no existing row-selection/bulk-action UI in any Volt page in this repo** — verified by grepping `resources/views/livewire/` for selection state (zero matches). This is genuinely net-new UI. The lightest consistent build reuses two existing in-repo idioms:
- checkbox-array binding: `my-voters.blade.php:236-248` (`wire:model.live="exportColumns" value="cedula"` against `public array $exportColumns`)
- modal: `leaders.blade.php:126` (`<flux:modal wire:model="showLeaderInvitationModal">`)

```blade
{{-- header --}}
<flux:checkbox.all />

{{-- per row, inside a flux:checkbox.group wrapper --}}
<flux:checkbox wire:model.live="selectedUserIds" value="{{ $coordinator->id }}" />

{{-- bulk bar --}}
@if (count($selectedUserIds) > 0)
    <div class="flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm dark:bg-zinc-900">
        <flux:text>{{ count($selectedUserIds) }} seleccionados</flux:text>
        <flux:button variant="primary" wire:click="$set('showBulkMetadataModal', true)" icon="tag">
            Asignar metadata
        </flux:button>
    </div>
@endif
```

**Server-side re-scoping is mandatory:** `$selectedUserIds` arrives from the browser and is attacker-controlled. Do NOT trust it. Re-filter through the service:

```php
$targets = app(MetadataAssignmentService::class)
    ->directSubordinatesQuery(auth()->user())
    ->whereIn('id', $this->selectedUserIds)
    ->get();
```

`16-CONTEXT.md`'s discretion note says "bulk action target-user validation is implicit — each panel's own table is already scoped." That reasoning holds for Filament (Filament's bulk actions resolve records through the table's own scoped query), but it does **not** hold for a hand-rolled Livewire array — the array is just IDs posted from the client. This is a real divergence the planner must encode.

### Anti-Patterns to Avoid

- **Flux components inside anything a Filament panel renders.** No Flux CSS, no `@fluxScripts`. Verified: 0 `flux:` occurrences under `resources/views/filament/`; `SaldosBadge` (the only Livewire component in a Filament panel) uses `x-filament::` exclusively.
- **`->bulkActions([...])`** — deprecated in v4 (`HasBulkActions.php:33`). Use `->toolbarActions([...])`. Note `UsersTable.php:191` and the two `Messages` tables still use the deprecated call; do not copy them.
- **`Action::form([...])`** — deprecated in v4 (`HasSchema.php:124`). Use `->schema([...])`.
- **`UserMetadataValue::updateOrCreate(...)`** — destroys the append-only audit trail (META-05) *and* introduces a genuine read-modify-write race (META-06). Only `create()`.
- **`ORDER BY assigned_at DESC` without `, id DESC`** — non-deterministic for same-second writes. See META-06 analysis.
- **Reusing `User::teamCoordinatorUserIds()`** — explicitly forbidden by D-03; it returns the transitive team, not direct subordinates.
- **Registering `AuditObserver` on `UserMetadataValue`** — would write a duplicate `audit_logs` row for data that is already, by design, its own audit trail.
- **A metadata section on the create form** — no record id exists yet. `->visibleOn('edit')`.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Audit trail of assignments | An `metadata_assignment_log` table, or wiring `AuditObserver` to `UserMetadataValue` | The existing append-only `user_metadata_values` rows (`assigned_by`, `assigned_at`, `assignedByUser()` relation) | Phase 12 D-02 designed the value table AS the audit log. Verified: no unique constraint, relation already defined |
| Campaign isolation on user queries | Manual `whereHas('campaigns', ...)` in the subordinate resolver | `User`'s global `CampaignMembershipScope` (via `HasCampaignMembershipScope`, `User.php:24`) | Phase 14 Plan 02 already proved by SQL inspection that `User::query()` is auto-scoped to the active campaign |
| Cross-campaign guard on the assignment target | A hand-rolled campaign_id comparison | `Gate::before` in `AuthServiceProvider.php:45-61` — denies any `User` model not in the active campaign | Phase 13 Plan 02 established this; adding a second check is redundant and can drift |
| Articulador→coordinador ownership check | `$coordinator->area_coordinator_user_id !== auth()->id()` | `auth()->user()->can('update', $coordinator)` → `CoordinatorPolicy` | Phase 15 Plan 04's recorded decision — the Volt route already uses exactly this at `edit-coordinator.blade.php:54` |
| "Select all" checkbox behavior | Custom Alpine toggling every row | `flux:checkbox.all` inside `flux:checkbox.group` | Verified present in Flux free (`checkbox/all.blade.php`) |
| JSON array editor for `metadata_keys.options` | Textarea + `explode(',')` | `Repeater::make('options')->simple(TextInput::make('option'))` | Working in-repo example at `QuestionsRelationManager.php:110`; matches the `'options' => 'array'` cast |
| Super-admin-only resource gating | Middleware or a new policy | `public static function canAccess(): bool { return CampaignContext::isSuperAdmin(); }` | Exact precedent: `AuditLogResource.php:47` |
| Type-conditional form fields | A custom dynamic component factory | Multiple same-named fields with mutually exclusive `->visible(fn (Get $get) => ...)` | Exact precedent: `QuestionsRelationManager.php:106-145` |

**Key insight:** almost everything this phase needs already exists somewhere in this repo. The research value here is in pointing at the right existing line, not in importing new ideas.

## META-06: Atomicity Analysis

This deserves its own section because the requirement as literally worded is *already true by construction*, while a real, adjacent gap exists that the requirement is plausibly reaching for. The planner needs to know which is which so it doesn't write a test that passes for the wrong reason.

### What the schema actually guarantees

`user_metadata_values` (verified migration) has: no unique constraint on `(user_id, metadata_key_id)`, a plain auto-increment `id`, and `assigned_at` as `$table->timestamp(...)`. Every assignment is an independent `INSERT`.

**Case A — two concurrent assignments to DIFFERENT keys on the same user.** This is what ROADMAP success criterion 5 literally says. Two INSERTs producing two rows with different `metadata_key_id`. They share no row, no lock, and no read-modify-write step. **They cannot clobber each other — this is structurally impossible, not merely unlikely.** A test here proves a property of the schema, not of the code. It is still worth writing (it locks the append-only design against a future refactor to `updateOrCreate`), but it should be framed honestly as a regression guard, not a race test.

**Case B — two concurrent assignments to the SAME key on the same user.** Also two INSERTs, also both rows survive. There is no *lost update* in the destructive sense. But there IS a real defect: **which one is "current" can be non-deterministic.**

`$table->timestamp('assigned_at')` produces a MySQL `TIMESTAMP` with **precision 0** (whole seconds), and Laravel's default `getDateFormat()` is `'Y-m-d H:i:s'` — also whole seconds, in SQLite too. So two assignments landing within the same wall-clock second store **byte-identical** `assigned_at` values. Any resolver written as:

```php
->where('user_id', $id)->where('metadata_key_id', $keyId)->latest('assigned_at')->first()
```

returns an arbitrary row of the two. On MySQL/InnoDB the physical order is usually PK order and it usually looks correct, which makes this exactly the kind of bug that passes review and surfaces in production during a bulk operation — where dozens of rows are written inside a single second by definition.

**This is the actual substance of META-06.** The fix is one clause:

```php
->orderByDesc('assigned_at')->orderByDesc('id')
```

`id` is a monotonic auto-increment, so it is the true insertion order and gives a deterministic total order regardless of timestamp collisions. Every read path — the Filament section body, the Flux panel, and (importantly) Phase 17's FILT-01/FILT-02 filter and sort queries — must use it.

### The one way to actually break atomicity

Introduce a read-modify-write. If any plan implements the write as "look up the current row, then update it" (`updateOrCreate`, or `firstOrNew` + `save`), Case B becomes a genuine lost update and Case A becomes vulnerable to whole-record overwrite. **The single most important META-06 constraint is: the write path is `UserMetadataValue::create()` and nothing else.** That is enforceable by test.

### Recommended test design (given the constraints of this suite)

`phpunit.xml:27-28` sets `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:`. An in-memory SQLite database is single-connection — **true parallel writes cannot be simulated.** Do not attempt threads, `pcntl_fork`, or `ParallelTesting` for this; any such test would be theater. Report this limitation openly in the plan. Four honest tests fully cover the requirement:

1. **Deterministic tie-break (the real one).** Insert two rows for the same `(user, key)` with an *identical* explicit `assigned_at` and different values. Assert `currentValues()` returns the one with the higher `id`. This test fails today against a naive `latest('assigned_at')` resolver — it is the only test here with genuine RED-state value.
2. **Different keys don't interfere.** Assign key A, then key B, then key A again, to the same user. Assert both current values are correct and that 3 rows exist. Covers criterion 5 as literally worded.
3. **Append-only write path.** Assign the same key twice. Assert `UserMetadataValue::where(...)->count() === 2` and that the first row's `value`/`assigned_by`/`assigned_at` are byte-for-byte unchanged. This is the guard against a future `updateOrCreate` regression.
4. **Bulk writes preserve per-user attribution.** Run the bulk action over N users in one call. Assert N rows, each with the correct `user_id`, and all with the same `metadata_key_id`/`value`/`assigned_by`.

### Should the bulk write be wrapped in a transaction?

Recommendation: **no explicit transaction.** Each `INSERT` is independently atomic, the operation is idempotent-ish (a re-run just appends more rows), and wrapping N inserts means one bad row rolls back N-1 good assignments. Note that on the Filament *individual* path the Action closure runs outside `EditRecord::save()`'s transaction anyway (that transaction only wraps `handleRecordUpdate` + `afterSave`, `EditRecord.php:151-166`), so behavior is consistent across surfaces.

## Common Pitfalls

### Pitfall 1: Flux components rendered inside a Filament panel
**What goes wrong:** The metadata panel appears unstyled and non-interactive (modals never open, dropdowns never expand) inside `/admin`, while working perfectly on `/coordinator` and `/articulador`.
**Why it happens:** `resources/css/filament/theme.css` imports only `vendor/filament/filament/resources/css/theme.css` — no `flux.css`. `resources/css/app.css:2` is where `flux.css` is imported, and that bundle is only loaded by the Volt layouts. Filament layouts never emit `@fluxScripts`, so Flux's Alpine behaviors are absent too.
**How to avoid:** Filament surfaces use `x-filament::*` / native schema components. Volt surfaces use `flux:*`. Never one blade for both.
**Warning signs:** any `flux:` string appearing in a file under `resources/views/filament/`, or in a view rendered via `Filament\Schemas\Components\View`/`Livewire`.

### Pitfall 2: Copying the BulkAction pattern from `CoordinatorsTable`
**What goes wrong:** There is no custom BulkAction there to copy — only `DeleteBulkAction::make()` at `CoordinatorsTable.php:59`. `16-CONTEXT.md` states otherwise in two places (canonical_refs and code_context).
**How to avoid:** Copy from `MessageTemplatesTable.php:172-186`, but modernise: swap the deprecated `->bulkActions()` for `->toolbarActions()` (which `CoordinatorsTable.php:57` already uses) and `->form()` for `->schema()`.

### Pitfall 3: Adding the metadata section to a shared Create/Edit form
**What goes wrong:** `CoordinatorForm::configure()` serves both `CreateCoordinator` and `EditCoordinator`. On create there is no `$record`, so `View::make(...)->viewData(fn (User $record) => ...)` throws, or the assign action writes against a null user.
**How to avoid:** `->visibleOn('edit')` on the Section (verified `CanBeHidden.php:124`).

### Pitfall 4: `Filament\Notifications\Notification` from a Volt page
**What goes wrong:** Feedback silently disappears; the user clicks "Asignar" and sees nothing.
**Why:** no notification renderer in the Volt layout chain (`components/layouts/app.blade.php` → `app/sidebar.blade.php`) — zero grep matches for `notifications`.
**How to avoid:** inline component state or `session()->flash('success', ...)` (the established Volt idiom — `edit-coordinator.blade.php:117`, `leaders.blade.php:153`).

### Pitfall 5: Trusting client-supplied `$selectedUserIds`
**What goes wrong:** A coordinador edits the wire payload and assigns metadata to a user outside their team.
**How to avoid:** always re-filter selected IDs through `directSubordinatesQuery(auth()->user())->whereIn('id', ...)` before writing. This is the same class of leak documented in Quick tasks 260804-i5f and 260804-jbc (cross-coordinator PII).
**Warning signs:** `User::whereIn('id', $this->selectedUserIds)` with no ownership predicate.

### Pitfall 6: `UserFactory` produces null `document_number` / `phone`
**What goes wrong:** intermittent (~10-35%) test failures when a factory-created user is mounted into an edit form with typed `string` properties or required-field revalidation.
**Why:** documented repeatedly in STATE.md (Phase 14 Plan 01, Phase 15 Plan 04) — `UserFactory` nulls those fields a fraction of the time.
**How to avoid:** set `document_number` and `phone` explicitly in any fixture that gets mounted into `edit-leader` / `edit-coordinator` / `EditCoordinator`.

### Pitfall 7: `CampaignContext` static-override test pollution
**What goes wrong:** unrelated tests fail only when run together in one `php artisan test` invocation; pass in isolation.
**Why:** a standing, pre-existing, project-level deferred item (documented across Phases 12-15 in STATE.md).
**How to avoid:** verify new tests in isolation first (`php artisan test --filter=...`); do not attribute suite-wide failures to this phase without an isolated re-run.

### Pitfall 8: Missing Vite manifest in a worktree
**What goes wrong:** dozens of spurious HTTP-level test failures (`assertOk()` on Volt routes).
**How to avoid:** the established worktree bootstrap — `git merge --ff-only main`, copy `.env`, `composer install`, `npm install && npm run build`. Documented for every phase 12-15 in STATE.md; expect `gsd-tools init` to resolve `project_root` to the main checkout (known `findProjectRoot()` bug) and hand-edit STATE.md/REQUIREMENTS.md in the worktree.

## Code Examples

### Current-values display (Filament side, `x-filament::` only)

```blade
{{-- resources/views/filament/components/metadata-current-values.blade.php --}}
{{-- D-09: current value + who assigned it + when. No history. --}}
<div class="space-y-2">
    @forelse ($currentValues as $value)
        <div class="flex items-center justify-between gap-3">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">
                {{ $value->metadataKey->label }}
            </span>
            <div class="flex items-center gap-2">
                <x-filament::badge>{{ $value->value }}</x-filament::badge>
                <span class="text-xs text-gray-500 dark:text-gray-400">
                    {{ $value->assignedByUser?->name ?? 'Sistema' }} ·
                    {{ $value->assigned_at->format('d/m/Y H:i') }}
                </span>
            </div>
        </div>
    @empty
        <p class="text-sm text-gray-500 dark:text-gray-400">Sin metadata asignada.</p>
    @endforelse
</div>
```

### `MetadataKeyForm` — the `options` field (META-01)

```php
// Source: Repeater::simple() pattern verified at QuestionsRelationManager.php:110
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

return $schema->components([
    TextInput::make('key')
        ->label('Llave')
        ->required()
        ->maxLength(255)
        ->unique(ignoreRecord: true)
        ->helperText('Identificador técnico, sin espacios. No se puede cambiar una vez asignado.'),

    TextInput::make('label')
        ->label('Nombre visible')
        ->required()
        ->maxLength(255),

    Select::make('type')
        ->label('Tipo')
        ->options([
            'numeric' => 'Numérico',
            'text' => 'Texto',
            'date' => 'Fecha',
            'select' => 'Selección',
        ])
        ->required()
        ->live(),

    Repeater::make('options')
        ->label('Opciones de selección')
        ->simple(
            TextInput::make('option')
                ->label('Opción')
                ->required()
        )
        ->visible(fn (Get $get): bool => $get('type') === 'select')
        ->minItems(1)
        ->defaultItems(2)
        ->addActionLabel('+ Agregar opción')
        ->reorderable()
        ->columnSpanFull(),

    Toggle::make('is_active')
        ->label('Activa')
        ->default(true)
        ->helperText('Desactivar oculta la llave de los formularios de asignación sin borrar el historial.'),
]);
```

### `MetadataKeyResource` super-admin gate (META-01)

```php
// Source: exact precedent at AuditLogResource.php:47
use App\Services\CampaignContext;

public static function canAccess(): bool
{
    return CampaignContext::isSuperAdmin();
}
```

Existing navigation groups (verified across all resources): `Call Center`, `Configuración`, `Gestión`, `Jornada Electoral`, `Mensajería`, `Sistema`. `Configuración` is where `GremioResource` lives (`navigationGroup = 'Configuración'`, `navigationSort = 4`) — the natural home for `MetadataKeyResource`.

### Volt sidebar placement (D-05)

The coordinador/articulador sidebar branches by role at `resources/views/components/layouts/app/sidebar.blade.php:39-49`. Nothing new is needed there for this phase — metadata assignment lives inside the existing edit pages and list pages, both already linked (`coordinator.leaders`, `articulador.coordinadores`).

## State of the Art

| Old Approach (Filament v3 / this repo's older files) | Current (v4.2.0) | Where verified | Impact |
|---|---|---|---|
| `Table::bulkActions([...])` | `Table::toolbarActions([...])` | `HasBulkActions.php:33` `@deprecated` | New tables must use `toolbarActions`; `UsersTable.php:191` + both `Messages` tables are stale, don't copy |
| `Action::form([...])` | `Action::schema([...])` | `HasSchema.php:124` `@deprecated` | Bulk/individual modal fields |
| `Filament\Tables\Actions\*` | `Filament\Actions\*` | CLAUDE.md + all current tables | `use Filament\Actions\BulkAction;` |
| `Filament\Forms\Components\{Section,Grid,Tabs}` | `Filament\Schemas\Components\{...}` | CLAUDE.md + `CoordinatorForm.php:15` | Section import path |
| `$casts` property | `casts()` method | `MetadataKey.php` already uses it | — |

**Not deprecated / still correct:** `Filament\Forms\Components\{TextInput, Select, DatePicker, Repeater, Toggle}` — these stayed in `Filament\Forms`.

## Open Questions

1. **Is a catalog seeder expected in this phase?**
   - What we know: CLAUDE.md mandates "new models: create factories + seeders." `MetadataKeyFactory` and `UserMetadataValueFactory` exist (Phase 12); no `MetadataKeySeeder` does. `16-CONTEXT.md` and ROADMAP success criteria never mention seeding. The v1.2 milestone name ("Articuladores + Metadata de Usuario") and STATE.md's project memory reference "biáticos" as a motivating example, and `MetadataCatalogSchemaTest` uses `'biaticos'` as its fixture key.
   - What's unclear: whether the client wants real starter keys (biáticos, etc.) shipped, or an empty catalog the superadmin fills through the new UI.
   - Recommendation: **ship no seeder.** META-01's success criterion is that the superadmin *creates* keys through the UI — a seeder would make that criterion untestable-by-observation and hardcodes client-specific business vocabulary into the repo. If the planner disagrees, make it a separate optional task, not a dependency.

2. **Does `EditLeader` (Filament) exist and need the section too?**
   - What we know: D-04 names `EditCoordinator`, `EditLeader`, `EditAreaCoordinator`. `app/Filament/Resources/Leaders/` exists with a `LeadersTable` and `LeaderForm`. Verified: `LeadersTable.php:51-59` has `recordActions([EditAction, DeleteAction])` and `toolbarActions([BulkActionGroup([DeleteBulkAction])])`.
   - What's unclear: nothing blocking — the planner should simply add the section to `LeaderForm` and the BulkAction to `LeadersTable`, same as the other two. Flagged only so it isn't dropped: D-04 lists three Filament edit forms, and the phase brief's "critical questions" only enumerate two.
   - Recommendation: cover all three Filament edit forms + `UsersTable` (D-02: superadmin assigns to any user).

3. **Should `metadata_keys.key` be immutable after first assignment?**
   - What we know: `key` is `unique`. `metadata_key_id` FK is `restrictOnDelete` (blocks hard-delete of an assigned key). Nothing blocks *renaming* the `key` string.
   - What's unclear: renaming `key` doesn't break referential integrity (assignments reference `id`, not the string), so the risk is cosmetic/reporting drift, not data loss. Phase 17's FILT-01 will surface keys by label, and labels are meant to be editable per META-01 ("crear/editar").
   - Recommendation: allow editing `label`, allow editing `key`, no lock. If the planner wants belt-and-braces, `->disabled(fn (?MetadataKey $record) => $record?->values()->exists())` on the `key` field is cheap — but it is not a stated requirement and adds a query per form render.

4. **Does `User` need a `metadataValues()` relation now, or in Phase 17?**
   - What we know: `UserMetadataValue::user()` exists (inverse side), but `User` has no `metadataValues()` HasMany. The service can work without it (`UserMetadataValue::where('user_id', ...)`) but eager loading across a table of users — which Phase 17's FILT-01/FILT-03 will need — requires the relation.
   - Recommendation: add `User::metadataValues(): HasMany` in this phase. It's two lines, it makes `currentValues()` eager-loadable, and Phase 17 depends on it. Keep it plain — no scoped "latest per key" relation (that needs a window function or a subquery join and belongs in Phase 17's sort/filter work).

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP | Everything | ✓ | 8.4.23 | — |
| Node | `npm run build` (Vite manifest for HTTP tests) | ✓ | v22.22.3 | — |
| `vendor/` (Composer deps installed) | Everything | ✓ | — | `composer install` |
| `vendor/bin/pint` | Mandatory pre-finalize step | ✓ | 1.24 | — |
| `vendor/bin/pest` / `php artisan test` | All tests | ✓ | Pest 4 | — |
| SQLite (in-memory) | Test DB (`phpunit.xml:27-28`) | ✓ | bundled | — |
| Phase 12 migrations applied | Models/factories | ✓ | verified — `MetadataCatalogSchemaTest` 6/6 pass, 0.77s | — |
| Filament v4 | Resource + bulk actions | ✓ | v4.2.0 | — |
| Flux (free) | Volt UI | ✓ | v2, `checkbox`/`checkbox.all`/`checkbox.group`/`modal` all verified present | — |

**Missing dependencies with no fallback:** none.
**Missing dependencies with fallback:** none.

**Note:** true multi-connection concurrency testing is **not available** (SQLite `:memory:` is single-connection). This constrains META-06 test design — see that section.

## Sources

### Primary (HIGH confidence — this repo's own source, read directly)
- `vendor/filament/tables/src/Table/Concerns/HasBulkActions.php:33` — `bulkActions()` `@deprecated Use toolbarActions() instead`
- `vendor/filament/actions/src/Concerns/HasSchema.php:26,124` — `schema()` current, `form()` `@deprecated`
- `vendor/filament/actions/src/BulkAction.php:12` — `setUp()` calls `accessSelectedRecords()` (so `$records` is injectable)
- `vendor/filament/actions/src/Concerns/CanDeselectRecordsAfterCompletion.php:11`
- `vendor/filament/schemas/src/Components/Concerns/HasHeaderActions.php:19` — `Section::headerActions()`
- `vendor/filament/schemas/src/Components/Concerns/CanBeHidden.php:40,124` — `hiddenOn()` / `visibleOn()`
- `vendor/filament/schemas/src/Components/{View,Livewire}.php` — both exist, `make()` signatures confirmed
- `vendor/filament/filament/src/Resources/Pages/EditRecord.php:118-190` — `mutateFormDataBeforeFill`, `save()` transaction boundary, `afterSave` hook
- `vendor/livewire/flux/stubs/resources/views/flux/checkbox/{index,all,group/index}.blade.php` — free-tier availability
- `composer.lock` — `filament/filament v4.2.0`
- `app/Filament/Resources/Messages/Tables/MessageTemplatesTable.php:172-186` — the only working custom `BulkAction` in the repo
- `app/Filament/Resources/Coordinators/Tables/CoordinatorsTable.php:57-61` — `toolbarActions()`, and confirmation that **no** custom BulkAction exists there
- `app/Filament/Resources/Surveys/RelationManagers/QuestionsRelationManager.php:106-145` — `Repeater::simple()` + type-conditional `->visible(Get)`
- `app/Filament/Resources/AuditLogs/AuditLogResource.php:47` — `canAccess()` super-admin gate
- `app/Filament/Resources/Gremios/*` — 6-file resource structure to mirror
- `app/Services/IdentityLookupService.php` + its 9 call sites — the shared-logic-across-Filament-and-Volt precedent
- `app/Livewire/SaldosBadge.php` + `resources/views/livewire/saldos-badge.blade.php` — proof that Filament-embedded Livewire uses `x-filament::`, not Flux
- `resources/css/app.css:2` vs `resources/css/filament/theme.css:1-5` — the Flux/Filament asset boundary
- `app/Providers/AuthServiceProvider.php:45-70` — `Gate::before` campaign guard
- `app/Models/User.php:24,143,153,169` — `HasCampaignMembershipScope`, `leaders()`, `coordinators()`, `teamCoordinatorUserIds()`
- `database/migrations/2026_08_10_1201*` / `1202*` — exact metadata schema
- `phpunit.xml:27-28` — SQLite `:memory:` test DB
- Live run: `php artisan test --filter=MetadataCatalogSchemaTest` → 6 passed, 14 assertions, 0.77s

### Secondary (MEDIUM confidence)
- `.planning/STATE.md` — recurring worktree-staleness workaround, `UserFactory` null-field flakiness, `CampaignContext` test pollution, `CampaignUser`/`HasCampaignContext` pivot-overwrite bug (all cross-referenced against at least two independent phase records)

### Tertiary (LOW confidence)
- None. No external sources were needed; every claim is backed by local source. No WebSearch/Context7 lookups were performed because the installed vendor tree is strictly more authoritative than published docs for version-specific API questions.

## Metadata

**Confidence breakdown:**
- Standard stack: **HIGH** — nothing new to install; every version read from `composer.lock` / `vendor/`
- Architecture (shared service + 2 UI layers): **HIGH** — the Flux/Filament boundary was verified empirically (0 Flux usages in Filament views, theme.css inspected), and the `IdentityLookupService` precedent is 9 call sites deep
- Filament v4 API specifics: **HIGH** — every method read from installed vendor source, including deprecation docblocks
- Pitfalls: **HIGH** for the asset boundary, deprecations, and `visibleOn`; **MEDIUM** for the flaky-test pitfalls (sourced from STATE.md rather than reproduced this session)
- META-06 analysis: **HIGH** on the mechanism (schema + Laravel date-format resolution are both verifiable); **MEDIUM** on intent — the requirement's Spanish wording and the ROADMAP criterion describe Case A, which is structurally impossible to violate. The Case B tie-break gap is a genuine finding, but reading it as "what META-06 meant" is my inference, not the client's stated words. The planner should implement both and let the verifier judge.

**Known corrections to `16-CONTEXT.md`** (the planner should not take these on faith from CONTEXT.md):
- `CoordinatorsTable.php` has **no** custom `BulkAction` to mirror — use `MessageTemplatesTable.php:172-186`, modernised to `toolbarActions()`/`schema()`
- "Bulk action target-user validation is implicit" is true for Filament tables but **false** for the hand-rolled Volt selection array — server-side re-scoping is required there

**Research date:** 2026-08-10
**Valid until:** 2026-09-09 (30 days — all findings are pinned to installed versions in this repo; only a `composer update` invalidates them)

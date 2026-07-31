---
phase: quick
plan: 260731-nuk
type: execute
wave: 1
depends_on: []
files_modified:
  - app/Filament/Resources/AuditLogs/AuditLogResource.php
  - app/Filament/Resources/AuditLogs/Tables/AuditLogsTable.php
  - app/Filament/Resources/AuditLogs/Pages/ListAuditLogs.php
  - app/Filament/Resources/AuditLogs/Pages/ViewAuditLog.php
  - tests/Feature/Filament/AuditLogResourceTest.php
autonomous: true
requirements: []
must_haves:
  truths:
    - "A super admin can see a paginated, sortable list of audit log entries (who, action, model, campaign, when)"
    - "A super admin can open a single audit log entry and read its old_values/new_values as legible formatted data"
    - "A super admin can filter the audit log list by user, by action type, and by created_at date range"
    - "Any non-super-admin role is blocked from viewing the audit log resource (403), including its nav item"
    - "No create, edit, or delete UI exists anywhere on the audit log resource"
  artifacts:
    - path: "app/Filament/Resources/AuditLogs/AuditLogResource.php"
      provides: "Resource definition gated to super_admin only, registers only index/view pages"
      contains: "public static function canAccess(): bool"
    - path: "app/Filament/Resources/AuditLogs/Tables/AuditLogsTable.php"
      provides: "Table columns (user/action/model/campaign_id/created_at) + user/action/date-range filters, view-only record action"
      contains: "class AuditLogsTable"
    - path: "app/Filament/Resources/AuditLogs/Pages/ListAuditLogs.php"
      provides: "Index page, no create action registered"
      contains: "class ListAuditLogs extends ListRecords"
    - path: "app/Filament/Resources/AuditLogs/Pages/ViewAuditLog.php"
      provides: "Read-only detail infolist formatting old_values/new_values as legible JSON"
      contains: "class ViewAuditLog extends ViewRecord"
    - path: "tests/Feature/Filament/AuditLogResourceTest.php"
      provides: "Pest coverage: 403 for non-super-admin, list/filters work for super admin, no create/edit/delete routes"
      contains: "test('super admin can access the audit log index'"
  key_links:
    - from: "app/Filament/Resources/AuditLogs/AuditLogResource.php"
      to: "app/Services/CampaignContext.php"
      via: "canAccess() calls CampaignContext::isSuperAdmin()"
      pattern: "CampaignContext::isSuperAdmin"
    - from: "app/Filament/Resources/AuditLogs/Tables/AuditLogsTable.php"
      to: "app/Models/AuditLog.php"
      via: "SelectFilter::make('user_id')->relationship('user', 'name')"
      pattern: "relationship\\('user'"
    - from: "app/Filament/Resources/AuditLogs/Pages/ListAuditLogs.php"
      to: "app/Filament/Resources/AuditLogs/AuditLogResource.php"
      via: "protected static string $resource = AuditLogResource::class"
      pattern: "AuditLogResource::class"
    - from: "app/Filament/Resources/AuditLogs/Pages/ViewAuditLog.php"
      to: "app/Models/AuditLog.php"
      via: "infolist TextEntry::make('old_values')/('new_values') read the array casts"
      pattern: "old_values"
---

<objective>
Create a read-only Filament Resource (`AuditLogResource`) to browse the `audit_logs` table written by
task `260731-n0n` (`app/Models/AuditLog.php`), gated to Super Admin only, with no create/edit/delete UI.

Purpose: `260731-n0n` built the write path (observer + auth subscriber) but explicitly deferred any UI
to review the resulting audit trail. This closes that gap so a super admin can actually inspect who did
what, on which model, in which campaign, and when — including the before/after JSON for updates.

Output: `AuditLogResource` (index + view pages only) registered under a new "Sistema" navigation group,
visible only to `super_admin`, with user/action/date-range filters and a legible old/new-values detail
view. Full Pest coverage per CLAUDE.md's test-enforcement rule.
</objective>

<execution_context>
@$HOME/.claude/get-shit-done/workflows/execute-plan.md
@$HOME/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@app/Models/AuditLog.php
@database/migrations/2026_07_31_120000_create_audit_logs_table.php
@app/Services/CampaignContext.php
@app/Filament/Resources/Voters/VoterResource.php
@app/Filament/Resources/Voters/Tables/VotersTable.php
@app/Filament/Resources/Voters/Pages/ViewVoter.php
@app/Filament/Resources/Messages/Tables/MessagesTable.php
</context>

<interfaces>
<!-- AuditLog model this Resource wraps — fillable/casts/relations exactly as shipped by 260731-n0n.
     Do not modify this file. -->
From app/Models/AuditLog.php:
```php
protected $fillable = [
    'auditable_type', 'auditable_id', 'action', 'user_id', 'campaign_id',
    'old_values', 'new_values', 'ip_address', 'user_agent',
];

protected function casts(): array
{
    return ['old_values' => 'array', 'new_values' => 'array'];
}

public function auditable(): MorphTo;  // polymorphic — auditable_type/auditable_id
public function user(): BelongsTo;     // -> users.id, nullable (system-originated rows have no actor)
public function campaign(): BelongsTo; // -> campaigns.id, nullable
```

<!-- The known, closed set of `action` values written by AuditObserver/AuditAuthActivitySubscriber
     (260731-n0n). `action` is a plain string column, not a backed enum — hardcode this list in the
     SelectFilter options and the badge color/label maps, do not invent new values. -->
Known `action` values: `created`, `updated`, `deleted`, `login`, `logout`, `login_failed`.

<!-- Super-admin-only gating precedent to replicate (D-01 in 260731-nuk-CONTEXT.md — this Resource
     uses the same isSuperAdmin() gate, not a new role check). -->
From app/Services/CampaignContext.php:
```php
public static function isSuperAdmin(?User $user = null): bool
```

<!-- Precedent for the static canAccess() gate on a Filament page/resource (blocks both the nav item
     and direct URL access — Filament\Resources\Resource\Concerns\HasAuthorization provides this hook). -->
From app/Filament/Pages/DiaD.php:
```php
public static function canAccess(): bool
{
    return Auth::user()?->hasRole([...]) ?? false;
}
```

<!-- Date-range table filter precedent to replicate exactly (Filter + two DatePicker fields + whereDate
     query, from Messages' MessagesTable.php). -->
From app/Filament/Resources/Messages/Tables/MessagesTable.php:
```php
Filter::make('created_at')
    ->form([
        DatePicker::make('created_from')->label('Desde'),
        DatePicker::make('created_until')->label('Hasta'),
    ])
    ->query(function ($query, array $data) {
        return $query
            ->when($data['created_from'], fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($data['created_until'], fn ($query, $date) => $query->whereDate('created_at', '<=', $date));
    }),
```

<!-- Resource auto-discovery: only AdminPanelProvider calls ->discoverResources(app/Filament/Resources).
     Placing this Resource under app/Filament/Resources/AuditLogs/ registers it in the 'admin' panel
     ONLY — no shouldRegisterNavigation() panel check needed (VoterResource needs one because it is
     ALSO manually registered in ReportsPanelProvider; AuditLogResource is not). -->
From app/Providers/Filament/AdminPanelProvider.php:
```php
->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
```
</interfaces>

<tasks>

<task type="auto">
  <name>Task 1: AuditLogResource (index + view, super-admin gated)</name>
  <files>
    app/Filament/Resources/AuditLogs/AuditLogResource.php,
    app/Filament/Resources/AuditLogs/Tables/AuditLogsTable.php,
    app/Filament/Resources/AuditLogs/Pages/ListAuditLogs.php,
    app/Filament/Resources/AuditLogs/Pages/ViewAuditLog.php
  </files>
  <action>
Create `app/Filament/Resources/AuditLogs/AuditLogResource.php`:

```php
<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Resources\AuditLogs\Pages\ViewAuditLog;
use App\Filament\Resources\AuditLogs\Tables\AuditLogsTable;
use App\Models\AuditLog;
use App\Services\CampaignContext;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Auditoría';

    protected static UnitEnum|string|null $navigationGroup = 'Sistema';

    protected static ?string $modelLabel = 'Registro de Auditoría';

    protected static ?string $pluralModelLabel = 'Auditoría';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'action';

    public static function table(Table $table): Table
    {
        return AuditLogsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
            'view' => ViewAuditLog::route('/{record}'),
        ];
    }

    public static function canAccess(): bool
    {
        // Solo Super Admin ve auditoría — mismo gate que el badge de Saldos (D-01, 260731-nuk-CONTEXT.md).
        return CampaignContext::isSuperAdmin();
    }
}
```

No `form()` override — this Resource has no create/edit page, so the base `Resource::form()` no-op is
sufficient. `getPages()` intentionally lists only `index`/`view` — there is no `create`/`edit` key, so
`AuditLogResource::getUrl('create')` and `::getUrl('edit', ...)` simply do not exist as routes.

Create `app/Filament/Resources/AuditLogs/Tables/AuditLogsTable.php`:

```php
<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Usuario')
                    ->placeholder('Sistema')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('action')
                    ->label('Acción')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'created', 'login' => 'success',
                        'updated' => 'info',
                        'deleted', 'login_failed' => 'danger',
                        'logout' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'created' => 'Creado',
                        'updated' => 'Actualizado',
                        'deleted' => 'Eliminado',
                        'login' => 'Inicio de Sesión',
                        'logout' => 'Cierre de Sesión',
                        'login_failed' => 'Inicio Fallido',
                        default => $state,
                    })
                    ->sortable(),

                TextColumn::make('auditable_type')
                    ->label('Modelo')
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '—')
                    ->toggleable(),

                TextColumn::make('auditable_id')
                    ->label('ID Registro')
                    ->toggleable(),

                TextColumn::make('campaign_id')
                    ->label('Campaña ID')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label('Usuario')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('action')
                    ->label('Acción')
                    ->options([
                        'created' => 'Creado',
                        'updated' => 'Actualizado',
                        'deleted' => 'Eliminado',
                        'login' => 'Inicio de Sesión',
                        'logout' => 'Cierre de Sesión',
                        'login_failed' => 'Inicio Fallido',
                    ])
                    ->multiple(),

                Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from')
                            ->label('Desde'),
                        DatePicker::make('created_until')
                            ->label('Hasta'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['created_from'], fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'], fn ($query, $date) => $query->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->persistFiltersInSession();
    }
}
```

No `->bulkActions()` call at all (not even an empty array) — omitting it means no bulk-selection
checkboxes render, and there is no `DeleteBulkAction` to hide. `recordActions()` contains only
`ViewAction::make()` — no `EditAction`, no `DeleteAction`.

Create `app/Filament/Resources/AuditLogs/Pages/ListAuditLogs.php`:

```php
<?php

namespace App\Filament\Resources\AuditLogs\Pages;

use App\Filament\Resources\AuditLogs\AuditLogResource;
use Filament\Resources\Pages\ListRecords;

class ListAuditLogs extends ListRecords
{
    protected static string $resource = AuditLogResource::class;
}
```

No `getHeaderActions()` override — `CreateAction` is opt-in in this codebase (added manually per
resource, see `ListVoters::getHeaderActions()`), so leaving it unoverridden means zero header actions,
in particular no create button.

Create `app/Filament/Resources/AuditLogs/Pages/ViewAuditLog.php` (old_values/new_values formatted as
indented JSON, mirroring ViewVoter's inline-infolist pattern):

```php
<?php

namespace App\Filament\Resources\AuditLogs\Pages;

use App\Filament\Resources\AuditLogs\AuditLogResource;
use Filament\Infolists\Components;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema as SchemaType;
use Filament\Support\Enums\FontFamily;

class ViewAuditLog extends ViewRecord
{
    protected static string $resource = AuditLogResource::class;

    public function infolist(SchemaType $schema): SchemaType
    {
        return $schema->schema([
            Components\TextEntry::make('created_at')
                ->label('Fecha')
                ->dateTime('d/m/Y H:i:s'),

            Components\TextEntry::make('user.name')
                ->label('Usuario')
                ->placeholder('Sistema'),

            Components\TextEntry::make('action')
                ->label('Acción')
                ->badge(),

            Components\TextEntry::make('auditable_type')
                ->label('Modelo')
                ->placeholder('—')
                ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '—'),

            Components\TextEntry::make('auditable_id')
                ->label('ID Registro')
                ->placeholder('—'),

            Components\TextEntry::make('campaign_id')
                ->label('Campaña ID')
                ->placeholder('—'),

            Components\TextEntry::make('ip_address')
                ->label('IP')
                ->placeholder('—'),

            Components\TextEntry::make('user_agent')
                ->label('User Agent')
                ->placeholder('—')
                ->columnSpanFull(),

            Components\TextEntry::make('old_values')
                ->label('Valores Anteriores')
                ->placeholder('—')
                ->formatStateUsing(fn (?array $state): string => $state
                    ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                    : '—')
                ->fontFamily(FontFamily::Mono)
                ->columnSpanFull(),

            Components\TextEntry::make('new_values')
                ->label('Valores Nuevos')
                ->placeholder('—')
                ->formatStateUsing(fn (?array $state): string => $state
                    ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                    : '—')
                ->fontFamily(FontFamily::Mono)
                ->columnSpanFull(),
        ]);
    }
}
```

No `getHeaderActions()` override — no `EditAction`, so the view page has no edit entry point either.

Run `vendor/bin/pint --dirty` on all four new files.
  </action>
  <verify>
    <automated>php artisan route:list --path=admin/audit-logs 2>&1 | grep -c 'audit-logs'</automated>
  </verify>
  <done>Route list shows exactly 2 audit-logs routes (index GET, view GET) — no create/edit/delete routes registered for the resource.</done>
</task>

<task type="auto" tdd="true">
  <name>Task 2: Pest coverage for gating, listing, filters, and route surface</name>
  <files>tests/Feature/Filament/AuditLogResourceTest.php</files>
  <behavior>
    - A `super_admin` gets `assertOk()` hitting `AuditLogResource::getUrl('index')`.
    - Each of `admin_campaign`, `coordinator`, `reviewer` (roles that either pass panel-level access or
      not — either way must end up blocked) gets `assertForbidden()` on the same URL.
    - A `super_admin` can see 3 factory-created `AuditLog` records via `Livewire::test(ListAuditLogs::class)`.
    - Filtering by `user_id` shows only that user's row and hides another user's row.
    - Filtering by `action` (multi-select) shows only matching-action rows and hides others.
    - Filtering by the `created_at` date-range filter (`created_from`/`created_until`) shows an
      in-range row and hides an out-of-range row.
    - `AuditLogResource::getPages()` returns exactly `['index', 'view']` as keys — no `create`/`edit`.
    - The list table has no `edit`/`delete` record actions and no `delete` bulk action registered.
  </behavior>
  <action>
Write `tests/Feature/Filament/AuditLogResourceTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    collect(UserRole::values())->each(
        fn ($role) => Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])
    );
});

test('super admin can access the audit log index', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(UserRole::SUPER_ADMIN->value);

    actingAs($superAdmin);
    Session::put('campaign_context.mode', 'all');

    $this->get(AuditLogResource::getUrl('index', panel: 'admin'))->assertOk();
});

test('non-super-admin roles cannot access the audit log index', function (string $role) {
    $user = User::factory()->create();
    $user->assignRole($role);

    actingAs($user);

    $this->get(AuditLogResource::getUrl('index', panel: 'admin'))->assertForbidden();
})->with([
    UserRole::ADMIN_CAMPAIGN->value,
    UserRole::COORDINATOR->value,
    UserRole::REVIEWER->value,
]);

test('super admin can list audit log records', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(UserRole::SUPER_ADMIN->value);
    actingAs($superAdmin);
    Session::put('campaign_context.mode', 'all');

    $logs = AuditLog::factory()->count(3)->create();

    Livewire::test(ListAuditLogs::class)
        ->assertCanSeeTableRecords($logs);
});

test('audit log table filters by user', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(UserRole::SUPER_ADMIN->value);
    actingAs($superAdmin);
    Session::put('campaign_context.mode', 'all');

    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $logA = AuditLog::factory()->create(['user_id' => $userA->id]);
    $logB = AuditLog::factory()->create(['user_id' => $userB->id]);

    Livewire::test(ListAuditLogs::class)
        ->filterTable('user_id', $userA->id)
        ->assertCanSeeTableRecords([$logA])
        ->assertCanNotSeeTableRecords([$logB]);
});

test('audit log table filters by action', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(UserRole::SUPER_ADMIN->value);
    actingAs($superAdmin);
    Session::put('campaign_context.mode', 'all');

    $created = AuditLog::factory()->create(['action' => 'created']);
    $deleted = AuditLog::factory()->create(['action' => 'deleted']);

    Livewire::test(ListAuditLogs::class)
        ->filterTable('action', ['deleted'])
        ->assertCanSeeTableRecords([$deleted])
        ->assertCanNotSeeTableRecords([$created]);
});

test('audit log table filters by created_at date range', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(UserRole::SUPER_ADMIN->value);
    actingAs($superAdmin);
    Session::put('campaign_context.mode', 'all');

    $inRange = AuditLog::factory()->create(['created_at' => now()->subDays(2)]);
    $outOfRange = AuditLog::factory()->create(['created_at' => now()->subDays(10)]);

    Livewire::test(ListAuditLogs::class)
        ->filterTable('created_at', [
            'created_from' => now()->subDays(3)->toDateString(),
            'created_until' => now()->toDateString(),
        ])
        ->assertCanSeeTableRecords([$inRange])
        ->assertCanNotSeeTableRecords([$outOfRange]);
});

test('audit log resource registers only index and view pages', function () {
    expect(array_keys(AuditLogResource::getPages()))->toBe(['index', 'view']);
});

test('audit log table has no edit/delete record actions or bulk delete action', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(UserRole::SUPER_ADMIN->value);
    actingAs($superAdmin);
    Session::put('campaign_context.mode', 'all');

    AuditLog::factory()->create();

    Livewire::test(ListAuditLogs::class)
        ->assertTableActionDoesNotExist('edit')
        ->assertTableActionDoesNotExist('delete')
        ->assertTableBulkActionDoesNotExist('delete');
});
```

Run `vendor/bin/pint --dirty` on the new test file.
  </action>
  <verify>
    <automated>php artisan test tests/Feature/Filament/AuditLogResourceTest.php --stop-on-failure</automated>
  </verify>
  <done>All tests in AuditLogResourceTest.php pass: 403 for non-super-admin roles, 200 + listing/filtering for super_admin, and confirmed absence of create/edit/delete routes and actions.</done>
</task>

</tasks>

<verification>
- `php artisan test tests/Feature/Filament/AuditLogResourceTest.php` — all pass
- `vendor/bin/pint --dirty --test` — clean on every file in `files_modified`
- `php artisan route:list --path=admin/audit-logs` — only index (GET) and view (GET) routes, no create/edit/delete
- `composer.json` unchanged — no new dependency added (`git diff composer.json composer.lock` empty)
</verification>

<success_criteria>
- Only `super_admin` can see or reach the audit log Resource — its nav item and every route 403 for every other role
- Super admin can list, filter (user/action/date-range), and view individual audit_logs rows with old/new values legible
- No create, edit, delete, or bulk-delete UI exists anywhere on the resource
- No new Composer dependency
</success_criteria>

<output>
After completion, create `.planning/quick/260731-nuk-crear-filament-resource-de-solo-lectura-/260731-nuk-SUMMARY.md`
</output>

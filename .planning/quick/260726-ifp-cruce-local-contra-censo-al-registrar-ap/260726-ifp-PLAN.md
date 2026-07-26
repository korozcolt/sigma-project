---
phase: quick
plan: 260726-ifp
type: execute
wave: 1
depends_on: []
files_modified:
  - app/Enums/VoterStatus.php
  - app/Filament/Resources/Voters/Tables/VotersTable.php
  - tests/Feature/Filament/VoterResourceTest.php
  - resources/views/livewire/leader/register-voter.blade.php
  - tests/Feature/Leader/RegisterVoterCensusWarningTest.php
  - app/Jobs/DispatchCensusRevalidation.php
  - app/Console/Commands/ReconcileCensusValidations.php
  - routes/console.php
  - tests/Feature/Jobs/DispatchCensusRevalidationTest.php
  - tests/Feature/Filament/RevalidateLeaderVotersActionTest.php
autonomous: true
requirements: []

must_haves:
  truths:
    - "When a líder types a document number that does not exist in the campaign's local census, blurring the field shows a non-blocking inline warning below it, and 'Guardar Apoyo' stays enabled and successfully saves"
    - "When the líder ignores the warning and saves anyway, the created apoyo gets status CENSUS_NOT_FOUND instead of the default PENDING_REVIEW"
    - "When the document number IS found in the local census, no warning appears and the apoyo saves with its normal default status (PENDING_REVIEW), unaffected by this change"
    - "CENSUS_NOT_FOUND voters are visible and filterable by a reviewer in the admin Voters table (badge renders correctly, status filter includes it)"
    - "An admin/reviewer can, from the admin Voters table, trigger a background revalidation of every pending/not-found apoyo belonging to one specific líder, without waiting for the hourly schedule"
    - "Every hour, a scheduled command automatically re-queues per-voter census validation jobs for all pending/not-found apoyos platform-wide, reusing the existing orphaned ValidateVoterAgainstCensus job"
  artifacts:
    - path: "app/Enums/VoterStatus.php"
      provides: "New CENSUS_NOT_FOUND case with label/color/icon/description, semantically distinct from REJECTED_CENSUS"
    - path: "app/Filament/Resources/Voters/Tables/VotersTable.php"
      provides: "Status badge color match handles CENSUS_NOT_FOUND (no UnhandledMatchError); new role-gated headerAction 'revalidateLeaderVoters' that dispatches DispatchCensusRevalidation for a chosen líder"
    - path: "resources/views/livewire/leader/register-voter.blade.php"
      provides: "updatedDocumentNumber() blur hook runs the local census cross-check via VoterValidationService::documentExistsInCensus(); non-blocking inline banner; save() assigns CENSUS_NOT_FOUND when not found locally"
    - path: "app/Jobs/DispatchCensusRevalidation.php"
      provides: "New ShouldQueue job, optional leaderId, queries voters with status PENDING_REVIEW or CENSUS_NOT_FOUND and dispatches the existing ValidateVoterAgainstCensus job per matching voter"
    - path: "app/Console/Commands/ReconcileCensusValidations.php"
      provides: "New census:reconcile-validation Artisan command that dispatchSync()'s DispatchCensusRevalidation"
    - path: "routes/console.php"
      provides: "Schedule::command('census:reconcile-validation')->hourly()->withoutOverlapping(10), matching the census:reconcile-live precedent"
  key_links:
    - from: "resources/views/livewire/leader/register-voter.blade.php updatedDocumentNumber()"
      to: "App\\Services\\VoterValidationService::documentExistsInCensus()"
      via: "app(VoterValidationService::class)->documentExistsInCensus($this->campaign_id, $this->document_number)"
    - from: "resources/views/livewire/leader/register-voter.blade.php save()"
      to: "App\\Enums\\VoterStatus::CENSUS_NOT_FOUND"
      via: "Voter::create(['status' => $foundInCensus ? VoterStatus::PENDING_REVIEW : VoterStatus::CENSUS_NOT_FOUND, ...])"
    - from: "app/Filament/Resources/Voters/Tables/VotersTable.php headerAction 'revalidateLeaderVoters'"
      to: "App\\Jobs\\DispatchCensusRevalidation"
      via: "DispatchCensusRevalidation::dispatch((int) $data['leader_id'])"
    - from: "app/Console/Commands/ReconcileCensusValidations.php handle()"
      to: "App\\Jobs\\DispatchCensusRevalidation"
      via: "DispatchCensusRevalidation::dispatchSync()"
    - from: "app/Jobs/DispatchCensusRevalidation.php handle()"
      to: "App\\Jobs\\ValidateVoterAgainstCensus"
      via: "ValidateVoterAgainstCensus::dispatch($voter) per matching voter, which internally calls VoterValidationService::validateAndUpdate()"
    - from: "routes/console.php"
      to: "census:reconcile-validation command"
      via: "Schedule::command('census:reconcile-validation')->hourly()->withoutOverlapping(10)"
---

<objective>
Add a LOCAL (no live Registraduría call) census cross-check to the Líder's "Registrar Apoyo" flow: when the document number loses focus, check it against the campaign's already-imported local census (`CensusRecord`) and show a non-blocking inline warning if it's not found — the líder can always save anyway. A new `VoterStatus::CENSUS_NOT_FOUND` case tracks apoyos saved despite the warning, distinct from `REJECTED_CENSUS`. Add two background reconciliation surfaces that both reuse the existing orphaned `ValidateVoterAgainstCensus` job: an hourly scheduled command (matching the `census:reconcile-live` precedent) and an admin/reviewer-only manual action in the Filament Voters table to revalidate one líder's apoyos on demand.

Purpose: catch likely-bad cédulas at data-entry time without blocking the líder's workflow (network/live lookups stay out of the hot path), while giving admins/reviewers both automatic and on-demand ways to reconcile these flagged apoyos as the local census evolves.

Output:
- `VoterStatus::CENSUS_NOT_FOUND` enum case, wired into the Filament Voters table badge and filters.
- Non-blocking inline warning banner in the Líder's register-voter form, triggered on document-number blur.
- New `App\Jobs\DispatchCensusRevalidation` (optionally leader-scoped) that queues the existing per-voter `ValidateVoterAgainstCensus` job.
- New `census:reconcile-validation` Artisan command, scheduled hourly in `routes/console.php`.
- New admin/reviewer-only Filament header action to trigger a specific líder's revalidation on demand.
- Pest coverage for every piece above.
</objective>

<execution_context>
@$HOME/.claude/get-shit-done/workflows/execute-plan.md
@$HOME/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@.planning/quick/260726-ifp-cruce-local-contra-censo-al-registrar-ap/260726-ifp-CONTEXT.md

<interfaces>
Key contracts extracted from the codebase. Use directly, no exploration needed.

From app/Services/VoterValidationService.php (DO NOT MODIFY — already has exactly what's needed):
```php
// LOCAL-only check, no live call. Use this for the blur-triggered warning AND at save() time.
public function documentExistsInCensus(int $campaignId, string $documentNumber): bool;

// Used by the orphaned per-voter job — updates status + writes a ValidationHistory row.
public function validateAndUpdate(Voter $voter): array; // ['voter' => Voter, 'found' => bool, 'match' => ?CensusRecord]
```

From app/Jobs/ValidateVoterAgainstCensus.php (DO NOT MODIFY — the orphaned job both background surfaces must reuse):
```php
namespace App\Jobs;

class ValidateVoterAgainstCensus implements ShouldQueue
{
    use Queueable;

    public function __construct(public Voter $voter) {}

    public function handle(VoterValidationService $validationService): void
    {
        $validationService->validateAndUpdate($this->voter);
    }
}
```

From app/Console/Commands/ReconcileLivePollingPlaces.php (the exact pattern to mirror for the new command):
```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ReconcileFallbackPollingPlaces;
use Illuminate\Console\Command;

class ReconcileLivePollingPlaces extends Command
{
    protected $signature = 'census:reconcile-live';
    protected $description = '...';

    public function handle(): int
    {
        ReconcileFallbackPollingPlaces::dispatchSync();
        return self::SUCCESS;
    }
}
```

From routes/console.php (current end of file — add the new schedule line directly after this one, do not touch it):
```php
// Reintenta la consulta en vivo para votantes resueltos por fuente de respaldo (RECON-01)
Schedule::command('census:reconcile-live')->hourly()->withoutOverlapping(10);
```

From app/Enums/VoterStatus.php (current cases — add CENSUS_NOT_FOUND as a new case, every match() below is exhaustive so ALL FOUR methods need the new arm):
```php
enum VoterStatus: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    case PENDING_REVIEW = 'pending_review';
    case REJECTED_CENSUS = 'rejected_census';
    case VERIFIED_CENSUS = 'verified_census';
    // ... CORRECTION_REQUIRED, VERIFIED_CALL, CONFIRMED, VOTED, DID_NOT_VOTE, DUPLICATE

    public function getLabel(): ?string   // match($this) => ...
    public function getColor(): string|array|null   // match($this) => ...
    public function getIcon(): ?string   // match($this) => ...
    public function getDescription(): ?string   // match($this) => ...
}
```

From app/Filament/Resources/Voters/Tables/VotersTable.php (the status column ALSO has its own exhaustive match — this is a second, separate override of the enum's HasColor, must get the new arm too):
```php
TextColumn::make('status')
    ->label('Estado')
    ->badge()
    ->color(fn (VoterStatus $state): string => match ($state) {
        VoterStatus::PENDING_REVIEW => 'gray',
        VoterStatus::REJECTED_CENSUS => 'danger',
        // ... etc — add VoterStatus::CENSUS_NOT_FOUND => 'warning' here
    })
    ->sortable(),
```
Existing per-record action pattern to mirror for icon/color/role-gating conventions (do not modify this action):
```php
Action::make('validateCensus')
    ->label('Validar contra Censo')
    ->icon('heroicon-o-shield-check')
    ->color('info')
    ->requiresConfirmation()
    ->visible(fn (Voter $record): bool => $record->status !== VoterStatus::DUPLICATE)
    ->action(function (Voter $record): void {
        app(VoterValidationService::class)->validateAndUpdate($record);
        Notification::make()->title('Validación contra censo completada')->success()->send();
    }),
```

From app/Filament/Resources/Voters/Pages/EditVoter.php (role-gated Action with a ->form([...]) modal — the exact pattern to mirror for the new header action):
```php
Action::make('reassignDuplicateOwner')
    ->label('Reasignar dueño de duplicado')
    ->icon('heroicon-o-arrows-right-left')
    ->color('warning')
    ->visible(fn (): bool => auth()->user()?->hasAnyRole([UserRole::ADMIN_CAMPAIGN->value, UserRole::SUPER_ADMIN->value]) ?? false)
    ->form(function (): array {
        return [
            Select::make('new_owner_user_id')->label('...')->options(...)->native(false)->required(),
        ];
    })
    ->action(function (array $data): void { /* ... */ }),
```

From app/Filament/Resources/Voters/Schemas/VoterForm.php (the leader Select options pattern to mirror, no campaign scoping used today — stay consistent):
```php
Select::make('registered_by')->label('Líder')->relationship(
    name: 'registeredBy',
    titleAttribute: 'name',
    modifyQueryUsing: fn (Builder $query, Get $get) => $query->role(UserRole::LEADER->value)->orderBy('name'),
);
// Elsewhere, a plain options() variant for a similar role-filtered dropdown:
Select::make('coordinator_user_id')->options(fn () => User::query()->role(UserRole::COORDINATOR->value)->orderBy('name')->pluck('name', 'id'));
```

From app/Models/Voter.php (relevant columns, already migrated — no new migration needed anywhere in this plan):
```php
protected function casts(): array
{
    return [
        'status' => VoterStatus::class,   // plain string column, default 'pending_review' — new enum case needs zero migration
        // ...
    ];
}
public function registeredBy(): BelongsTo { return $this->belongsTo(User::class, 'registered_by'); }
```

From resources/views/livewire/leader/register-voter.blade.php (relevant excerpt — mount() already resolves $this->campaign_id; the field to hook is document_number, bound via wire:model.blur):
```php
public string $document_number = '';
public ?int $campaign_id = null;

public function mount(): void
{
    $campaign = auth()->user()->campaigns()->first();
    $this->campaign_id = $campaign?->id;
    // ...
}

public function save(): void
{
    $campaign = $this->campaign_id ? Campaign::query()->find($this->campaign_id) : auth()->user()->campaigns()->first();
    // ... validate() ...
    Voter::create([
        // ...
        'registered_by' => auth()->id(),
        'status' => VoterStatus::PENDING_REVIEW,   // <-- this line becomes conditional
    ]);
}
```
```blade
<flux:input
    wire:model.blur="document_number"
    label="Número de Documento *"
    type="text"
    inputmode="numeric"
    pattern="[0-9]*"
    placeholder="1234567890"
/>
<!-- new inline banner goes directly below this input -->
```
</interfaces>
</context>

<tasks>

<task type="auto">
  <name>Task 1: New VoterStatus::CENSUS_NOT_FOUND case, wired into the Filament Voters table</name>
  <files>
    app/Enums/VoterStatus.php
    app/Filament/Resources/Voters/Tables/VotersTable.php
    tests/Feature/Filament/VoterResourceTest.php
  </files>
  <action>
In app/Enums/VoterStatus.php, add a new case `CENSUS_NOT_FOUND = 'census_not_found';` directly after `REJECTED_CENSUS` (keep it visually near its semantic sibling). Add a matching arm to ALL FOUR exhaustive match() methods (getLabel, getColor, getIcon, getDescription) — omitting any one causes an UnhandledMatchError at runtime:
- getLabel(): `self::CENSUS_NOT_FOUND => 'No Encontrado en Censo',`
- getColor(): `self::CENSUS_NOT_FOUND => 'warning',` (distinct from REJECTED_CENSUS's 'danger' — this is a soft, reviewable flag, not a hard rejection, so it stays visually grouped with CORRECTION_REQUIRED's urgency level)
- getIcon(): `self::CENSUS_NOT_FOUND => 'heroicon-m-question-mark-circle',`
- getDescription(): `self::CENSUS_NOT_FOUND => 'El apoyo no se encontró en el censo electoral local al momento de registrarlo; requiere revisión o reconciliación en segundo plano.',`

In app/Filament/Resources/Voters/Tables/VotersTable.php, add a matching arm to the status column's own `->color(fn (VoterStatus $state): string => match ($state) { ... })` closure (this is a SEPARATE exhaustive match from the enum's own HasColor implementation — both must handle the new case or the table throws on render): `VoterStatus::CENSUS_NOT_FOUND => 'warning',` placed after the `VoterStatus::REJECTED_CENSUS => 'danger',` line.

In tests/Feature/Filament/VoterResourceTest.php, add two tests near the existing "can filter voters by status" test:
- `test('voters table renders a voter with the census-not-found status without error', ...)`: create a Voter with `status => VoterStatus::CENSUS_NOT_FOUND`, `Livewire::test(ListVoters::class)->assertSuccessful()->assertCanSeeTableRecords([$voter])`.
- `test('can filter voters by census not found status', ...)`: create one voter with CENSUS_NOT_FOUND and one with VERIFIED_CENSUS, filter the table by `status` = `VoterStatus::CENSUS_NOT_FOUND->value`, assert the first is visible and the second is not (mirrors the existing "can filter voters by status" test exactly).
  </action>
  <verify>
    <automated>php artisan test --filter=VoterResourceTest</automated>
  </verify>
  <done>New CENSUS_NOT_FOUND case exists with all four match arms filled in both VoterStatus.php and VotersTable.php's color closure; the two new Pest tests pass, proving the table renders and filters the new status without an UnhandledMatchError.</done>
</task>

<task type="auto" tdd="true">
  <name>Task 2: Local census cross-check on the Líder's register-voter form (blur warning + save-time status)</name>
  <files>
    resources/views/livewire/leader/register-voter.blade.php
    tests/Feature/Leader/RegisterVoterCensusWarningTest.php
  </files>
  <behavior>
    - Typing a document number that does NOT exist in the campaign's local census, then blurring the field (`->set('document_number', ...)` in tests fires the same `updated{Property}` hook `wire:model.blur` triggers) sets `censusNotFoundWarning` to true and the banner text is visible in the rendered output.
    - Typing a document number that DOES exist in the local census clears/keeps `censusNotFoundWarning` false, and no banner text is visible.
    - An incomplete/invalid document number (not exactly 10 digits) never triggers a census lookup and keeps `censusNotFoundWarning` false (no noise while the líder is still typing).
    - Saving with the warning active (cédula not found) still succeeds (`assertHasNoErrors()`) and the created Voter's `status` is `VoterStatus::CENSUS_NOT_FOUND`.
    - Saving with a cédula that IS found in the local census still succeeds and the created Voter's `status` is the default `VoterStatus::PENDING_REVIEW`, unchanged from today.
  </behavior>
  <action>
In resources/views/livewire/leader/register-voter.blade.php's Volt class:

1. Add `use App\Services\VoterValidationService;` to the top `use` block, placed after `use App\Rules\MaxTablesForPollingPlace;` and before `use Livewire\Volt\Component;`.

2. Add a new public property near the other boolean flags (after `public ?string $lastVoterName = null;`):
```php
public bool $censusNotFoundWarning = false;
```

3. Add a new lifecycle hook method (place it directly after `mount()`):
```php
public function updatedDocumentNumber(): void
{
    $this->censusNotFoundWarning = false;

    if (! preg_match('/^\d{10}$/', $this->document_number)) {
        return;
    }

    if (! $this->campaign_id) {
        return;
    }

    $this->censusNotFoundWarning = ! app(VoterValidationService::class)
        ->documentExistsInCensus($this->campaign_id, $this->document_number);
}
```

4. In `save()`, right before the `// Crear el apoyo` comment, compute the same local check fresh (do not trust the property alone — a paste-then-submit without a blur event could skip the hook):
```php
$foundInCensus = app(VoterValidationService::class)
    ->documentExistsInCensus($campaign->id, $this->document_number);
```
Then change the `Voter::create([...])` call's `'status' => VoterStatus::PENDING_REVIEW,` line to:
```php
'status' => $foundInCensus ? VoterStatus::PENDING_REVIEW : VoterStatus::CENSUS_NOT_FOUND,
```

5. In the Blade template, directly below the `document_number` `<flux:input>` element (inside the "Datos Personales" card, still inside the same `flex flex-col gap-4` wrapper), add the non-blocking inline banner — no modal, no confirmation, "Guardar Apoyo" stays enabled per the locked D-01 decision:
```blade
@if($censusNotFoundWarning)
    <div class="flex items-start gap-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
        <flux:icon.exclamation-triangle class="mt-0.5 h-4 w-4 shrink-0" />
        <span>Esta cédula no aparece en el censo actual, revísala.</span>
    </div>
@endif
```

Create tests/Feature/Leader/RegisterVoterCensusWarningTest.php covering the five <behavior> cases above. Follow tests/Feature/Leader/LeaderAppTest.php's setup conventions exactly (Role::create leader/admin, Municipality/Neighborhood/Campaign factories, leader User with campaigns()->attach($campaign)), but additionally create `CensusRecord::factory()->create(['campaign_id' => $campaign->id, 'document_number' => '...'])` for the "found" cases. Use `Volt::test('leader.register-voter')->set('document_number', '...')` to trigger the blur hook, and assert against `->get('censusNotFoundWarning')` plus `->assertSee(...)`/`->assertDontSee('Esta cédula no aparece en el censo actual, revísala.')`. For the save-time tests, fill the full required form (first_name, last_name, phone, municipality_id) then `->call('save')->assertHasNoErrors()`, then assert `Voter::where('document_number', '...')->first()->status` equals the expected enum case.
  </action>
  <verify>
    <automated>php artisan test --filter=RegisterVoterCensusWarningTest</automated>
  </verify>
  <done>register-voter.blade.php shows the non-blocking banner exactly when the local census check misses on blur, never blocks saving, and persists CENSUS_NOT_FOUND vs PENDING_REVIEW correctly at save time. All 5 new Pest tests pass.</done>
</task>

<task type="auto" tdd="true">
  <name>Task 3: Background reconciliation — DispatchCensusRevalidation job + scheduled census:reconcile-validation command</name>
  <files>
    app/Jobs/DispatchCensusRevalidation.php
    app/Console/Commands/ReconcileCensusValidations.php
    routes/console.php
    tests/Feature/Jobs/DispatchCensusRevalidationTest.php
  </files>
  <behavior>
    - `(new DispatchCensusRevalidation)->handle()` dispatches `ValidateVoterAgainstCensus` for every Voter whose status is `PENDING_REVIEW` or `CENSUS_NOT_FOUND`, and does NOT dispatch it for voters in any other status (e.g. VERIFIED_CENSUS).
    - `(new DispatchCensusRevalidation(leaderId: $leader->id))->handle()` only dispatches for voters whose `registered_by` matches that leader, leaving other leaders' matching voters untouched.
    - `routes/console.php` contains `Schedule::command('census:reconcile-validation')->hourly()->withoutOverlapping(10)`.
    - `Artisan::call('census:reconcile-validation')` dispatches `DispatchCensusRevalidation` (via `Bus::fake()` + `Bus::assertDispatched`).
  </behavior>
  <action>
Create app/Jobs/DispatchCensusRevalidation.php:
```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\VoterStatus;
use App\Models\Voter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DispatchCensusRevalidation implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ?int $leaderId = null,
    ) {}

    public function handle(): void
    {
        $voters = Voter::query()
            ->whereIn('status', [VoterStatus::PENDING_REVIEW->value, VoterStatus::CENSUS_NOT_FOUND->value])
            ->when($this->leaderId, fn ($query) => $query->where('registered_by', $this->leaderId))
            ->get();

        foreach ($voters as $voter) {
            ValidateVoterAgainstCensus::dispatch($voter);
        }

        Log::info('census.revalidation.dispatched', [
            'leader_id' => $this->leaderId,
            'count' => $voters->count(),
        ]);
    }
}
```
(`ValidateVoterAgainstCensus` is in the same `App\Jobs` namespace — no import needed.)

Create app/Console/Commands/ReconcileCensusValidations.php, mirroring app/Console/Commands/ReconcileLivePollingPlaces.php exactly:
```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\DispatchCensusRevalidation;
use Illuminate\Console\Command;

class ReconcileCensusValidations extends Command
{
    protected $signature = 'census:reconcile-validation';

    protected $description = 'Re-checks apoyos pendientes o no encontrados en el censo local y encola su validación individual';

    public function handle(): int
    {
        DispatchCensusRevalidation::dispatchSync();

        return self::SUCCESS;
    }
}
```

In routes/console.php, add directly after the existing `census:reconcile-live` line, without touching it:
```php
// Revalida en background los apoyos pendientes o no encontrados en el censo local
Schedule::command('census:reconcile-validation')->hourly()->withoutOverlapping(10);
```

Create tests/Feature/Jobs/DispatchCensusRevalidationTest.php with 4 tests covering the <behavior> block above, following tests/Feature/Jobs/ReconcileFallbackPollingPlacesTest.php's structure/style (RefreshDatabase, Bus::fake(), `Bus::assertDispatched(ValidateVoterAgainstCensus::class, fn ($job) => $job->voter->is($expectedVoter))`, and the same `Schedule::command(...)` file_get_contents(base_path('routes/console.php')) assertion + `Artisan::call()` + `Bus::assertDispatched` pattern used by ReconcileFallbackPollingPlacesTest's last two tests).
  </action>
  <verify>
    <automated>php artisan test --filter=DispatchCensusRevalidationTest</automated>
  </verify>
  <done>DispatchCensusRevalidation exists and correctly filters by status (+ optional leaderId) before dispatching the reused ValidateVoterAgainstCensus job per voter. census:reconcile-validation is registered and scheduled hourly with a 10-minute overlap guard. All 4 new Pest tests pass.</done>
</task>

<task type="auto" tdd="true">
  <name>Task 4: Admin/reviewer manual "revalidate one líder's apoyos" action in the Voters table</name>
  <files>
    app/Filament/Resources/Voters/Tables/VotersTable.php
    tests/Feature/Filament/RevalidateLeaderVotersActionTest.php
  </files>
  <behavior>
    - A super_admin/admin_campaign/reviewer sees the `revalidateLeaderVoters` header action on the Voters table (via `assertActionVisible`), can call it with `['leader_id' => $leader->id]`, and it dispatches `DispatchCensusRevalidation` with that exact `leaderId` (via `Bus::fake()`), plus sends a success notification.
    - A leader role does NOT see the action (via `assertActionHidden`), even though the leader role otherwise has panel access to render the page (mirrors the existing `reassignDuplicateOwner` hidden-role test precedent).
  </behavior>
  <action>
In app/Filament/Resources/Voters/Tables/VotersTable.php, add two new `use` statements in alphabetical position: `use App\Jobs\DispatchCensusRevalidation;` (after the `App\Enums\*` block, before `App\Models\Voter`) and `use App\Models\User;` (before `use App\Models\Voter;`).

Add a `->headerActions([...])` chain to the `Table` builder (place it directly after `->filters([...])` and before `->recordActions([...])`):
```php
->headerActions([
    Action::make('revalidateLeaderVoters')
        ->label('Revalidar apoyos de un líder')
        ->icon('heroicon-o-arrow-path')
        ->color('info')
        ->visible(fn (): bool => auth()->user()?->hasAnyRole([
            UserRole::SUPER_ADMIN->value,
            UserRole::ADMIN_CAMPAIGN->value,
            UserRole::REVIEWER->value,
        ]) ?? false)
        ->form([
            Select::make('leader_id')
                ->label('Líder')
                ->options(fn (): array => User::query()
                    ->role(UserRole::LEADER->value)
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->searchable()
                ->required(),
        ])
        ->action(function (array $data): void {
            DispatchCensusRevalidation::dispatch((int) $data['leader_id']);

            Notification::make()
                ->title('Revalidación en background iniciada')
                ->success()
                ->send();
        }),
])
```
(`Select`, `Action`, and `Notification` are already imported in this file — no new imports needed for those. `User::query()->role(...)` mirrors the exact pattern already used in VoterForm.php's `coordinator_user_id` select.)

Create tests/Feature/Filament/RevalidateLeaderVotersActionTest.php, mirroring tests/Feature/Filament/VoterResourceTest.php's `beforeEach` (roles seeded via `collect(UserRole::values())->each(...)`, `actingAs($admin)`, `Session::put('campaign_context.mode', 'all')`) and tests/Feature/ApoyoDuplicateSequenceTest.php's hidden-role assertion pattern. Cover both <behavior> cases: use `Bus::fake()` + `Livewire::test(ListVoters::class)->callAction('revalidateLeaderVoters', data: ['leader_id' => $leader->id])->assertNotified()` then `Bus::assertDispatched(DispatchCensusRevalidation::class, fn ($job) => $job->leaderId === $leader->id)` for the visible/dispatch case, and `Livewire::test(ListVoters::class)->assertActionHidden('revalidateLeaderVoters')` acting as a plain leader for the hidden case.
  </action>
  <verify>
    <automated>php artisan test --filter=RevalidateLeaderVotersActionTest</automated>
  </verify>
  <done>The Voters table has a role-gated headerAction that dispatches DispatchCensusRevalidation scoped to a chosen líder, with a success notification. Visible to super_admin/admin_campaign/reviewer, hidden from leader. Both new Pest tests pass.</done>
</task>

</tasks>

<verification>
Run the full targeted suite one more time to confirm nothing else broke:
php artisan test --filter=VoterResourceTest
php artisan test --filter=RegisterVoterCensusWarningTest
php artisan test --filter=DispatchCensusRevalidationTest
php artisan test --filter=RevalidateLeaderVotersActionTest
php artisan test --filter=VoterValidationServiceTest
php artisan test --filter=LeaderAppTest
vendor/bin/pint --dirty --test
</verification>

<success_criteria>
- Líder's register-voter form warns (non-blocking, banner-only, below the document field) when a document number is not found in the campaign's LOCAL census, on blur — no live Registraduría call anywhere in this flow.
- Saving despite the warning persists `VoterStatus::CENSUS_NOT_FOUND`; saving a cédula found locally persists the unchanged default `VoterStatus::PENDING_REVIEW`.
- `VoterStatus::CENSUS_NOT_FOUND` renders and filters correctly in the admin Voters table.
- An hourly `census:reconcile-validation` scheduled command and an admin/reviewer-only manual per-líder action both reuse the existing orphaned `ValidateVoterAgainstCensus` job via the new `DispatchCensusRevalidation` job.
- No new migrations (status stays a plain string column); no dependency changes.
</success_criteria>

<output>
After completion, create `.planning/quick/260726-ifp-cruce-local-contra-censo-al-registrar-ap/260726-ifp-SUMMARY.md`
</output>

---
phase: quick
plan: 260726-jao
type: execute
wave: 1
depends_on: []
files_modified:
  - database/migrations/2026_07_26_170000_create_registraduria_lookups_table.php
  - app/Models/RegistraduriaLookup.php
  - database/factories/RegistraduriaLookupFactory.php
  - app/Services/PollingPlaceResolver.php
  - tests/Feature/Services/PollingPlaceResolverTest.php
  - app/Enums/VoterStatus.php
  - app/Filament/Resources/Voters/Tables/VotersTable.php
  - tests/Feature/Filament/VoterResourceTest.php
  - app/Filament/Resources/Voters/Concerns/HasRegistraduriaPolling.php
  - tests/Feature/Filament/VoterRegistraduriaRefreshTest.php
  - resources/views/livewire/leader/register-voter.blade.php
  - tests/Feature/Leader/RegisterVoterRegistraduriaLookupTest.php
  - resources/views/livewire/coordinator/create-leader.blade.php
  - tests/Feature/CreateLeaderOtpTest.php
  - tests/Feature/Coordinator/CreateLeaderRegistraduriaLookupTest.php
autonomous: true
requirements: []

must_haves:
  truths:
    - "A cédula already resolved live once (via admin lookup, líder form, coordinator form check, or the reconciliation job) is never re-queried against the live Registraduría source again anywhere in the system — every consumer checks the permanent table first"
    - "In the admin Filament Voter edit flow, consulting a cédula previously resolved live shows the confirmed result instantly from the permanent table instead of opening the live 2captcha modal"
    - "When a líder blurs a document number already confirmed by Registraduría, register-voter.blade.php auto-fills puesto de votación, dirección, and mesa, and shows a green 'Verificado por Registraduría' banner that replaces (not stacks with) any census warning"
    - "Saving a líder-registered apoyo whose cédula is confirmed by the permanent Registraduría table persists VoterStatus::VERIFIED_REGISTRADURIA — distinct from and stronger than VERIFIED_CENSUS/PENDING_REVIEW; census-only and not-found cases are unaffected regressions"
    - "The coordinator's 'Agregar Líder' form has a required, unique Número de Documento field; blurring a cédula already confirmed by Registraduría shows the same green banner; saving always persists the líder's document_number onto the created User"
    - "PollingPlaceResolver::resolveAutomated() (used by the headless ReconcileFallbackPollingPlaces job) checks the permanent lookup table before attempting any live adapter call, upgrading fallback-sourced voters to LIVE without paying for a new live lookup when a prior result already exists"
    - "Every genuine live Registraduría success — from the admin interactive flow or the headless reconciliation job — is persisted into the permanent table, so savings compound over time"
  artifacts:
    - path: "database/migrations/2026_07_26_170000_create_registraduria_lookups_table.php"
      provides: "Permanent, non-expiring registraduria_lookups table (document_number unique, parsed fields, source, informational nullable campaign_id)"
    - path: "app/Models/RegistraduriaLookup.php"
      provides: "Eloquent model + toRegistraduriaFields() converting the row back into RegistraduriaService's field shape"
    - path: "app/Services/PollingPlaceResolver.php"
      provides: "resolveFromPermanentLookup()/persistPermanentLookup(); resolveAutomated() checks the permanent table before any live adapter attempt"
    - path: "app/Enums/VoterStatus.php"
      provides: "New VERIFIED_REGISTRADURIA case (all 4 match arms), stronger than VERIFIED_CENSUS"
    - path: "app/Filament/Resources/Voters/Concerns/HasRegistraduriaPolling.php"
      provides: "30-day Cache mechanism replaced by permanent-table reads (openRegistraduriaBrowser) and writes (handleRegistraduriaResult)"
    - path: "resources/views/livewire/leader/register-voter.blade.php"
      provides: "Blur cascade (Registraduría table -> local census -> warning), autofill + green banner, save() picks VERIFIED_REGISTRADURIA/PENDING_REVIEW/CENSUS_NOT_FOUND"
    - path: "resources/views/livewire/coordinator/create-leader.blade.php"
      provides: "New required+unique document_number field, same blur cascade + banners, persisted onto the created leader User"
  key_links:
    - from: "app/Services/PollingPlaceResolver.php resolveAutomated()"
      to: "App\\Models\\RegistraduriaLookup"
      via: "resolveFromPermanentLookup($cedula) checked before the liveAdapters loop"
    - from: "resources/views/livewire/leader/register-voter.blade.php updatedDocumentNumber()"
      to: "App\\Services\\PollingPlaceResolver::resolveFromPermanentLookup()"
      via: "app(PollingPlaceResolver::class)->resolveFromPermanentLookup($this->document_number)"
    - from: "resources/views/livewire/leader/register-voter.blade.php save()"
      to: "App\\Enums\\VoterStatus::VERIFIED_REGISTRADURIA"
      via: "match(true) picks VERIFIED_REGISTRADURIA when RegistraduriaLookup::where('document_number', ...)->exists()"
    - from: "resources/views/livewire/coordinator/create-leader.blade.php save()"
      to: "App\\Models\\User::document_number"
      via: "User::create(['document_number' => $this->document_number, ...])"
    - from: "app/Filament/Resources/Voters/Concerns/HasRegistraduriaPolling.php handleRegistraduriaResult()"
      to: "App\\Services\\PollingPlaceResolver::persistPermanentLookup()"
      via: "app(PollingPlaceResolver::class)->persistPermanentLookup($cedula, $data, CampaignContext::currentCampaignId())"
---

<objective>
Replace the 30-day, cache-store-backed Registraduría result cache (`HasRegistraduriaPolling`, key `registraduria:cedula:{cedula}`) with a permanent, non-expiring `registraduria_lookups` table, and wire it into every point that consults or produces a live Registraduría result: the admin Filament flow (read + write), the Líder's "Registrar Apoyo" form (read + autofill + banner + save status), the Coordinador's "Agregar Líder" form (new document_number field + read + banner), and `PollingPlaceResolver::resolveAutomated()` (the headless reconciliation cascade, read + write). A live Registraduría result is treated as more authoritative than a `CensusRecord` match everywhere it's checked. A new `VoterStatus::VERIFIED_REGISTRADURIA` case marks apoyos confirmed directly against this permanent table, distinct from and stronger than `VERIFIED_CENSUS`.

Purpose: stop the system from re-paying live/2captcha Registraduría lookups for cédulas that have already been resolved once, anywhere — data-entry forms, admin edits, and the automated background job all benefit from the exact same accumulated table.

Output:
- `registraduria_lookups` table + `RegistraduriaLookup` model, permanent (no TTL, survives `cache:clear`).
- `PollingPlaceResolver::resolveFromPermanentLookup()` / `persistPermanentLookup()`, and `resolveAutomated()` checking the permanent table before any live attempt.
- `HasRegistraduriaPolling` migrated off `Cache::get`/`Cache::put` onto the new table.
- Líder's register-voter form: blur-triggered autofill (puesto/dirección/mesa) + green "Verificado por Registraduría" banner + `VoterStatus::VERIFIED_REGISTRADURIA` at save time.
- Coordinador's create-leader form: new required+unique `document_number` field, same blur cascade/banners, persisted onto the created leader.
- Pest coverage for every piece above.
</objective>

<execution_context>
@$HOME/.claude/get-shit-done/workflows/execute-plan.md
@$HOME/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@.planning/quick/260726-jao-tabla-permanente-de-resultados-de-regist/260726-jao-CONTEXT.md

<interfaces>
Key contracts extracted from the codebase. Use directly, no exploration needed.

From app/Services/RegistraduriaService.php (DO NOT MODIFY — this is the field shape every layer of this plan must match):
```php
// getResult()/parseConsultaHtml() always produce this exact 7-key shape:
// puesto_nombre, puesto_codigo, zona_codigo, mesa_numero, departamento, municipio, direccion
// puesto_codigo/zona_codigo are ALWAYS '' for real live results (no such column exists in
// the real wsp response) — never assume they're populated.
```

From app/Services/PollingPlaceResolutionResult.php (DO NOT MODIFY):
```php
final readonly class PollingPlaceResolutionResult
{
    public function __construct(
        public PollingPlaceSource $source,
        public array $fields,          // same 7-key shape as above
        public ?int $pollingPlaceId = null,
        public ?string $tableNumber = null,   // already ltrim('0')'d
    ) {}
}
```

From app/Services/PollingPlaceResolver.php (current relevant excerpt — resolveOrCreatePollingPlace() is already public and reused, do not duplicate its firstOrCreate/matching logic):
```php
namespace App\Services;

use App\Enums\PollingPlaceSource;
use App\Models\CensusRecord;
use App\Models\Municipality;
use App\Models\NationalCensusRecord;
use App\Models\PollingPlace;
use App\Models\PollingPlaceResolution;
use App\Models\Voter;
use Illuminate\Support\Sleep;

class PollingPlaceResolver
{
    public function __construct(private readonly iterable $liveAdapters) {}

    public function isLiveReachable(): bool { /* unchanged */ }
    public function startLiveLookup(string $cedula): string { /* unchanged */ }
    public function resolveFromCampaignCensus(string $cedula): ?PollingPlaceResolutionResult { /* unchanged */ }
    public function resolveFromNationalSnapshot(string $cedula): ?PollingPlaceResolutionResult { /* unchanged, mirror this shape exactly for resolveFromPermanentLookup() */ }
    public function persist(?Voter $voter, PollingPlaceResolutionResult $result, bool $isExplicitOverride, string $resolvedVia): ?PollingPlaceResolutionResult { /* unchanged */ }
    public function resolveOrCreatePollingPlace(array $fields): ?PollingPlace { /* unchanged, already public, reuse directly */ }

    // resolveAutomated() current body — this plan adds a permanent-table check as the
    // FIRST tier, before the liveAdapters loop:
    public function resolveAutomated(string $cedula, Voter $voter, string $resolvedVia = 'reconciliation'): ?PollingPlaceResolutionResult
    {
        foreach ($this->liveAdapters as $adapter) {
            if ($fields = $this->attemptLiveAutomated($adapter, $cedula)) {
                $result = new PollingPlaceResolutionResult(
                    source: PollingPlaceSource::LIVE,
                    fields: $fields,
                    pollingPlaceId: $this->resolveOrCreatePollingPlace($fields)?->id,
                    tableNumber: ltrim($fields['mesa_numero'] ?? '', '0') ?: null,
                );
                return $this->persist($voter, $result, isExplicitOverride: false, resolvedVia: $resolvedVia);
            }
        }
        if ($fromSnapshot = $this->resolveFromNationalSnapshot($cedula)) {
            return $this->persist($voter, $fromSnapshot, isExplicitOverride: false, resolvedVia: $resolvedVia);
        }
        return null;
    }
}
```

From app/Filament/Resources/Voters/Concerns/HasRegistraduriaPolling.php (current cache-based excerpt — this plan replaces both marked spots):
```php
use Illuminate\Support\Facades\Cache;   // REMOVE this import once both spots below are gone

private const CACHE_TTL_DAYS = 30;      // REMOVE

private function registraduriaCacheKey(string $cedula): string   // REMOVE
{
    return "registraduria:cedula:{$cedula}";
}

public function openRegistraduriaBrowser(string $cedula): void
{
    // ... blank-cedula guard unchanged ...
    $resolver = app(PollingPlaceResolver::class);

    // REPLACE this whole "Layer 1: Redis cache" block:
    $cached = Cache::get($this->registraduriaCacheKey($cedula));
    if ($cached) {
        $this->applyResolvedFields(new PollingPlaceResolutionResult(PollingPlaceSource::LIVE, $cached));
        Notification::make()->title('Puesto de votación (desde caché)')->body(...)->success()->send();
        return;
    }
    // ... rest (live attempt, DB reconstruction, snapshot) unchanged ...
}

#[On('registraduria-result')]
public function handleRegistraduriaResult(array $data): void
{
    // ... unchanged up through applyResolvedFields() ...
    $this->applyResolvedFields(new PollingPlaceResolutionResult(PollingPlaceSource::LIVE, $data), $isExplicitOverride);

    // REPLACE this whole cache-write block:
    $cedula = $this->data['document_number'] ?? null;
    if ($cedula) {
        Cache::put($this->registraduriaCacheKey($cedula), $data, now()->addDays(self::CACHE_TTL_DAYS));
    }
    // ... notification unchanged ...
}
```
`use App\Services\CampaignContext;` is already imported in this file (used by `fillPollingPlaceFields()`) — reuse it, no new import needed for that.

From app/Enums/VoterStatus.php (current cases — every match() below is exhaustive, ALL FOUR methods need the new arm):
```php
enum VoterStatus: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    case PENDING_REVIEW = 'pending_review';
    case REJECTED_CENSUS = 'rejected_census';
    case CENSUS_NOT_FOUND = 'census_not_found';
    case VERIFIED_CENSUS = 'verified_census';
    // add VERIFIED_REGISTRADURIA here, directly after VERIFIED_CENSUS
    case CORRECTION_REQUIRED = 'correction_required';
    // ... VERIFIED_CALL, CONFIRMED, VOTED, DID_NOT_VOTE, DUPLICATE
}
```

From app/Filament/Resources/Voters/Tables/VotersTable.php (SEPARATE exhaustive match on the status column — must also get the new arm):
```php
TextColumn::make('status')->badge()->color(fn (VoterStatus $state): string => match ($state) {
    VoterStatus::PENDING_REVIEW => 'gray',
    VoterStatus::REJECTED_CENSUS => 'danger',
    VoterStatus::CENSUS_NOT_FOUND => 'warning',
    VoterStatus::VERIFIED_CENSUS => 'info',
    // add VoterStatus::VERIFIED_REGISTRADURIA => 'success', here
    VoterStatus::CORRECTION_REQUIRED => 'warning',
    // ...
})->sortable(),
```

From resources/views/livewire/leader/register-voter.blade.php (current relevant excerpt, post-260726-ifp — the blur hook and save() this plan extends):
```php
use App\Enums\VoterStatus;
use App\Models\Campaign;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\Neighborhood;
use App\Models\PollingPlace;
use App\Models\Voter;
use App\Rules\MaxTablesForPollingPlace;
use App\Services\VoterValidationService;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

public bool $censusNotFoundWarning = false;

public function updatedDocumentNumber(): void
{
    $this->censusNotFoundWarning = false;
    if (! preg_match('/^\d{10}$/', $this->document_number)) return;
    if (! $this->campaign_id) return;
    $this->censusNotFoundWarning = ! app(VoterValidationService::class)
        ->documentExistsInCensus($this->campaign_id, $this->document_number);
}

public function save(): void
{
    // ... campaign resolution + $this->validate([...]) + municipality/department checks unchanged ...
    $foundInCensus = app(VoterValidationService::class)
        ->documentExistsInCensus($campaign->id, $this->document_number);

    Voter::create([
        // ... unchanged fields ...
        'polling_place_id' => $this->polling_place_id,
        'polling_table_number' => $this->polling_table_number,
        'address' => $this->address,
        // ...
        'status' => $foundInCensus ? VoterStatus::PENDING_REVIEW : VoterStatus::CENSUS_NOT_FOUND,
    ]);
    // ... registerAnother / redirect unchanged ...
}
```
```blade
<flux:input wire:model.blur="document_number" label="Número de Documento *" .../>
@if($censusNotFoundWarning)
    <div class="... bg-amber-50 ...">
        <flux:icon.exclamation-triangle .../>
        <span>Esta cédula no aparece en el censo actual, revísala.</span>
    </div>
@endif
<!-- polling_place_id select is wire:model.live, options filtered by $this->municipality_id -->
<!-- polling_table_number is wire:model.blur, address is wire:model.blur textarea -->
```

From resources/views/livewire/coordinator/create-leader.blade.php (current full relevant excerpt — no document_number field exists today):
```php
use App\Enums\UserRole;
use App\Models\Neighborhood;
use App\Models\User;
use App\Services\CampaignContext;
use App\Services\OtpVerificationService;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new class extends Component
{
    #[Validate('required|string|max:255')]
    public string $name = '';
    #[Validate('required|email|unique:users,email')]
    public string $email = '';
    // ... password, phone, otp fields, neighborhood_id, coordinator_user_id ...

    private function resolveActiveCampaign()
    {
        return $this->getCoordinatorUser()?->campaigns()->first() ?? CampaignContext::currentCampaign();
    }

    public function save(): void
    {
        if (! $this->otpVerified) { $this->addError('otp_code', '...'); return; }
        if (auth()->user()->hasRole(UserRole::COORDINATOR->value)) { $this->coordinator_user_id = auth()->id(); }
        $this->validate();   // validates every #[Validate]-attributed property automatically
        $coordinatorUser = $this->getCoordinatorUser();
        // ...
        $leader = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'phone' => $this->phone,
            'municipality_id' => $coordinatorUser->municipality_id,
            'coordinator_user_id' => $coordinatorUser->id,
            'neighborhood_id' => $this->neighborhood_id,
            'email_verified_at' => now(),
        ]);
        $leader->assignRole(UserRole::LEADER->value);
        // ...
    }
}
```
`User::$fillable` already includes `document_number` (nullable, unique, indexed at the DB level — this plan adds form-level `required` validation only, NOT a DB migration change).

From app/Console/Commands/CoordinatorForm.php-equivalent precedent (app/Filament/Resources/Coordinators/Schemas/CoordinatorForm.php — the ONLY existing document_number validation convention for User, mirror it exactly, no digit-count constraint):
```php
TextInput::make('document_number')->label('Número de documento')->required()->unique(ignoreRecord: true)->maxLength(50);
```
</interfaces>
</context>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: Permanent registraduria_lookups table + model + PollingPlaceResolver wiring</name>
  <files>
    database/migrations/2026_07_26_170000_create_registraduria_lookups_table.php
    app/Models/RegistraduriaLookup.php
    database/factories/RegistraduriaLookupFactory.php
    app/Services/PollingPlaceResolver.php
    tests/Feature/Services/PollingPlaceResolverTest.php
  </files>
  <behavior>
    - `resolveFromPermanentLookup($cedula)` returns null when no matching row exists.
    - `resolveFromPermanentLookup($cedula)` returns a `PollingPlaceResolutionResult` with `source === PollingPlaceSource::LIVE` and a resolved `pollingPlaceId` when a matching `RegistraduriaLookup` row exists.
    - `persistPermanentLookup($cedula, $fields)` creates a new row when none exists for that document_number.
    - Calling `persistPermanentLookup()` a second time for the same cédula updates the existing row in place (upsert) — no duplicate rows.
    - `resolveAutomated()` returns the permanent-table result WITHOUT calling `startLookup()` on any live adapter when a matching `RegistraduriaLookup` row already exists.
    - `resolveAutomated()` persists a fresh live success into the permanent table (in addition to updating the voter's `polling_place_source`) when no permanent-table row existed beforehand.
  </behavior>
  <action>
Run `php artisan make:migration create_registraduria_lookups_table --create=registraduria_lookups --no-interaction`. In the generated file's `up()`:
```php
Schema::create('registraduria_lookups', function (Blueprint $table) {
    $table->id();
    $table->string('document_number')->unique();
    $table->string('puesto_nombre')->nullable();
    $table->string('puesto_codigo')->nullable();
    $table->string('zona_codigo')->nullable();
    $table->string('mesa_numero')->nullable();
    $table->string('departamento')->nullable();
    $table->string('municipio')->nullable();
    $table->string('direccion')->nullable();
    $table->string('source')->default('live');
    $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
    $table->timestamps();
});
```
No expiration/TTL column by design — this table is permanent (survives `cache:clear`), unlike the mechanism it replaces. `campaign_id` is nullable and purely informational/audit — never used to scope reads (per locked decision: Registraduría results are cross-campaign global data, same precedent as the old cache key).

Run `php artisan make:model RegistraduriaLookup --no-interaction`, then write:
```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistraduriaLookup extends Model
{
    /** @use HasFactory<\Database\Factories\RegistraduriaLookupFactory> */
    use HasFactory;

    protected $fillable = [
        'document_number',
        'puesto_nombre',
        'puesto_codigo',
        'zona_codigo',
        'mesa_numero',
        'departamento',
        'municipio',
        'direccion',
        'source',
        'campaign_id',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Raw fields in the exact shape RegistraduriaService/PollingPlaceResolver already use
     * (puesto_nombre, puesto_codigo, zona_codigo, mesa_numero, departamento, municipio,
     * direccion) — no translation layer needed by callers.
     *
     * @return array{puesto_nombre: string, puesto_codigo: string, zona_codigo: string, mesa_numero: string, departamento: string, municipio: string, direccion: string}
     */
    public function toRegistraduriaFields(): array
    {
        return [
            'puesto_nombre' => $this->puesto_nombre ?? '',
            'puesto_codigo' => $this->puesto_codigo ?? '',
            'zona_codigo' => $this->zona_codigo ?? '',
            'mesa_numero' => $this->mesa_numero ?? '',
            'departamento' => $this->departamento ?? '',
            'municipio' => $this->municipio ?? '',
            'direccion' => $this->direccion ?? '',
        ];
    }
}
```

Create `database/factories/RegistraduriaLookupFactory.php`:
```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RegistraduriaLookup;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RegistraduriaLookup> */
class RegistraduriaLookupFactory extends Factory
{
    protected $model = RegistraduriaLookup::class;

    public function definition(): array
    {
        return [
            'document_number' => fake()->unique()->numerify('##########'),
            'puesto_nombre' => 'IE '.fake()->lastName(),
            'puesto_codigo' => (string) fake()->numberBetween(1, 99),
            'zona_codigo' => (string) fake()->numberBetween(1, 9),
            'mesa_numero' => (string) fake()->numberBetween(1, 30),
            'departamento' => fake()->state(),
            'municipio' => fake()->city(),
            'direccion' => fake()->address(),
            'source' => 'live',
            'campaign_id' => null,
        ];
    }
}
```

In `app/Services/PollingPlaceResolver.php`: add `use App\Models\RegistraduriaLookup;` (alphabetical position, after `PollingPlaceResolution`, before `Voter`). Add two new public methods (place directly after `resolveFromNationalSnapshot()`):
```php
/**
 * Resolve from the permanent Registraduría lookup table (persisted results from any
 * genuine past live lookup — admin interactive flow or headless reconciliation).
 * Checked before the interactive live modal (replaces the old 30-day cache, D-01 layer 1)
 * and before the automated cascade's live attempt, so a cédula already resolved once
 * never re-pays for a live/2captcha lookup. Treated as PollingPlaceSource::LIVE because
 * every row in this table originated from a genuine live result — more authoritative
 * than a CensusRecord match, mirroring resolveFromNationalSnapshot()'s shape exactly.
 */
public function resolveFromPermanentLookup(string $cedula): ?PollingPlaceResolutionResult
{
    $lookup = RegistraduriaLookup::query()->where('document_number', $cedula)->first();

    if (! $lookup) {
        return null;
    }

    $fields = $lookup->toRegistraduriaFields();

    return new PollingPlaceResolutionResult(
        source: PollingPlaceSource::LIVE,
        fields: $fields,
        pollingPlaceId: $this->resolveOrCreatePollingPlace($fields)?->id,
        tableNumber: ltrim($fields['mesa_numero'], '0') ?: null,
    );
}

/**
 * Upsert a genuine live Registraduría result into the permanent lookup table, keyed by
 * document_number. Called by every point that produces a real live result (the
 * interactive admin modal's handleRegistraduriaResult() and this class's own
 * resolveAutomated()) so the whole system stops re-paying for a cédula already resolved.
 *
 * @param  array<string, string>  $fields  Raw Registraduría fields (RegistraduriaService shape)
 */
public function persistPermanentLookup(string $cedula, array $fields, ?int $campaignId = null): void
{
    RegistraduriaLookup::updateOrCreate(
        ['document_number' => $cedula],
        [
            'puesto_nombre' => $fields['puesto_nombre'] ?? null,
            'puesto_codigo' => $fields['puesto_codigo'] ?? null,
            'zona_codigo' => $fields['zona_codigo'] ?? null,
            'mesa_numero' => $fields['mesa_numero'] ?? null,
            'departamento' => $fields['departamento'] ?? null,
            'municipio' => $fields['municipio'] ?? null,
            'direccion' => $fields['direccion'] ?? null,
            'source' => PollingPlaceSource::LIVE->value,
            'campaign_id' => $campaignId,
        ]
    );
}
```

Update `resolveAutomated()` to check the permanent table FIRST, and to persist a fresh live success into it:
```php
public function resolveAutomated(string $cedula, Voter $voter, string $resolvedVia = 'reconciliation'): ?PollingPlaceResolutionResult
{
    if ($fromPermanentLookup = $this->resolveFromPermanentLookup($cedula)) {
        return $this->persist($voter, $fromPermanentLookup, isExplicitOverride: false, resolvedVia: $resolvedVia);
    }

    foreach ($this->liveAdapters as $adapter) {
        if ($fields = $this->attemptLiveAutomated($adapter, $cedula)) {
            $this->persistPermanentLookup($cedula, $fields, $voter->campaign_id);

            $result = new PollingPlaceResolutionResult(
                source: PollingPlaceSource::LIVE,
                fields: $fields,
                pollingPlaceId: $this->resolveOrCreatePollingPlace($fields)?->id,
                tableNumber: ltrim($fields['mesa_numero'] ?? '', '0') ?: null,
            );

            return $this->persist($voter, $result, isExplicitOverride: false, resolvedVia: $resolvedVia);
        }
    }

    if ($fromSnapshot = $this->resolveFromNationalSnapshot($cedula)) {
        return $this->persist($voter, $fromSnapshot, isExplicitOverride: false, resolvedVia: $resolvedVia);
    }

    return null;
}
```

Add tests to `tests/Feature/Services/PollingPlaceResolverTest.php` (add `use App\Models\RegistraduriaLookup;` to its imports) covering all 6 `<behavior>` items above. For the "resolveAutomated returns the permanent-table result without calling startLookup" test, build a `LiveSourceAdapter` anonymous class whose `startLookup()` fails the test if invoked (`$this->fail(...)` or a boolean flag assertion), mirroring Test 16's `$startLookupCalled` pattern already in this file.
  </action>
  <verify>
    <automated>php artisan migrate --no-interaction && php artisan test --filter=PollingPlaceResolverTest</automated>
  </verify>
  <done>registraduria_lookups table + RegistraduriaLookup model + factory exist; PollingPlaceResolver::resolveFromPermanentLookup()/persistPermanentLookup() work as specified; resolveAutomated() checks the permanent table before any live adapter call and persists fresh live successes into it. All new Pest tests pass.</done>
</task>

<task type="auto">
  <name>Task 2: VoterStatus::VERIFIED_REGISTRADURIA case, wired into the Filament Voters table</name>
  <files>
    app/Enums/VoterStatus.php
    app/Filament/Resources/Voters/Tables/VotersTable.php
    tests/Feature/Filament/VoterResourceTest.php
  </files>
  <action>
In `app/Enums/VoterStatus.php`, add `case VERIFIED_REGISTRADURIA = 'verified_registraduria';` directly after `VERIFIED_CENSUS` (its semantic sibling — distinct and stronger). Add a matching arm to ALL FOUR exhaustive `match()` methods:
- `getLabel()`: `self::VERIFIED_REGISTRADURIA => 'Verificado por Registraduría',`
- `getColor()`: `self::VERIFIED_REGISTRADURIA => 'success',` (stronger confidence signal than VERIFIED_CENSUS's 'info')
- `getIcon()`: `self::VERIFIED_REGISTRADURIA => 'heroicon-m-shield-check',`
- `getDescription()`: `self::VERIFIED_REGISTRADURIA => 'El apoyo fue verificado directamente contra un resultado en vivo de la Registraduría — la fuente más confiable disponible, más fuerte que el censo local',`

In `app/Filament/Resources/Voters/Tables/VotersTable.php`, add a matching arm to the status column's own separate `->color(fn (VoterStatus $state): string => match ($state) { ... })` closure: `VoterStatus::VERIFIED_REGISTRADURIA => 'success',` placed directly after `VoterStatus::VERIFIED_CENSUS => 'info',`.

In `tests/Feature/Filament/VoterResourceTest.php`, add two tests near the existing CENSUS_NOT_FOUND tests (mirror their structure exactly): one asserting `Livewire::test(ListVoters::class)->assertSuccessful()->assertCanSeeTableRecords([$voter])` for a voter with `status => VoterStatus::VERIFIED_REGISTRADURIA`, and one asserting the status filter correctly isolates VERIFIED_REGISTRADURIA voters from VERIFIED_CENSUS voters.
  </action>
  <verify>
    <automated>php artisan test --filter=VoterResourceTest</automated>
  </verify>
  <done>VERIFIED_REGISTRADURIA exists with all four match arms in both VoterStatus.php and VotersTable.php's color closure; both new Pest tests pass, proving the table renders and filters the new status without an UnhandledMatchError.</done>
</task>

<task type="auto" tdd="true">
  <name>Task 3: HasRegistraduriaPolling — replace the 30-day cache with the permanent lookup table</name>
  <files>
    app/Filament/Resources/Voters/Concerns/HasRegistraduriaPolling.php
    tests/Feature/Filament/VoterRegistraduriaRefreshTest.php
  </files>
  <behavior>
    - `openRegistraduriaBrowser()` on a cédula already present in the permanent table applies the resolved fields and sends a success notification WITHOUT calling `startLookup()` on any live adapter, even when a `CensusRecord` for the same cédula would also resolve.
    - The pre-existing "forces a fresh 2captcha lookup even when the cache is warm" behavior (`forceRefreshFromRegistraduria()` bypassing every tier) still holds, now warmed via a `RegistraduriaLookup` row instead of `Cache::put`.
    - The pre-existing "never mislabels a DB-reconstruction result as LIVE" regression still holds: a DB-reconstruction resolution never writes a row into `registraduria_lookups` (only genuine LIVE results do).
  </behavior>
  <action>
In `app/Filament/Resources/Voters/Concerns/HasRegistraduriaPolling.php`:
1. Remove `use Illuminate\Support\Facades\Cache;`, the `CACHE_TTL_DAYS` constant, and the `registraduriaCacheKey()` private method entirely.
2. In `openRegistraduriaBrowser()`, replace the "Layer 1: Redis cache" block with:
```php
// Layer 1: permanent Registraduría lookup table (replaces the old 30-day Redis-backed
// cache — same invariant holds: only ever written by a genuine live result, either here
// via handleRegistraduriaResult() or headlessly via PollingPlaceResolver::resolveAutomated(),
// so a hit is always safely LIVE-sourced.
$permanentResult = $resolver->resolveFromPermanentLookup($cedula);
if ($permanentResult) {
    $this->applyResolvedFields($permanentResult);
    Notification::make()
        ->title('Puesto de votación (ya verificado por Registraduría)')
        ->body("Puesto: {$permanentResult->fields['puesto_nombre']} — Mesa: {$permanentResult->fields['mesa_numero']}")
        ->success()
        ->send();

    return;
}
```
3. In `handleRegistraduriaResult()`, replace the `Cache::put(...)` block (still after `applyResolvedFields()`, before the final success notification) with:
```php
// Persist the result to the permanent Registraduría lookup table so the next lookup
// for this cédula is instant (no 2captcha cost) — replaces the old 30-day cache write.
// This is the ONLY interactive-flow place that writes here — a genuine live-sourced
// result — which is what keeps openRegistraduriaBrowser()'s permanent-table branch
// above always safely LIVE-sourced.
$cedula = $this->data['document_number'] ?? null;
if ($cedula) {
    app(PollingPlaceResolver::class)->persistPermanentLookup($cedula, $data, CampaignContext::currentCampaignId());
}
```
(`CampaignContext` is already imported in this file.)

In `tests/Feature/Filament/VoterRegistraduriaRefreshTest.php`:
- Add `use App\Models\RegistraduriaLookup;`.
- In the "forces a fresh 2captcha lookup even when the Redis cache is warm..." test (line ~60), replace the `Cache::put("registraduria:cedula:{$cedula}", [...], now()->addDays(30))` call with `RegistraduriaLookup::factory()->create(['document_number' => $cedula, 'puesto_nombre' => 'CACHED PLACE', 'departamento' => 'SUCRE', 'municipio' => $voter->municipality->name, 'direccion' => 'Calle Falsa 123'])`.
- In the "never mislabels a DB-reconstruction result as LIVE on a later lookup via a stale cache hit" test (line ~334), replace `->and(Cache::get("registraduria:cedula:{$cedula}"))->toBeNull()` with `->and(RegistraduriaLookup::where('document_number', $cedula)->exists())->toBeFalse()`.
- Once both spots are updated, remove `use Illuminate\Support\Facades\Cache;` if no other usage of `Cache::` remains in the file (confirm via grep before removing).
- Add one new test: "resolves from the permanent Registraduría lookup table without opening the live modal when the cédula was already verified" — create a `RegistraduriaLookup::factory()->create(['document_number' => $cedula, 'municipio' => $voter->municipality->name, ...])`, mock the live adapter(s) to assert `shouldNotReceive('startLookup')`, call `openRegistraduriaBrowser($cedula)`, assert the success notification and `$voter->fresh()->polling_place_source === PollingPlaceSource::LIVE`.
  </action>
  <verify>
    <automated>php artisan test --filter=VoterRegistraduriaRefreshTest</automated>
  </verify>
  <done>HasRegistraduriaPolling no longer references Illuminate\Support\Facades\Cache anywhere; permanent-table reads/writes replace it exactly. All pre-existing tests in VoterRegistraduriaRefreshTest.php pass with the updated fixtures, plus the new permanent-table-hit test.</done>
</task>

<task type="auto" tdd="true">
  <name>Task 4: Líder register-voter.blade.php — Registraduría blur cascade, autofill, banner, save status</name>
  <files>
    resources/views/livewire/leader/register-voter.blade.php
    tests/Feature/Leader/RegisterVoterRegistraduriaLookupTest.php
  </files>
  <behavior>
    - Blurring a document number matching a permanent `RegistraduriaLookup` row sets `registraduriaVerified` to true, autofills `polling_place_id`, `polling_table_number`, and `address` from the resolved result, and the green banner text is visible — the amber census warning is NOT shown at the same time.
    - Blurring a document number found only in `CensusRecord` (not in `RegistraduriaLookup`) behaves exactly as before this plan (260726-ifp): no green banner, no amber warning — regression check.
    - Blurring a document number found in neither source shows the amber warning only, no green banner — regression check.
    - Saving with a cédula present in `RegistraduriaLookup` persists the created Voter's `status` as `VoterStatus::VERIFIED_REGISTRADURIA`, computed fresh at save time (not trusting the blur-set property alone, matching the 260726-ifp precedent for a paste-then-submit flow).
    - Saving with a cédula found only in the local census still persists `VoterStatus::PENDING_REVIEW` — regression check.
    - Saving with a cédula found in neither source still persists `VoterStatus::CENSUS_NOT_FOUND` — regression check.
  </behavior>
  <action>
In `resources/views/livewire/leader/register-voter.blade.php`'s Volt class:

1. Add two new imports to the top `use` block: `use App\Models\RegistraduriaLookup;` (alphabetical, after `Neighborhood`, before `PollingPlace`) and `use App\Services\PollingPlaceResolver;` (alphabetical, before `use App\Services\VoterValidationService;`).

2. Add a new public property directly after `public bool $censusNotFoundWarning = false;`:
```php
public bool $registraduriaVerified = false;
```

3. Replace `updatedDocumentNumber()` with:
```php
public function updatedDocumentNumber(): void
{
    $this->censusNotFoundWarning = false;
    $this->registraduriaVerified = false;

    if (! preg_match('/^\d{10}$/', $this->document_number)) {
        return;
    }

    $registraduria = app(PollingPlaceResolver::class)->resolveFromPermanentLookup($this->document_number);

    if ($registraduria) {
        $this->registraduriaVerified = true;
        $this->polling_place_id = $registraduria->pollingPlaceId;
        $this->polling_table_number = $registraduria->tableNumber ? (int) $registraduria->tableNumber : null;

        if (filled($registraduria->fields['direccion'] ?? null)) {
            $this->address = $registraduria->fields['direccion'];
        }

        return;
    }

    if (! $this->campaign_id) {
        return;
    }

    $this->censusNotFoundWarning = ! app(VoterValidationService::class)
        ->documentExistsInCensus($this->campaign_id, $this->document_number);
}
```

4. In `save()`, replace the existing `$foundInCensus = ...` line and the `'status' => ...` line with:
```php
$foundInRegistraduria = RegistraduriaLookup::query()->where('document_number', $this->document_number)->exists();

$foundInCensus = app(VoterValidationService::class)
    ->documentExistsInCensus($campaign->id, $this->document_number);

$status = match (true) {
    $foundInRegistraduria => VoterStatus::VERIFIED_REGISTRADURIA,
    $foundInCensus => VoterStatus::PENDING_REVIEW,
    default => VoterStatus::CENSUS_NOT_FOUND,
};
```
Then change the `Voter::create([...])` call's status line to `'status' => $status,`.

5. In the Blade template, change the existing `@if($censusNotFoundWarning)` banner block to an `@if/@elseif` so the two banners are mutually exclusive (green takes priority, per the locked decision that it REPLACES the census warning, not stacks with it):
```blade
@if($registraduriaVerified)
    <div class="flex items-start gap-2 rounded-lg bg-green-50 p-3 text-sm text-green-800 dark:bg-green-900/20 dark:text-green-300">
        <flux:icon.check-badge class="mt-0.5 h-4 w-4 shrink-0" />
        <span>Verificado por Registraduría — puesto de votación, mesa y dirección autocompletados. Puedes editarlos si es necesario.</span>
    </div>
@elseif($censusNotFoundWarning)
    <div class="flex items-start gap-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
        <flux:icon.exclamation-triangle class="mt-0.5 h-4 w-4 shrink-0" />
        <span>Esta cédula no aparece en el censo actual, revísala.</span>
    </div>
@endif
```

Create `tests/Feature/Leader/RegisterVoterRegistraduriaLookupTest.php` covering the 6 `<behavior>` cases, following `tests/Feature/Leader/RegisterVoterCensusWarningTest.php`'s exact setup conventions (`Role::create` leader/admin, `Municipality`/`Neighborhood`/`Campaign` factories, leader `User` with `campaigns()->attach($campaign)`). IMPORTANT: when creating the `RegistraduriaLookup` fixture, also pre-create the matching `PollingPlace` via `PollingPlace::factory()->create(['municipality_id' => $this->municipality->id, 'name' => '<same puesto_nombre>', 'max_tables' => 20])` BEFORE the lookup row, so `resolveOrCreatePollingPlace()` matches the existing place (its factory default `max_tables` is a random 1-20) instead of creating a fresh one with `max_tables => 0` — a fresh codeless place would otherwise fail `MaxTablesForPollingPlace` validation at save() for any positive mesa number. Use `Volt::test('leader.register-voter')->set('document_number', '...')` to trigger the blur hook; assert via `->get('registraduriaVerified')`/`->get('censusNotFoundWarning')` plus `->assertSee(...)`/`->assertDontSee(...)`. For save-time tests, fill the full required form then `->call('save')->assertHasNoErrors()`, then assert `Voter::where('document_number', '...')->first()->status` against the expected enum case.
  </action>
  <verify>
    <automated>php artisan test --filter=RegisterVoterRegistraduriaLookupTest</automated>
  </verify>
  <done>register-voter.blade.php checks the permanent Registraduría table before the local census on document-number blur, autofills puesto/dirección/mesa, shows the green banner exclusively of the amber one, and persists VERIFIED_REGISTRADURIA/PENDING_REVIEW/CENSUS_NOT_FOUND correctly at save time. All 6 new Pest tests pass, plus the pre-existing RegisterVoterCensusWarningTest.php suite still passes unmodified.</done>
</task>

<task type="auto" tdd="true">
  <name>Task 5: Coordinador create-leader.blade.php — required document_number field + Registraduría cascade</name>
  <files>
    resources/views/livewire/coordinator/create-leader.blade.php
    tests/Feature/CreateLeaderOtpTest.php
    tests/Feature/Coordinator/CreateLeaderRegistraduriaLookupTest.php
  </files>
  <behavior>
    - Submitting `save()` (after OTP verification) without a `document_number` fails validation on that field and creates no leader User.
    - Submitting `save()` with a `document_number` already used by an existing User fails validation (unique) and creates no leader User.
    - Submitting `save()` with a valid, unique `document_number` persists it onto the created leader User, regardless of whether it matches Registraduría or the census.
    - Blurring a `document_number` matching a permanent `RegistraduriaLookup` row sets `registraduriaVerified` to true and the green banner is visible; the amber census warning is NOT shown at the same time.
    - Blurring a `document_number` found only in the coordinator's active campaign census shows neither banner (parity with the líder form's existing, unchanged behavior).
    - Blurring a `document_number` found in neither source shows the amber warning only, no green banner.
  </behavior>
  <action>
In `resources/views/livewire/coordinator/create-leader.blade.php`'s Volt class:

1. Add two new imports: `use App\Models\RegistraduriaLookup;` (alphabetical, after `use App\Models\Neighborhood;`, before `use App\Models\User;`) and `use App\Services\VoterValidationService;` (alphabetical, after `use App\Services\OtpVerificationService;`).

2. Add a new validated property directly after the `phone` property:
```php
#[Validate('required|string|max:50|unique:users,document_number')]
public string $document_number = '';
```
(Mirrors `App\Filament\Resources\Coordinators\Schemas\CoordinatorForm.php`'s existing convention for User's `document_number` — no digit-count constraint, this project's only precedent for validating this column on `User`.)

3. Add two new banner-state properties directly after `public string $otp_code = '';`:
```php
public bool $registraduriaVerified = false;

public bool $censusNotFoundWarning = false;
```

4. Add a new lifecycle hook (place it directly after `mount()`):
```php
public function updatedDocumentNumber(): void
{
    $this->registraduriaVerified = false;
    $this->censusNotFoundWarning = false;

    if (blank($this->document_number)) {
        return;
    }

    if (RegistraduriaLookup::query()->where('document_number', $this->document_number)->exists()) {
        $this->registraduriaVerified = true;

        return;
    }

    $campaign = $this->resolveActiveCampaign();

    if (! $campaign) {
        return;
    }

    $this->censusNotFoundWarning = ! app(VoterValidationService::class)
        ->documentExistsInCensus($campaign->id, $this->document_number);
}
```
(`resolveActiveCampaign()` is an existing private method on this component — reuse it directly.)

5. In `save()`, add `'document_number' => $this->document_number,` to the `User::create([...])` array (any position — place it directly after `'phone' => $this->phone,`).

6. In the Blade template, add the new field directly after the "Nombre Completo" `<flux:input>` (still inside the "Información Personal" card's `space-y-4` wrapper), plus its banners:
```blade
<flux:input
    wire:model.blur="document_number"
    label="Número de Documento *"
    type="text"
    inputmode="numeric"
    pattern="[0-9]*"
    placeholder="1234567890"
/>

@if($registraduriaVerified)
    <div class="flex items-start gap-2 rounded-lg bg-green-50 p-3 text-sm text-green-800 dark:bg-green-900/20 dark:text-green-300">
        <flux:icon.check-badge class="mt-0.5 h-4 w-4 shrink-0" />
        <span>Verificado por Registraduría.</span>
    </div>
@elseif($censusNotFoundWarning)
    <div class="flex items-start gap-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
        <flux:icon.exclamation-triangle class="mt-0.5 h-4 w-4 shrink-0" />
        <span>Esta cédula no aparece en el censo actual, revísala.</span>
    </div>
@endif
```

In `tests/Feature/CreateLeaderOtpTest.php`: add `->set('document_number', '1102812122')` to the `Volt::test('coordinator.create-leader')` chain in the "sendOtp then verifyOtp with correct code allows save to create the leader with phone persisted" test (the only test whose `save()` call reaches `$this->validate()` successfully today), and extend its final `expect()` to also assert `$leader->document_number === '1102812122'`.

Create `tests/Feature/Coordinator/CreateLeaderRegistraduriaLookupTest.php` covering all 6 `<behavior>` cases, following `tests/Feature/CreateLeaderOtpTest.php`'s `beforeEach` (`RoleSeeder`, coordinator `User` + `Campaign`) for the save-time tests (drive the full `sendOtp`/`verifyOtp`/`save` flow), and a lighter `beforeEach` (no OTP flow needed) for the blur-only banner tests — those can assert directly via `Volt::test('coordinator.create-leader')->set('document_number', '...')->assertSet('registraduriaVerified', ...)`/`assertSet('censusNotFoundWarning', ...)` without touching OTP or `save()`. For the census-only test, create a `CensusRecord::factory()->create(['campaign_id' => $campaign->id, 'document_number' => '...'])` matching the coordinator's campaign (resolved via `resolveActiveCampaign()` — the coordinator's own `campaigns()->first()`).
  </action>
  <verify>
    <automated>php artisan test --filter=CreateLeaderRegistraduriaLookupTest && php artisan test --filter=CreateLeaderOtpTest</automated>
  </verify>
  <done>create-leader.blade.php has a required, unique document_number field persisted onto the created leader; the same Registraduría-table -> census -> warning cascade and green/amber banners as the líder form now apply here too. All 6 new Pest tests pass, and the pre-existing CreateLeaderOtpTest.php suite passes with the added document_number.</done>
</task>

</tasks>

<verification>
Run the full targeted suite one more time to confirm nothing else broke:
php artisan test --filter=PollingPlaceResolverTest
php artisan test --filter=PollingPlaceResolverPriorityTest
php artisan test --filter=VoterResourceTest
php artisan test --filter=VoterRegistraduriaRefreshTest
php artisan test --filter=RegisterVoterRegistraduriaLookupTest
php artisan test --filter=RegisterVoterCensusWarningTest
php artisan test --filter=CreateLeaderRegistraduriaLookupTest
php artisan test --filter=CreateLeaderOtpTest
php artisan test --filter=ReconcileFallbackPollingPlacesTest
vendor/bin/pint --dirty --test
</verification>

<success_criteria>
- A permanent, non-expiring `registraduria_lookups` table replaces the 30-day cache mechanism in `HasRegistraduriaPolling`; no code path reads or writes `Cache::` for Registraduría results anymore.
- `PollingPlaceResolver::resolveAutomated()` (and by extension `ReconcileFallbackPollingPlaces`) checks the permanent table before any live adapter attempt.
- Every genuine live Registraduría success — admin interactive flow or headless reconciliation — is persisted into the permanent table.
- Líder's register-voter form and Coordinador's create-leader form both check the permanent table first, then the local census, then fall back to the existing amber warning; a Registraduría match always wins the banner and (for Voter only) the saved status.
- `VoterStatus::VERIFIED_REGISTRADURIA` exists, renders, and filters correctly in the admin Voters table.
- Coordinador's create-leader form gains a required + unique `document_number` field, persisted onto the created leader — no User/Voter deduplication or anonymous-identifier logic introduced anywhere (explicitly deferred).
- No campaign-scoping added to registraduria_lookups reads (cross-campaign global data, matching the prior cache-key precedent).
</success_criteria>

<output>
After completion, create `.planning/quick/260726-jao-tabla-permanente-de-resultados-de-regist/260726-jao-SUMMARY.md`
</output>

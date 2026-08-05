<?php

declare(strict_types=1);

use App\Enums\PollingPlaceSource;
use App\Enums\VoterStatus;
use App\Models\CensusRecord;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\NationalCensusRecord;
use App\Models\NationalIdentityRecord;
use App\Models\PollingPlace;
use App\Models\RegistraduriaLookup;
use App\Models\User;
use App\Models\ValidationHistory;
use App\Models\Voter;
use App\Services\VoterValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->validator = User::factory()->create();
    $this->service = new VoterValidationService;

    $this->department = Department::factory()->create(['name' => 'SUCRE']);
    $this->municipality = Municipality::factory()->create([
        'name' => 'SINCELEJO',
        'department_id' => $this->department->id,
    ]);
    $this->pollingPlace = PollingPlace::factory()->create([
        'department_id' => $this->department->id,
        'municipality_id' => $this->municipality->id,
    ]);
});

test('a cedula in registraduria_lookups (tier 0) resolves both census status AND polling_place_source in one pass', function () {
    RegistraduriaLookup::factory()->create([
        'document_number' => '1000000001',
        'puesto_nombre' => $this->pollingPlace->name,
        'puesto_codigo' => '',
        'zona_codigo' => '',
        'mesa_numero' => '05',
        'departamento' => $this->department->name,
        'municipio' => $this->municipality->name,
        'direccion' => $this->pollingPlace->address,
    ]);

    $voter = Voter::factory()->create([
        'document_number' => '1000000001',
        'status' => VoterStatus::PENDING_REVIEW,
        'polling_place_source' => null,
    ]);

    actingAs($this->validator);

    $result = $this->service->validateAndUpdate($voter);

    expect($result['found'])->toBeTrue()
        ->and($result['voter']->status)->toBe(VoterStatus::VERIFIED_CENSUS)
        ->and($result['voter']->polling_place_source)->not->toBeNull()
        ->and($result['voter']->polling_place_source)->toBe(PollingPlaceSource::LIVE);
});

test('a cedula resolvable only via the national census snapshot resolves VERIFIED_CENSUS with polling_place_source = SNAPSHOT', function () {
    NationalCensusRecord::factory()->create([
        'document_number' => '1000000002',
        'polling_place_id' => $this->pollingPlace->id,
    ]);

    $voter = Voter::factory()->create([
        'document_number' => '1000000002',
        'status' => VoterStatus::PENDING_REVIEW,
        'polling_place_source' => null,
    ]);

    actingAs($this->validator);

    $result = $this->service->validateAndUpdate($voter);

    expect($result['found'])->toBeTrue()
        ->and($result['voter']->status)->toBe(VoterStatus::VERIFIED_CENSUS)
        ->and($result['voter']->polling_place_source)->toBe(PollingPlaceSource::SNAPSHOT);
});

test('a cedula not resolvable to a polling place but present in national_identity_records counts as census-found', function () {
    NationalIdentityRecord::factory()->create([
        'cedula' => '1000000003',
    ]);

    $voter = Voter::factory()->create([
        'document_number' => '1000000003',
        'status' => VoterStatus::PENDING_REVIEW,
        'polling_place_source' => null,
    ]);

    actingAs($this->validator);

    $result = $this->service->validateAndUpdate($voter);

    expect($result['found'])->toBeTrue()
        ->and($result['voter']->status)->toBe(VoterStatus::VERIFIED_CENSUS)
        ->and($result['voter']->polling_place_source)->toBeNull();
});

test('a cedula absent from every tier and from national_identity_records lands on census_not_found, not the dead-end rejected_census', function () {
    $voter = Voter::factory()->create([
        'document_number' => '1000000004',
        'status' => VoterStatus::PENDING_REVIEW,
        'polling_place_source' => null,
    ]);

    actingAs($this->validator);

    $result = $this->service->validateAndUpdate($voter);

    expect($result['found'])->toBeFalse()
        ->and($result['voter']->status)->toBe(VoterStatus::CENSUS_NOT_FOUND)
        ->and($result['voter']->status)->not->toBe(VoterStatus::REJECTED_CENSUS);
});

test('validateAndUpdate no longer queries census_records — an existing row for a DIFFERENT cedula never flips an unresolvable voter to found', function () {
    CensusRecord::factory()->create([
        'document_number' => '9999999999',
    ]);

    $voter = Voter::factory()->create([
        'document_number' => '1000000005',
        'status' => VoterStatus::PENDING_REVIEW,
        'polling_place_source' => null,
    ]);

    actingAs($this->validator);

    $result = $this->service->validateAndUpdate($voter);

    expect($result['found'])->toBeFalse()
        ->and($result['voter']->status)->toBe(VoterStatus::CENSUS_NOT_FOUND);
});

test('a ValidationHistory row is written on status change with validation_type census', function () {
    RegistraduriaLookup::factory()->create([
        'document_number' => '1000000006',
    ]);

    $voter = Voter::factory()->create([
        'document_number' => '1000000006',
        'status' => VoterStatus::PENDING_REVIEW,
    ]);

    actingAs($this->validator);

    $this->service->validateAndUpdate($voter);

    $history = ValidationHistory::where('voter_id', $voter->id)->latest()->first();

    expect($history)->not->toBeNull()
        ->and($history->validation_type)->toBe('census')
        ->and($history->previous_status)->toBe(VoterStatus::PENDING_REVIEW)
        ->and($history->new_status)->toBe(VoterStatus::VERIFIED_CENSUS)
        ->and($history->validated_by)->toBe($this->validator->id);
});

// ============ No-downgrade guard: census validation must never clobber a stronger,
// post-verification/Day-D operational status, whether found or not found ============

test('census validation never downgrades a voter already in a protected post-verification status', function (VoterStatus $protectedStatus) {
    $voter = Voter::factory()->create([
        'document_number' => '9999999998',
        'status' => $protectedStatus,
        'polling_place_source' => null,
    ]);

    actingAs($this->validator);

    $countBefore = ValidationHistory::count();

    $updated = $this->service->updateVoterStatus($voter, false);

    expect($updated->status)->toBe($protectedStatus)
        ->and(ValidationHistory::count())->toBe($countBefore);
})->with([
    VoterStatus::VERIFIED_REGISTRADURIA,
    VoterStatus::VERIFIED_CALL,
    VoterStatus::CONFIRMED,
    VoterStatus::VOTED,
    VoterStatus::DID_NOT_VOTE,
    VoterStatus::DUPLICATE,
    VoterStatus::CORRECTION_REQUIRED,
]);

test('census validation still updates a voter in a non-protected status (e.g. pending_review)', function () {
    $voter = Voter::factory()->create([
        'document_number' => '9999999997',
        'status' => VoterStatus::PENDING_REVIEW,
        'polling_place_source' => null,
    ]);

    actingAs($this->validator);

    $updated = $this->service->updateVoterStatus($voter, true);

    expect($updated->status)->toBe(VoterStatus::VERIFIED_CENSUS);
});

<?php

use App\Enums\ElectionType;
use App\Enums\VoterStatus;
use App\Jobs\ReconcileVoterTerritory;
use App\Models\Campaign;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\ValidationHistory;
use App\Models\Voter;
use App\Services\VoterTerritoryScope;
use App\Services\VoterValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->territoryScope = app(VoterTerritoryScope::class);
    $this->validationService = app(VoterValidationService::class);
});

test('marks a voter outside a Municipal-scope campaign as REJECTED_OUT_OF_SCOPE with a ValidationHistory row', function () {
    $department = Department::factory()->create();
    $insideMunicipality = Municipality::factory()->create(['department_id' => $department->id]);
    $outsideMunicipality = Municipality::factory()->create(['department_id' => $department->id]);

    $campaign = Campaign::factory()->create([
        'election_type' => ElectionType::MAYOR,
        'department_id' => $department->id,
        'municipality_id' => $insideMunicipality->id,
    ]);

    $voter = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'municipality_id' => $outsideMunicipality->id,
        'status' => VoterStatus::PENDING_REVIEW,
    ]);

    (new ReconcileVoterTerritory)->handle($this->territoryScope, $this->validationService);

    $fresh = $voter->fresh();

    expect($fresh->status)->toBe(VoterStatus::REJECTED_OUT_OF_SCOPE);

    $history = ValidationHistory::where('voter_id', $voter->id)->latest()->first();

    expect($history)->not->toBeNull()
        ->and($history->previous_status)->toBe(VoterStatus::PENDING_REVIEW)
        ->and($history->new_status)->toBe(VoterStatus::REJECTED_OUT_OF_SCOPE)
        ->and($history->validation_type)->toBe('territory');
});

test('never touches a protected-status voter even when outside territory', function (VoterStatus $protectedStatus) {
    $department = Department::factory()->create();
    $insideMunicipality = Municipality::factory()->create(['department_id' => $department->id]);
    $outsideMunicipality = Municipality::factory()->create(['department_id' => $department->id]);

    $campaign = Campaign::factory()->create([
        'election_type' => ElectionType::MAYOR,
        'department_id' => $department->id,
        'municipality_id' => $insideMunicipality->id,
    ]);

    $voter = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'municipality_id' => $outsideMunicipality->id,
        'status' => $protectedStatus,
    ]);

    (new ReconcileVoterTerritory)->handle($this->territoryScope, $this->validationService);

    $fresh = $voter->fresh();

    expect($fresh->status)->toBe($protectedStatus)
        ->and(ValidationHistory::where('voter_id', $voter->id)->exists())->toBeFalse();
})->with([
    VoterStatus::VERIFIED_REGISTRADURIA,
    VoterStatus::VERIFIED_CALL,
    VoterStatus::CONFIRMED,
    VoterStatus::VOTED,
    VoterStatus::DID_NOT_VOTE,
    VoterStatus::DUPLICATE,
    VoterStatus::CORRECTION_REQUIRED,
]);

test('reverts a REJECTED_OUT_OF_SCOPE voter back to PENDING_REVIEW when back in scope', function () {
    $department = Department::factory()->create();
    $municipality = Municipality::factory()->create(['department_id' => $department->id]);

    $campaign = Campaign::factory()->create([
        'election_type' => ElectionType::MAYOR,
        'department_id' => $department->id,
        'municipality_id' => $municipality->id,
    ]);

    $voter = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'municipality_id' => $municipality->id,
        'status' => VoterStatus::REJECTED_OUT_OF_SCOPE,
    ]);

    (new ReconcileVoterTerritory)->handle($this->territoryScope, $this->validationService);

    $fresh = $voter->fresh();

    expect($fresh->status)->toBe(VoterStatus::PENDING_REVIEW);

    $history = ValidationHistory::where('voter_id', $voter->id)->latest()->first();

    expect($history)->not->toBeNull()
        ->and($history->previous_status)->toBe(VoterStatus::REJECTED_OUT_OF_SCOPE)
        ->and($history->new_status)->toBe(VoterStatus::PENDING_REVIEW)
        ->and($history->validation_type)->toBe('territory');
});

test('ignores voters in a Nacional-scope campaign', function () {
    $campaign = Campaign::factory()->create([
        'election_type' => ElectionType::PRESIDENT,
    ]);

    $voter = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => VoterStatus::PENDING_REVIEW,
    ]);

    (new ReconcileVoterTerritory)->handle($this->territoryScope, $this->validationService);

    $fresh = $voter->fresh();

    expect($fresh->status)->toBe(VoterStatus::PENDING_REVIEW)
        ->and(ValidationHistory::where('voter_id', $voter->id)->exists())->toBeFalse();
});

test('ignores voters in a Regional-scope campaign with no territory defined', function () {
    $campaign = Campaign::factory()->create([
        'election_type' => ElectionType::OTHER,
        'department_id' => null,
        'municipality_id' => null,
    ]);

    $voter = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => VoterStatus::PENDING_REVIEW,
    ]);

    (new ReconcileVoterTerritory)->handle($this->territoryScope, $this->validationService);

    $fresh = $voter->fresh();

    expect($fresh->status)->toBe(VoterStatus::PENDING_REVIEW)
        ->and(ValidationHistory::where('voter_id', $voter->id)->exists())->toBeFalse();
});

test('never processes more than 50 voters in a single run', function () {
    $department = Department::factory()->create();
    $insideMunicipality = Municipality::factory()->create(['department_id' => $department->id]);
    $outsideMunicipality = Municipality::factory()->create(['department_id' => $department->id]);

    $campaign = Campaign::factory()->create([
        'election_type' => ElectionType::MAYOR,
        'department_id' => $department->id,
        'municipality_id' => $insideMunicipality->id,
    ]);

    $voters = Voter::factory()->count(51)->create([
        'campaign_id' => $campaign->id,
        'municipality_id' => $outsideMunicipality->id,
        'status' => VoterStatus::PENDING_REVIEW,
    ]);

    (new ReconcileVoterTerritory)->handle($this->territoryScope, $this->validationService);

    $touched = Voter::whereIn('id', $voters->pluck('id'))
        ->where('status', VoterStatus::REJECTED_OUT_OF_SCOPE->value)
        ->count();

    expect($touched)->toBe(50);
});

test('census:reconcile-territory command dispatches the job', function () {
    Bus::fake();

    Artisan::call('census:reconcile-territory');

    Bus::assertDispatched(ReconcileVoterTerritory::class);
});

test('census:reconcile-territory is scheduled hourly with a 10-minute withoutOverlapping lock', function () {
    $consoleRoutes = file_get_contents(base_path('routes/console.php'));

    expect($consoleRoutes)->toContain("Schedule::command('census:reconcile-territory')->hourly()->withoutOverlapping(10)");
});

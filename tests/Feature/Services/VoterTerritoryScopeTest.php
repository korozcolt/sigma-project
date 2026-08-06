<?php

use App\Enums\ElectionType;
use App\Models\Campaign;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\PollingPlace;
use App\Models\Voter;
use App\Services\VoterTerritoryScope;

beforeEach(function () {
    $this->territoryScope = new VoterTerritoryScope;
});

test('isWithinCampaignScope returns true for a voter inside a Municipal-scope campaign', function () {
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
    ]);

    expect($this->territoryScope->isWithinCampaignScope($voter, $campaign))->toBeTrue();
});

test('isWithinCampaignScope returns false for a voter outside a Municipal-scope campaign', function () {
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
    ]);

    expect($this->territoryScope->isWithinCampaignScope($voter, $campaign))->toBeFalse();
});

test('isWithinCampaignScope returns true for a voter inside a Departamental-scope campaign', function () {
    $department = Department::factory()->create();
    $municipality = Municipality::factory()->create(['department_id' => $department->id]);

    $campaign = Campaign::factory()->create([
        'election_type' => ElectionType::GOVERNOR,
        'department_id' => $department->id,
    ]);

    $voter = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'municipality_id' => $municipality->id,
    ]);

    expect($this->territoryScope->isWithinCampaignScope($voter, $campaign))->toBeTrue();
});

test('isWithinCampaignScope returns false for a voter outside a Departamental-scope campaign', function () {
    $insideDepartment = Department::factory()->create();
    $outsideDepartment = Department::factory()->create();
    $outsideMunicipality = Municipality::factory()->create(['department_id' => $outsideDepartment->id]);

    $campaign = Campaign::factory()->create([
        'election_type' => ElectionType::GOVERNOR,
        'department_id' => $insideDepartment->id,
    ]);

    $voter = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'municipality_id' => $outsideMunicipality->id,
    ]);

    expect($this->territoryScope->isWithinCampaignScope($voter, $campaign))->toBeFalse();
});

test('isWithinCampaignScope always returns true (not-applicable) for a Nacional-scope campaign', function () {
    $campaign = Campaign::factory()->create([
        'election_type' => ElectionType::PRESIDENT,
    ]);

    $voter = Voter::factory()->create(['campaign_id' => $campaign->id]);

    expect($this->territoryScope->isWithinCampaignScope($voter, $campaign))->toBeTrue();
});

test('isWithinCampaignScope always returns true (not-applicable) for a Regional-scope campaign with no territory', function () {
    // ElectionType::OTHER->scope() === CampaignScope::Regional, the one scope Campaign::boot()
    // does not auto-null — used here to construct a true both-null state.
    $campaign = Campaign::factory()->create([
        'election_type' => ElectionType::OTHER,
        'department_id' => null,
        'municipality_id' => null,
    ]);

    $voter = Voter::factory()->create(['campaign_id' => $campaign->id]);

    expect($this->territoryScope->isWithinCampaignScope($voter, $campaign))->toBeTrue();
});

test('isTerritoryDefined is false for a Nacional-scope campaign', function () {
    $campaign = Campaign::factory()->create([
        'election_type' => ElectionType::PRESIDENT,
    ]);

    expect($this->territoryScope->isTerritoryDefined($campaign))->toBeFalse();
});

test('isTerritoryDefined is false for a Regional-scope campaign with no territory', function () {
    $campaign = Campaign::factory()->create([
        'election_type' => ElectionType::OTHER,
        'department_id' => null,
        'municipality_id' => null,
    ]);

    expect($this->territoryScope->isTerritoryDefined($campaign))->toBeFalse();
});

test('isTerritoryDefined is true for a Municipal-scope campaign', function () {
    $department = Department::factory()->create();
    $municipality = Municipality::factory()->create(['department_id' => $department->id]);

    $campaign = Campaign::factory()->create([
        'election_type' => ElectionType::MAYOR,
        'department_id' => $department->id,
        'municipality_id' => $municipality->id,
    ]);

    expect($this->territoryScope->isTerritoryDefined($campaign))->toBeTrue();
});

test('isTerritoryDefined is true for a Departamental-scope campaign', function () {
    $department = Department::factory()->create();

    $campaign = Campaign::factory()->create([
        'election_type' => ElectionType::GOVERNOR,
        'department_id' => $department->id,
    ]);

    expect($this->territoryScope->isTerritoryDefined($campaign))->toBeTrue();
});

test('isWithinCampaignScope falls back to voter.municipality_id when no polling place is resolved', function () {
    $department = Department::factory()->create();
    $insideMunicipality = Municipality::factory()->create(['department_id' => $department->id]);

    $campaign = Campaign::factory()->create([
        'election_type' => ElectionType::MAYOR,
        'department_id' => $department->id,
        'municipality_id' => $insideMunicipality->id,
    ]);

    $voter = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'municipality_id' => $insideMunicipality->id,
        'polling_place_id' => null,
    ]);

    expect($this->territoryScope->isWithinCampaignScope($voter, $campaign))->toBeTrue();
});

test('isWithinCampaignScope prefers the resolved polling place municipality over voter.municipality_id (Municipal scope)', function () {
    $department = Department::factory()->create();
    $campaignMunicipality = Municipality::factory()->create(['department_id' => $department->id]);
    $realMunicipality = Municipality::factory()->create(['department_id' => $department->id]);

    $campaign = Campaign::factory()->create([
        'election_type' => ElectionType::MAYOR,
        'department_id' => $department->id,
        'municipality_id' => $campaignMunicipality->id,
    ]);

    $pollingPlace = PollingPlace::factory()->create([
        'department_id' => $department->id,
        'municipality_id' => $realMunicipality->id,
    ]);

    // voter.municipality_id was defaulted to the campaign's own municipality at creation time
    // (per register-voter.blade.php mount()), but the resolved polling place disagrees.
    $voter = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'municipality_id' => $campaignMunicipality->id,
        'polling_place_id' => $pollingPlace->id,
    ]);

    expect($this->territoryScope->isWithinCampaignScope($voter, $campaign))->toBeFalse();
});

test('isWithinCampaignScope prefers the resolved polling place department over voter.municipality_id (Departamental scope)', function () {
    $insideDepartment = Department::factory()->create();
    $outsideDepartment = Department::factory()->create();

    // Voter's own stored municipality_id is inside the campaign's department...
    $voterMunicipality = Municipality::factory()->create(['department_id' => $insideDepartment->id]);
    // ...but the resolved polling place's municipality belongs to a different department entirely.
    $realMunicipality = Municipality::factory()->create(['department_id' => $outsideDepartment->id]);

    $campaign = Campaign::factory()->create([
        'election_type' => ElectionType::GOVERNOR,
        'department_id' => $insideDepartment->id,
    ]);

    $pollingPlace = PollingPlace::factory()->create([
        'department_id' => $outsideDepartment->id,
        'municipality_id' => $realMunicipality->id,
    ]);

    $voter = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'municipality_id' => $voterMunicipality->id,
        'polling_place_id' => $pollingPlace->id,
    ]);

    expect($this->territoryScope->isWithinCampaignScope($voter, $campaign))->toBeFalse();
});

test('isWithinCampaignScope returns true when the resolved polling place agrees with voter.municipality_id', function () {
    $department = Department::factory()->create();
    $municipality = Municipality::factory()->create(['department_id' => $department->id]);

    $campaign = Campaign::factory()->create([
        'election_type' => ElectionType::MAYOR,
        'department_id' => $department->id,
        'municipality_id' => $municipality->id,
    ]);

    $pollingPlace = PollingPlace::factory()->create([
        'department_id' => $department->id,
        'municipality_id' => $municipality->id,
    ]);

    $voter = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'municipality_id' => $municipality->id,
        'polling_place_id' => $pollingPlace->id,
    ]);

    expect($this->territoryScope->isWithinCampaignScope($voter, $campaign))->toBeTrue();
});

test('isTerritoryDefined is true for a Regional-scope campaign that does have a territory set', function () {
    $department = Department::factory()->create();

    $campaign = Campaign::factory()->create([
        'election_type' => ElectionType::OTHER,
        'department_id' => $department->id,
        'municipality_id' => null,
    ]);

    expect($this->territoryScope->isTerritoryDefined($campaign))->toBeTrue();
});

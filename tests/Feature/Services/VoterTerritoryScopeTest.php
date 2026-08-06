<?php

use App\Enums\ElectionType;
use App\Models\Campaign;
use App\Models\Department;
use App\Models\Municipality;
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

test('isTerritoryDefined is true for a Regional-scope campaign that does have a territory set', function () {
    $department = Department::factory()->create();

    $campaign = Campaign::factory()->create([
        'election_type' => ElectionType::OTHER,
        'department_id' => $department->id,
        'municipality_id' => null,
    ]);

    expect($this->territoryScope->isTerritoryDefined($campaign))->toBeTrue();
});

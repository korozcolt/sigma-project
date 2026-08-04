<?php

use App\Enums\UserRole;
use App\Exports\TopLeadersExport;
use App\Models\Campaign;
use App\Models\Municipality;
use App\Models\User;
use App\Models\Voter;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

    $this->municipality = Municipality::factory()->create();
    $this->campaign = Campaign::factory()->create(['municipality_id' => $this->municipality->id]);

    $this->coordinator = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $this->coordinator->assignRole(UserRole::COORDINATOR->value);
    $this->coordinator->campaigns()->attach($this->campaign->id);

    $this->ownLeader = User::factory()->create([
        'municipality_id' => $this->municipality->id,
        'coordinator_user_id' => $this->coordinator->id,
    ]);
    $this->ownLeader->assignRole(UserRole::LEADER->value);
    $this->ownLeader->campaigns()->attach($this->campaign->id);
    Voter::factory()->create(['campaign_id' => $this->campaign->id, 'registered_by' => $this->ownLeader->id]);

    $this->otherCoordinator = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $this->otherCoordinator->assignRole(UserRole::COORDINATOR->value);
    $this->otherCoordinator->campaigns()->attach($this->campaign->id);

    $this->otherLeader = User::factory()->create([
        'municipality_id' => $this->municipality->id,
        'coordinator_user_id' => $this->otherCoordinator->id,
    ]);
    $this->otherLeader->assignRole(UserRole::LEADER->value);
    $this->otherLeader->campaigns()->attach($this->campaign->id);
    Voter::factory()->create(['campaign_id' => $this->campaign->id, 'registered_by' => $this->otherLeader->id]);
});

it('excluye del ranking exportado a un líder de otro coordinador del mismo municipio y campaña', function () {
    $this->actingAs($this->coordinator);

    $ids = (new TopLeadersExport($this->campaign->id))->query()->pluck('id');

    expect($ids)->toContain($this->ownLeader->id)
        ->and($ids)->not->toContain($this->otherLeader->id);
});

it('un super_admin ve ambos líderes en el ranking exportado (el filtro es solo para coordinadores)', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(UserRole::SUPER_ADMIN->value);
    $this->actingAs($superAdmin);

    $ids = (new TopLeadersExport($this->campaign->id))->query()->pluck('id');

    expect($ids)->toContain($this->ownLeader->id)
        ->and($ids)->toContain($this->otherLeader->id);
});

<?php

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Municipality;
use App\Models\User;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

    $this->municipality = Municipality::factory()->create();
    $this->campaign = Campaign::factory()->create(['municipality_id' => $this->municipality->id]);

    $this->coordinator = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $this->coordinator->assignRole(UserRole::COORDINATOR->value);
    $this->coordinator->campaigns()->attach($this->campaign->id);

    $this->leader = User::factory()->create([
        'municipality_id' => $this->municipality->id,
        'coordinator_user_id' => $this->coordinator->id,
    ]);
    $this->leader->assignRole(UserRole::LEADER->value);
    $this->leader->campaigns()->attach($this->campaign->id);
});

it('un coordinador puede ver los apoyos de su propio líder', function () {
    $this->actingAs($this->coordinator);

    $response = $this->get(route('coordinator.leaders.voters', $this->leader));

    $response->assertOk();
});

it('devuelve 403 cuando el líder pertenece a otro coordinador del mismo municipio y campaña', function () {
    $otherCoordinator = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $otherCoordinator->assignRole(UserRole::COORDINATOR->value);
    $otherCoordinator->campaigns()->attach($this->campaign->id);

    $otherLeader = User::factory()->create([
        'municipality_id' => $this->municipality->id,
        'coordinator_user_id' => $otherCoordinator->id,
    ]);
    $otherLeader->assignRole(UserRole::LEADER->value);
    $otherLeader->campaigns()->attach($this->campaign->id);

    $this->actingAs($this->coordinator);

    $response = $this->get(route('coordinator.leaders.voters', $otherLeader));

    $response->assertForbidden();
});

<?php

use App\Enums\UserRole;
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

    $this->leader = User::factory()->create([
        'municipality_id' => $this->municipality->id,
        'coordinator_user_id' => $this->coordinator->id,
    ]);
    $this->leader->assignRole(UserRole::LEADER->value);
    $this->leader->campaigns()->attach($this->campaign->id);
    Voter::factory()->count(2)->create(['campaign_id' => $this->campaign->id, 'registered_by' => $this->leader->id]);

    $this->otherCoordinator = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $this->otherCoordinator->assignRole(UserRole::COORDINATOR->value);
    $this->otherCoordinator->campaigns()->attach($this->campaign->id);

    $this->otherLeader = User::factory()->create([
        'municipality_id' => $this->municipality->id,
        'coordinator_user_id' => $this->otherCoordinator->id,
    ]);
    $this->otherLeader->assignRole(UserRole::LEADER->value);
    $this->otherLeader->campaigns()->attach($this->campaign->id);
    Voter::factory()->count(3)->create(['campaign_id' => $this->campaign->id, 'registered_by' => $this->otherLeader->id]);
});

it('el dashboard de un coordinador muestra solo sus propios líderes y apoyos', function () {
    $this->actingAs($this->coordinator);

    $response = $this->get(route('coordinator.dashboard'));

    $response->assertOk()
        ->assertSeeText($this->leader->name)
        ->assertSeeText('2'); // total apoyos propios
});

it('el dashboard de un coordinador NO muestra líderes ni apoyos de otro coordinador del mismo municipio y campaña', function () {
    $this->actingAs($this->coordinator);

    $response = $this->get(route('coordinator.dashboard'));

    $response->assertOk()->assertDontSeeText($this->otherLeader->name);
});

it('un admin_campaign ve líderes de múltiples coordinadores en el dashboard sin restricción', function () {
    $admin = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $admin->assignRole(UserRole::ADMIN_CAMPAIGN->value);
    $admin->campaigns()->attach($this->campaign->id);

    $this->actingAs($admin);

    $response = $this->get(route('coordinator.dashboard'));

    $response->assertOk()
        ->assertSeeText($this->leader->name)
        ->assertSeeText($this->otherLeader->name);
});

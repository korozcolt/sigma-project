<?php

use App\Models\Campaign;
use App\Models\Municipality;
use App\Models\User;
use App\Models\Voter;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'coordinator']);
    Role::firstOrCreate(['name' => 'leader']);

    $this->municipality = Municipality::factory()->create();
    $this->campaign = Campaign::factory()->create();
});

it('allows coordinator to export leaders list', function () {
    $coordinator = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $coordinator->assignRole('coordinator');
    $coordinator->campaigns()->attach($this->campaign);

    $leaderA = User::factory()->create(['municipality_id' => $this->municipality->id, 'name' => 'Ana Lopez', 'email' => 'ana@example.com']);
    $leaderA->assignRole('leader');
    $leaderA->campaigns()->attach($this->campaign);

    $leaderB = User::factory()->create(['municipality_id' => $this->municipality->id, 'name' => 'Carlos Perez', 'email' => 'carlos@example.com']);
    $leaderB->assignRole('leader');
    $leaderB->campaigns()->attach($this->campaign);

    // create voters registered by leaders
    Voter::factory()->count(3)->create(['registered_by' => $leaderA->id, 'campaign_id' => $this->campaign->id]);
    Voter::factory()->count(2)->create(['registered_by' => $leaderB->id, 'campaign_id' => $this->campaign->id]);

    $response = $this->actingAs($coordinator)->get(route('coordinator.leaders.export'));

    $response->assertOk();
    $response->assertHeader('content-disposition');
    $this->assertStringContainsString('lideres.xlsx', $response->headers->get('content-disposition'));
    $this->assertStringContainsString('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('content-type'));
});

it('forbids non-coordinator users from exporting leaders', function () {
    $leader = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $leader->assignRole('leader');
    $leader->campaigns()->attach($this->campaign);

    $response = $this->actingAs($leader)->get(route('coordinator.leaders.export'));

    $response->assertForbidden();
});

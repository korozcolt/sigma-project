<?php

use App\Models\Campaign;
use App\Models\Municipality;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin_campaign']);
    Role::firstOrCreate(['name' => 'coordinator']);

    $this->municipality = Municipality::factory()->create();
    $this->campaign = Campaign::factory()->create();
});

it('allows admin_campaign to export coordinators', function () {
    $admin = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $admin->assignRole('admin_campaign');

    $coordinator = User::factory()->create(['municipality_id' => $this->municipality->id, 'name' => 'Juan Coord']);
    $coordinator->assignRole('coordinator');
    $coordinator->campaigns()->attach($this->campaign);

    $response = $this->actingAs($admin)->get(route('campaign-admin.users.export.coordinators'));

    $response->assertOk();
    $response->assertHeader('content-disposition');
    $this->assertStringContainsString('coordinadores.xlsx', $response->headers->get('content-disposition'));
});

it('forbids non-admin users from exporting coordinators', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('campaign-admin.users.export.coordinators'));

    $response->assertForbidden();
});

it('allows admin_campaign to export witnesses', function () {
    $admin = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $admin->assignRole('admin_campaign');

    $witness = User::factory()->witness('Mesa 001', 50000)->create(['municipality_id' => $this->municipality->id]);

    $response = $this->actingAs($admin)->get(route('campaign-admin.users.export.witnesses'));

    $response->assertOk();
    $response->assertHeader('content-disposition');
    $this->assertStringContainsString('testigos.xlsx', $response->headers->get('content-disposition'));
});

it('allows admin_campaign to export annotators', function () {
    $admin = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $admin->assignRole('admin_campaign');

    $annotator = User::factory()->create(['municipality_id' => $this->municipality->id, 'is_vote_recorder' => true]);

    $response = $this->actingAs($admin)->get(route('campaign-admin.users.export.annotators'));

    $response->assertOk();
    $response->assertHeader('content-disposition');
    $this->assertStringContainsString('anotadores.xlsx', $response->headers->get('content-disposition'));
});

it('forbids non-admin users from exporting witnesses', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('campaign-admin.users.export.witnesses'));

    $response->assertForbidden();
});

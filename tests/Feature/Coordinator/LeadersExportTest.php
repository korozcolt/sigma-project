<?php

use App\Enums\UserRole;
use App\Exports\LeadersExport;
use App\Models\Campaign;
use App\Models\Municipality;
use App\Models\User;
use App\Models\Voter;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'coordinator']);
    Role::firstOrCreate(['name' => 'leader']);
    Role::firstOrCreate(['name' => 'admin_campaign']);

    $this->municipality = Municipality::factory()->create();
    $this->campaign = Campaign::factory()->create();
});

it('allows coordinator to export leaders list', function () {
    $coordinator = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $coordinator->assignRole('coordinator');
    $coordinator->campaigns()->attach($this->campaign);

    $leaderA = User::factory()->create(['municipality_id' => $this->municipality->id, 'name' => 'Ana Lopez', 'email' => 'ana@example.com', 'coordinator_user_id' => $coordinator->id]);
    $leaderA->assignRole('leader');
    $leaderA->campaigns()->attach($this->campaign);

    $leaderB = User::factory()->create(['municipality_id' => $this->municipality->id, 'name' => 'Carlos Perez', 'email' => 'carlos@example.com', 'coordinator_user_id' => $coordinator->id]);
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

it('excluye del export a un líder de otro coordinador del mismo municipio y campaña', function () {
    $coordinator = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $coordinator->assignRole(UserRole::COORDINATOR->value);
    $coordinator->campaigns()->attach($this->campaign->id);

    $ownLeader = User::factory()->create([
        'municipality_id' => $this->municipality->id,
        'coordinator_user_id' => $coordinator->id,
    ]);
    $ownLeader->assignRole(UserRole::LEADER->value);
    $ownLeader->campaigns()->attach($this->campaign->id);

    $otherCoordinator = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $otherCoordinator->assignRole(UserRole::COORDINATOR->value);
    $otherCoordinator->campaigns()->attach($this->campaign->id);

    $otherLeader = User::factory()->create([
        'municipality_id' => $this->municipality->id,
        'coordinator_user_id' => $otherCoordinator->id,
    ]);
    $otherLeader->assignRole(UserRole::LEADER->value);
    $otherLeader->campaigns()->attach($this->campaign->id);

    Excel::fake();

    $this->actingAs($coordinator)->get(route('coordinator.leaders.export'));

    Excel::assertDownloaded('lideres.xlsx', function (LeadersExport $export) use ($ownLeader, $otherLeader) {
        $ids = $export->query()->pluck('id');

        return $ids->contains($ownLeader->id) && ! $ids->contains($otherLeader->id);
    });
});

it('un admin_campaign exporta líderes de múltiples coordinadores sin restricción', function () {
    $adminCampaign = User::factory()->create();
    $adminCampaign->assignRole(UserRole::ADMIN_CAMPAIGN->value);
    $adminCampaign->campaigns()->attach($this->campaign->id);

    $coordinatorOne = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $coordinatorOne->assignRole(UserRole::COORDINATOR->value);
    $coordinatorOne->campaigns()->attach($this->campaign->id);

    $coordinatorTwo = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $coordinatorTwo->assignRole(UserRole::COORDINATOR->value);
    $coordinatorTwo->campaigns()->attach($this->campaign->id);

    $leaderOne = User::factory()->create([
        'municipality_id' => $this->municipality->id,
        'coordinator_user_id' => $coordinatorOne->id,
    ]);
    $leaderOne->assignRole(UserRole::LEADER->value);
    $leaderOne->campaigns()->attach($this->campaign->id);

    $leaderTwo = User::factory()->create([
        'municipality_id' => $this->municipality->id,
        'coordinator_user_id' => $coordinatorTwo->id,
    ]);
    $leaderTwo->assignRole(UserRole::LEADER->value);
    $leaderTwo->campaigns()->attach($this->campaign->id);

    Excel::fake();

    $this->actingAs($adminCampaign)->get(route('coordinator.leaders.export'));

    Excel::assertDownloaded('lideres.xlsx', function (LeadersExport $export) use ($leaderOne, $leaderTwo) {
        $ids = $export->query()->pluck('id');

        return $ids->contains($leaderOne->id) && $ids->contains($leaderTwo->id);
    });
});

<?php

use App\Enums\UserRole;
use App\Exports\LeadersExport;
use App\Exports\TopLeadersExport;
use App\Filament\Widgets\TopLeadersTable;
use App\Http\Controllers\Coordinator\LeadersExportController;
use App\Models\Campaign;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;

uses()->group('articulador-team-resolution');

beforeEach(function () {
    collect(UserRole::values())->each(
        fn ($role) => Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])
    );

    $this->campaignA = Campaign::factory()->create(['status' => 'active']);
    $this->campaignB = Campaign::factory()->create(['status' => 'active']);

    $this->areaCoordinator = User::factory()->create();
    $this->areaCoordinator->assignRole(UserRole::AREA_COORDINATOR->value);
    $this->areaCoordinator->campaigns()->attach($this->campaignA->id);

    $this->coordinatorX = User::factory()->create(['area_coordinator_user_id' => $this->areaCoordinator->id]);
    $this->coordinatorX->assignRole(UserRole::COORDINATOR->value);
    $this->coordinatorX->campaigns()->attach($this->campaignA->id);
    $this->coordinatorX->campaigns()->attach($this->campaignB->id);

    $this->coordinatorY = User::factory()->create();
    $this->coordinatorY->assignRole(UserRole::COORDINATOR->value);
    $this->coordinatorY->campaigns()->attach($this->campaignA->id);

    $this->leaderX1 = User::factory()->create(['coordinator_user_id' => $this->coordinatorX->id]);
    $this->leaderX1->assignRole(UserRole::LEADER->value);
    $this->leaderX1->campaigns()->attach($this->campaignA->id);

    $this->leaderX2 = User::factory()->create(['coordinator_user_id' => $this->coordinatorX->id]);
    $this->leaderX2->assignRole(UserRole::LEADER->value);
    $this->leaderX2->campaigns()->attach($this->campaignA->id);

    // Under coordinatorX (managed by the articulador) but only in Campaign B — proves
    // AUTHZ-03: the articulador's Campaign A context never leaks Campaign B data, even
    // for a leader under a coordinador the articulador legitimately manages.
    $this->leaderX3OtherCampaign = User::factory()->create(['coordinator_user_id' => $this->coordinatorX->id]);
    $this->leaderX3OtherCampaign->assignRole(UserRole::LEADER->value);
    $this->leaderX3OtherCampaign->campaigns()->attach($this->campaignB->id);

    // Under coordinatorY, who is NOT managed by $this->areaCoordinator — proves AUTHZ-01
    // doesn't over-broaden to "every coordinador in the campaign".
    $this->leaderY1 = User::factory()->create(['coordinator_user_id' => $this->coordinatorY->id]);
    $this->leaderY1->assignRole(UserRole::LEADER->value);
    $this->leaderY1->campaigns()->attach($this->campaignA->id);

    Voter::factory()->count(2)->create(['campaign_id' => $this->campaignA->id, 'registered_by' => $this->leaderX1->id]);
    Voter::factory()->count(3)->create(['campaign_id' => $this->campaignA->id, 'registered_by' => $this->leaderX2->id]);
    Voter::factory()->count(1)->create(['campaign_id' => $this->campaignB->id, 'registered_by' => $this->leaderX3OtherCampaign->id]);
    Voter::factory()->count(4)->create(['campaign_id' => $this->campaignA->id, 'registered_by' => $this->leaderY1->id]);
});

function actAsArticuladorInCampaign(User $user, Campaign $campaign): void
{
    test()->actingAs($user);
    Session::put('campaign_context.campaign_id', $campaign->id);
    Session::put('campaign_context.mode', 'single');
}

test('AUTHZ-01: articulador sees their coordinadores transitive team in TopLeadersTable, not other coordinadores leaders', function () {
    actAsArticuladorInCampaign($this->areaCoordinator, $this->campaignA);

    Livewire::test(TopLeadersTable::class)
        ->assertCanSeeTableRecords([$this->leaderX1, $this->leaderX2])
        ->assertCanNotSeeTableRecords([$this->leaderY1, $this->leaderX3OtherCampaign]);
});

test('AUTHZ-01: articulador sees their coordinadores transitive team in TopLeadersExport, not other coordinadores leaders', function () {
    actAsArticuladorInCampaign($this->areaCoordinator, $this->campaignA);

    $ids = (new TopLeadersExport($this->campaignA->id))->query()->pluck('id');

    expect($ids->contains($this->leaderX1->id))->toBeTrue()
        ->and($ids->contains($this->leaderX2->id))->toBeTrue()
        ->and($ids->contains($this->leaderY1->id))->toBeFalse()
        ->and($ids->contains($this->leaderX3OtherCampaign->id))->toBeFalse();
});

test('AUTHZ-01: articulador sees their coordinadores transitive team in LeadersExportController, not other coordinadores leaders', function () {
    Excel::fake();

    $request = Request::create('/coordinator/leaders/export', 'GET');
    // The 'coordinator' route group's role middleware (role:coordinator,admin_campaign,super_admin)
    // intentionally excludes area_coordinator today — wiring articulador into that route/panel is
    // Phase 14/15 scope (13-CONTEXT.md Phase Boundary). Calling the controller directly proves the
    // query itself resolves the transitive team correctly, ahead of that routing work.
    $request->setUserResolver(fn () => $this->areaCoordinator);

    app(LeadersExportController::class)($request);

    $expectedIds = [$this->leaderX1->id, $this->leaderX2->id];
    $excludedIds = [$this->leaderY1->id, $this->leaderX3OtherCampaign->id];

    Excel::assertDownloaded('lideres.xlsx', function (LeadersExport $export) use ($expectedIds, $excludedIds) {
        $ids = $export->query()->pluck('id');

        return collect($expectedIds)->every(fn ($id) => $ids->contains($id))
            && collect($excludedIds)->every(fn ($id) => ! $ids->contains($id));
    });
});

test('an articulador with zero coordinadores sees an empty team across all 3 surfaces', function () {
    $lonelyAreaCoordinator = User::factory()->create();
    $lonelyAreaCoordinator->assignRole(UserRole::AREA_COORDINATOR->value);
    $lonelyAreaCoordinator->campaigns()->attach($this->campaignA->id);

    actAsArticuladorInCampaign($lonelyAreaCoordinator, $this->campaignA);

    Livewire::test(TopLeadersTable::class)
        ->assertCanNotSeeTableRecords([$this->leaderX1, $this->leaderX2, $this->leaderY1]);

    expect((new TopLeadersExport($this->campaignA->id))->query()->pluck('id'))->toBeEmpty();
});

test('coordinador scoping in all 3 surfaces is unchanged after the whereIn refactor (no regression)', function () {
    actAsArticuladorInCampaign($this->coordinatorX, $this->campaignA);

    Livewire::test(TopLeadersTable::class)
        ->assertCanSeeTableRecords([$this->leaderX1, $this->leaderX2])
        ->assertCanNotSeeTableRecords([$this->leaderY1]);

    $exportIds = (new TopLeadersExport($this->campaignA->id))->query()->pluck('id');
    expect($exportIds->contains($this->leaderX1->id))->toBeTrue()
        ->and($exportIds->contains($this->leaderY1->id))->toBeFalse();

    Excel::fake();
    $request = Request::create('/coordinator/leaders/export', 'GET');
    $request->setUserResolver(fn () => $this->coordinatorX);
    app(LeadersExportController::class)($request);

    $ownLeaderId = $this->leaderX1->id;
    $otherLeaderId = $this->leaderY1->id;

    Excel::assertDownloaded('lideres.xlsx', function (LeadersExport $export) use ($ownLeaderId, $otherLeaderId) {
        $ids = $export->query()->pluck('id');

        return $ids->contains($ownLeaderId) && ! $ids->contains($otherLeaderId);
    });
});

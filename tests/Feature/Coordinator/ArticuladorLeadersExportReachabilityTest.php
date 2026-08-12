<?php

use App\Enums\UserRole;
use App\Exports\LeadersExport;
use App\Filament\Widgets\TopLeadersTable;
use App\Models\Campaign;
use App\Models\User;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;

uses()->group('articulador-leaders-export-reachability');

beforeEach(function () {
    collect(UserRole::values())->each(
        fn ($role) => Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])
    );

    $this->campaignA = Campaign::factory()->create(['status' => 'active']);
    $this->campaignB = Campaign::factory()->create(['status' => 'active']);

    // Articulador A's own transitive team, all in Campaign A.
    $this->areaCoordinatorA = User::factory()->create();
    $this->areaCoordinatorA->assignRole(UserRole::AREA_COORDINATOR->value);
    $this->areaCoordinatorA->campaigns()->attach($this->campaignA->id);

    $this->coordinatorX = User::factory()->create(['area_coordinator_user_id' => $this->areaCoordinatorA->id]);
    $this->coordinatorX->assignRole(UserRole::COORDINATOR->value);
    $this->coordinatorX->campaigns()->attach($this->campaignA->id);

    $this->leaderX1 = User::factory()->create(['coordinator_user_id' => $this->coordinatorX->id]);
    $this->leaderX1->assignRole(UserRole::LEADER->value);
    $this->leaderX1->campaigns()->attach($this->campaignA->id);

    $this->leaderX2 = User::factory()->create(['coordinator_user_id' => $this->coordinatorX->id]);
    $this->leaderX2->assignRole(UserRole::LEADER->value);
    $this->leaderX2->campaigns()->attach($this->campaignA->id);

    // Under coordinatorX (managed by areaCoordinatorA) but ONLY in Campaign B — areaCoordinatorA
    // never belongs to Campaign B, so this leader must never appear in their download.
    $this->leaderX3OtherCampaign = User::factory()->create(['coordinator_user_id' => $this->coordinatorX->id]);
    $this->leaderX3OtherCampaign->assignRole(UserRole::LEADER->value);
    $this->leaderX3OtherCampaign->campaigns()->attach($this->campaignB->id);

    // A second, unrelated articulador with their own coordinador/líder team in the SAME campaign —
    // proves isolation between two articuladores, not just campaign isolation.
    $this->areaCoordinatorB = User::factory()->create();
    $this->areaCoordinatorB->assignRole(UserRole::AREA_COORDINATOR->value);
    $this->areaCoordinatorB->campaigns()->attach($this->campaignA->id);

    $this->coordinatorZ = User::factory()->create(['area_coordinator_user_id' => $this->areaCoordinatorB->id]);
    $this->coordinatorZ->assignRole(UserRole::COORDINATOR->value);
    $this->coordinatorZ->campaigns()->attach($this->campaignA->id);

    $this->leaderZ1 = User::factory()->create(['coordinator_user_id' => $this->coordinatorZ->id]);
    $this->leaderZ1->assignRole(UserRole::LEADER->value);
    $this->leaderZ1->campaigns()->attach($this->campaignA->id);
});

test('AUTHZ-01: an articulador reaches and downloads the leaders export route for their own transitive team', function () {
    $response = $this->actingAs($this->areaCoordinatorA)->get(route('coordinator.leaders.export'));

    $response->assertOk();
    $response->assertHeader('content-disposition');
    $this->assertStringContainsString('lideres.xlsx', $response->headers->get('content-disposition'));
});

test('AUTHZ-01: the downloaded export is scoped only to the articulador own transitive team, no cross-articulador or cross-campaign leakage', function () {
    Excel::fake();

    $this->actingAs($this->areaCoordinatorA)->get(route('coordinator.leaders.export'));

    $expectedIds = [$this->leaderX1->id, $this->leaderX2->id];
    $excludedIds = [$this->leaderZ1->id, $this->leaderX3OtherCampaign->id];

    Excel::assertDownloaded('lideres.xlsx', function (LeadersExport $export) use ($expectedIds, $excludedIds) {
        $ids = $export->query()->pluck('id');

        return collect($expectedIds)->every(fn ($id) => $ids->contains($id))
            && collect($excludedIds)->every(fn ($id) => ! $ids->contains($id));
    });
});

test('a leader is still forbidden from the leaders export route after splitting it out of the coordinator role group', function () {
    $response = $this->actingAs($this->leaderX1)->get(route('coordinator.leaders.export'));

    $response->assertForbidden();
});

test('a coordinador can still reach the leaders export route unchanged, no regression from the route split', function () {
    $response = $this->actingAs($this->coordinatorX)->get(route('coordinator.leaders.export'));

    $response->assertOk();
});

test('the TopLeadersTable widget exposes a link to the full-team export route for an articulador', function () {
    $this->actingAs($this->areaCoordinatorA);

    Livewire::test(TopLeadersTable::class)
        ->assertSee('Exportar Equipo Completo')
        ->assertSeeHtml(route('coordinator.leaders.export'));
});

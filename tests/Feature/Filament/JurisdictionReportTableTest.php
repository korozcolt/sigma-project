<?php

use App\Enums\ElectionType;
use App\Enums\UserRole;
use App\Filament\Widgets\JurisdictionReportTable;
use App\Models\Campaign;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses()->group('dashboard-widgets');

beforeEach(function () {
    Role::firstOrCreate(['name' => UserRole::SUPER_ADMIN->value, 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole(UserRole::SUPER_ADMIN->value);
    $this->actingAs($user);
});

test('jurisdiction report table is hidden for a Nacional-scope campaign (D-04)', function () {
    $nacional = Campaign::factory()->create([
        'election_type' => ElectionType::PRESIDENT,
        'status' => 'active',
    ]);

    Session::put('campaign_context.campaign_id', $nacional->id);
    Session::put('campaign_context.mode', 'single');

    expect(JurisdictionReportTable::canView())->toBeFalse();
});

test('jurisdiction report table compares dentro/fuera for a Municipal-scope campaign', function () {
    $department = Department::factory()->create();
    $insideMunicipality = Municipality::factory()->create(['department_id' => $department->id]);
    $outsideMunicipality = Municipality::factory()->create(['department_id' => $department->id]);

    $campaign = Campaign::factory()->create([
        'election_type' => ElectionType::MAYOR,
        'status' => 'active',
        'department_id' => $department->id,
        'municipality_id' => $insideMunicipality->id,
    ]);

    Session::put('campaign_context.campaign_id', $campaign->id);
    Session::put('campaign_context.mode', 'single');

    Voter::factory()->count(3)->create([
        'campaign_id' => $campaign->id,
        'municipality_id' => $insideMunicipality->id,
    ]);

    Voter::factory()->count(2)->create([
        'campaign_id' => $campaign->id,
        'municipality_id' => $outsideMunicipality->id,
    ]);

    Livewire::test(JurisdictionReportTable::class)
        ->assertOk()
        ->assertSee('Dentro')
        ->assertSee('Fuera')
        ->assertTableHeaderActionsExistInOrder(['export']);
});

test('jurisdiction report table compares dentro/fuera for a Departamental-scope campaign', function () {
    $insideDepartment = Department::factory()->create();
    $outsideDepartment = Department::factory()->create();

    $insideMunicipality = Municipality::factory()->create(['department_id' => $insideDepartment->id]);
    $outsideMunicipality = Municipality::factory()->create(['department_id' => $outsideDepartment->id]);

    $campaign = Campaign::factory()->create([
        'election_type' => ElectionType::GOVERNOR,
        'status' => 'active',
        'department_id' => $insideDepartment->id,
    ]);

    Session::put('campaign_context.campaign_id', $campaign->id);
    Session::put('campaign_context.mode', 'single');

    Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'municipality_id' => $insideMunicipality->id,
    ]);

    Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'municipality_id' => $outsideMunicipality->id,
    ]);

    Livewire::test(JurisdictionReportTable::class)
        ->assertOk()
        ->assertSee('Dentro')
        ->assertSee('Fuera');
});

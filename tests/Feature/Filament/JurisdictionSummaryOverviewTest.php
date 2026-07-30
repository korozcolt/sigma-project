<?php

use App\Enums\ElectionType;
use App\Enums\UserRole;
use App\Filament\Widgets\JurisdictionSummaryOverview;
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

test('jurisdiction summary is hidden for a Nacional-scope campaign', function () {
    $nacional = Campaign::factory()->create([
        'election_type' => ElectionType::PRESIDENT,
        'status' => 'active',
    ]);

    Session::put('campaign_context.campaign_id', $nacional->id);
    Session::put('campaign_context.mode', 'single');

    expect(JurisdictionSummaryOverview::canView())->toBeFalse();
});

test('jurisdiction summary counts dentro/fuera for a Municipal-scope campaign', function () {
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

    $instance = Livewire::test(JurisdictionSummaryOverview::class)->instance();
    $stats = (new ReflectionMethod($instance, 'getStats'))->invoke($instance);

    expect($stats[0]->getValue())->toBe(number_format(3))
        ->and($stats[1]->getValue())->toBe(number_format(2))
        ->and($stats[0]->getUrl())->toBeNull()
        ->and($stats[1]->getUrl())->toBeNull();
});

test('jurisdiction summary counts dentro/fuera for a Departamental-scope campaign', function () {
    $insideDepartment = Department::factory()->create();
    $outsideDepartment = Department::factory()->create();

    $insideMun = Municipality::factory()->create(['department_id' => $insideDepartment->id]);
    $outsideMun = Municipality::factory()->create(['department_id' => $outsideDepartment->id]);

    $campaign = Campaign::factory()->create([
        'election_type' => ElectionType::GOVERNOR,
        'status' => 'active',
        'department_id' => $insideDepartment->id,
    ]);

    Session::put('campaign_context.campaign_id', $campaign->id);
    Session::put('campaign_context.mode', 'single');

    Voter::factory()->count(2)->create([
        'campaign_id' => $campaign->id,
        'municipality_id' => $insideMun->id,
    ]);

    Voter::factory()->count(1)->create([
        'campaign_id' => $campaign->id,
        'municipality_id' => $outsideMun->id,
    ]);

    $instance = Livewire::test(JurisdictionSummaryOverview::class)->instance();
    $stats = (new ReflectionMethod($instance, 'getStats'))->invoke($instance);

    expect($stats[0]->getValue())->toBe(number_format(2))
        ->and($stats[1]->getValue())->toBe(number_format(1));
});

test('jurisdiction summary is hidden when the campaign has no territorial reference', function () {
    // ElectionType::OTHER->scope() === CampaignScope::Regional, the one scope Campaign::boot()
    // does not auto-null — used here to construct a true both-null state.
    $regional = Campaign::factory()->create([
        'election_type' => ElectionType::OTHER,
        'status' => 'active',
        'department_id' => null,
        'municipality_id' => null,
    ]);

    Session::put('campaign_context.campaign_id', $regional->id);
    Session::put('campaign_context.mode', 'single');

    expect(JurisdictionSummaryOverview::canView())->toBeFalse();
});

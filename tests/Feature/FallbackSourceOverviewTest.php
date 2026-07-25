<?php

use App\Enums\PollingPlaceSource;
use App\Enums\UserRole;
use App\Filament\Widgets\FallbackSourceOverview;
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

    $department = Department::factory()->create();
    $this->municipality = Municipality::factory()->create(['department_id' => $department->id]);

    $this->campaign = Campaign::factory()->create(['status' => 'active']);
    Session::put('campaign_context.campaign_id', $this->campaign->id);
    Session::put('campaign_context.mode', 'single');
});

test('fallback source overview counts only non-live, non-null sourced voters in the active campaign', function () {
    Voter::factory()->count(2)->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'polling_place_source' => PollingPlaceSource::SNAPSHOT,
    ]);

    Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'polling_place_source' => PollingPlaceSource::DB_RECONSTRUCTION,
    ]);

    Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'polling_place_source' => PollingPlaceSource::LIVE,
    ]);

    Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'polling_place_source' => null,
    ]);

    $otherCampaign = Campaign::factory()->create(['status' => 'active']);
    Voter::factory()->create([
        'campaign_id' => $otherCampaign->id,
        'municipality_id' => $this->municipality->id,
        'polling_place_source' => PollingPlaceSource::SNAPSHOT,
    ]);

    Livewire::test(FallbackSourceOverview::class)
        ->assertOk()
        ->assertSee('Apoyos en Fuente de Respaldo')
        ->assertSee('3');
});

test('fallback source overview shows zero when no voters exist', function () {
    Livewire::test(FallbackSourceOverview::class)
        ->assertOk()
        ->assertSee('Apoyos en Fuente de Respaldo')
        ->assertSee('0');
});

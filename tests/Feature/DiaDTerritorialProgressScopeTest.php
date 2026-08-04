<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Enums\VoterStatus;
use App\Filament\Widgets\DiaDTerritorialProgressTable;
use App\Models\Campaign;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

    $department = Department::factory()->create();
    $this->municipality = Municipality::factory()->create(['department_id' => $department->id]);

    $this->campaign = Campaign::factory()->create(['status' => 'active']);

    $this->coordinatorA = User::factory()->create();
    $this->coordinatorA->assignRole(UserRole::COORDINATOR->value);
    $this->coordinatorA->campaigns()->attach($this->campaign);

    $this->leaderA = User::factory()->create(['coordinator_user_id' => $this->coordinatorA->id]);
    $this->leaderA->assignRole(UserRole::LEADER->value);
    $this->leaderA->campaigns()->attach($this->campaign);
    Voter::factory()->count(2)->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'registered_by' => $this->leaderA->id,
        'status' => VoterStatus::VOTED,
    ]);

    $this->coordinatorB = User::factory()->create();
    $this->coordinatorB->assignRole(UserRole::COORDINATOR->value);
    $this->coordinatorB->campaigns()->attach($this->campaign);

    $this->leaderB = User::factory()->create(['coordinator_user_id' => $this->coordinatorB->id]);
    $this->leaderB->assignRole(UserRole::LEADER->value);
    $this->leaderB->campaigns()->attach($this->campaign);
    Voter::factory()->count(4)->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'registered_by' => $this->leaderB->id,
        'status' => VoterStatus::VOTED,
    ]);

    Session::put('campaign_context.campaign_id', $this->campaign->id);
    Session::put('campaign_context.mode', 'single');
});

it('un coordinador ve en la tabla territorial solo los conteos de su propio equipo', function () {
    $this->actingAs($this->coordinatorA);

    // assertSeeText/assertDontSeeText strip all tags (and their attributes) before
    // comparing, avoiding false-positive substring matches inside unrelated markup
    // (wire:snapshot checksums, SVG icon paths) that a raw HTML search would hit.
    Livewire::test(DiaDTerritorialProgressTable::class)
        ->assertOk()
        ->assertSeeText($this->municipality->name)
        ->assertSeeText('2')
        ->assertDontSeeText('6'); // 2 (equipo A) + 4 (equipo B) combinado
});

it('un super_admin ve en la tabla territorial el conteo combinado sin restricción', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(UserRole::SUPER_ADMIN->value);
    $this->actingAs($superAdmin);

    Livewire::test(DiaDTerritorialProgressTable::class)
        ->assertOk()
        ->assertSeeText($this->municipality->name)
        ->assertSeeText('6');
});

<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Municipality;
use App\Models\User;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    collect(UserRole::values())->each(fn ($role) => Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));

    $this->municipality = Municipality::factory()->create();
    $this->campaign = Campaign::factory()->create(['municipality_id' => $this->municipality->id]);
});

test('an articulador sees only their own coordinadores', function () {
    $areaCoordinator = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $areaCoordinator->assignRole(UserRole::AREA_COORDINATOR->value);
    $areaCoordinator->campaigns()->attach($this->campaign->id);

    $otherAreaCoordinator = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $otherAreaCoordinator->assignRole(UserRole::AREA_COORDINATOR->value);
    $otherAreaCoordinator->campaigns()->attach($this->campaign->id);

    $ownCoordinator = User::factory()->create([
        'municipality_id' => $this->municipality->id,
        'area_coordinator_user_id' => $areaCoordinator->id,
    ]);
    $ownCoordinator->assignRole(UserRole::COORDINATOR->value);
    $ownCoordinator->campaigns()->attach($this->campaign->id);

    $otherCoordinator = User::factory()->create([
        'municipality_id' => $this->municipality->id,
        'area_coordinator_user_id' => $otherAreaCoordinator->id,
    ]);
    $otherCoordinator->assignRole(UserRole::COORDINATOR->value);
    $otherCoordinator->campaigns()->attach($this->campaign->id);

    actingAs($areaCoordinator);

    Volt::test('articulador.coordinators')
        ->assertSee($ownCoordinator->name)
        ->assertDontSee($otherCoordinator->name);
});

test('searching by a substring of a coordinador name filters the list', function () {
    $areaCoordinator = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $areaCoordinator->assignRole(UserRole::AREA_COORDINATOR->value);
    $areaCoordinator->campaigns()->attach($this->campaign->id);

    $matching = User::factory()->create([
        'municipality_id' => $this->municipality->id,
        'area_coordinator_user_id' => $areaCoordinator->id,
        'name' => 'Lanna Contreras',
    ]);
    $matching->assignRole(UserRole::COORDINATOR->value);
    $matching->campaigns()->attach($this->campaign->id);

    $nonMatching = User::factory()->create([
        'municipality_id' => $this->municipality->id,
        'area_coordinator_user_id' => $areaCoordinator->id,
        'name' => 'Javiana Ortega',
    ]);
    $nonMatching->assignRole(UserRole::COORDINATOR->value);
    $nonMatching->campaigns()->attach($this->campaign->id);

    actingAs($areaCoordinator);

    Volt::test('articulador.coordinators')
        ->set('search', 'Lanna')
        ->assertSee($matching->name)
        ->assertDontSee($nonMatching->name);
});

test('an admin_campaign sees coordinadores belonging to multiple different articuladores', function () {
    $admin = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $admin->assignRole(UserRole::ADMIN_CAMPAIGN->value);
    $admin->campaigns()->attach($this->campaign->id);

    $areaCoordinatorOne = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $areaCoordinatorOne->assignRole(UserRole::AREA_COORDINATOR->value);
    $areaCoordinatorOne->campaigns()->attach($this->campaign->id);

    $areaCoordinatorTwo = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $areaCoordinatorTwo->assignRole(UserRole::AREA_COORDINATOR->value);
    $areaCoordinatorTwo->campaigns()->attach($this->campaign->id);

    $coordinatorOne = User::factory()->create([
        'municipality_id' => $this->municipality->id,
        'area_coordinator_user_id' => $areaCoordinatorOne->id,
    ]);
    $coordinatorOne->assignRole(UserRole::COORDINATOR->value);
    $coordinatorOne->campaigns()->attach($this->campaign->id);

    $coordinatorTwo = User::factory()->create([
        'municipality_id' => $this->municipality->id,
        'area_coordinator_user_id' => $areaCoordinatorTwo->id,
    ]);
    $coordinatorTwo->assignRole(UserRole::COORDINATOR->value);
    $coordinatorTwo->campaigns()->attach($this->campaign->id);

    actingAs($admin);

    Volt::test('articulador.coordinators')
        ->assertSee($coordinatorOne->name)
        ->assertSee($coordinatorTwo->name);
});

test('an articulador with zero coordinadores sees the empty state', function () {
    $areaCoordinator = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $areaCoordinator->assignRole(UserRole::AREA_COORDINATOR->value);
    $areaCoordinator->campaigns()->attach($this->campaign->id);

    actingAs($areaCoordinator);

    Volt::test('articulador.coordinators')
        ->assertSee('No hay coordinadores registrados');
});

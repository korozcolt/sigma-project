<?php

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

uses()->group('coordinator-policy');

beforeEach(function () {
    collect(UserRole::values())->each(
        fn ($role) => Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])
    );
});

test('AUTHZ-02: articulador is denied viewing/editing a coordinador that is not theirs, with an explicit reason', function () {
    $areaCoordinator = User::factory()->create();
    $areaCoordinator->assignRole(UserRole::AREA_COORDINATOR->value);

    $otherAreaCoordinator = User::factory()->create();
    $otherAreaCoordinator->assignRole(UserRole::AREA_COORDINATOR->value);

    $notMyCoordinator = User::factory()->create(['area_coordinator_user_id' => $otherAreaCoordinator->id]);
    $notMyCoordinator->assignRole(UserRole::COORDINATOR->value);

    $viewResponse = Gate::forUser($areaCoordinator)->inspect('view', $notMyCoordinator);
    $updateResponse = Gate::forUser($areaCoordinator)->inspect('update', $notMyCoordinator);

    expect($viewResponse->allowed())->toBeFalse()
        ->and($viewResponse->message())->toBe('Este coordinador no pertenece a tu equipo de articulador.')
        ->and($updateResponse->allowed())->toBeFalse()
        ->and($updateResponse->message())->toBe('Este coordinador no pertenece a tu equipo de articulador.');
});

test('articulador is allowed to view/edit their own coordinador', function () {
    $areaCoordinator = User::factory()->create();
    $areaCoordinator->assignRole(UserRole::AREA_COORDINATOR->value);

    $myCoordinator = User::factory()->create(['area_coordinator_user_id' => $areaCoordinator->id]);
    $myCoordinator->assignRole(UserRole::COORDINATOR->value);

    expect(Gate::forUser($areaCoordinator)->inspect('view', $myCoordinator)->allowed())->toBeTrue()
        ->and(Gate::forUser($areaCoordinator)->inspect('update', $myCoordinator)->allowed())->toBeTrue();
});

test('CoordinatorPolicy does not restrict any other role viewing/editing a coordinador or a leader (no regression)', function (UserRole $role) {
    $actor = User::factory()->create();
    $actor->assignRole($role->value);

    $coordinator = User::factory()->create();
    $coordinator->assignRole(UserRole::COORDINATOR->value);

    $leader = User::factory()->create();
    $leader->assignRole(UserRole::LEADER->value);

    expect(Gate::forUser($actor)->inspect('view', $coordinator)->allowed())->toBeTrue()
        ->and(Gate::forUser($actor)->inspect('update', $coordinator)->allowed())->toBeTrue()
        ->and(Gate::forUser($actor)->inspect('view', $leader)->allowed())->toBeTrue()
        ->and(Gate::forUser($actor)->inspect('update', $leader)->allowed())->toBeTrue();
})->with(collect(UserRole::cases())->reject(fn (UserRole $role) => $role === UserRole::AREA_COORDINATOR)->all());

test('articulador viewing/editing a non-coordinador User record is unrestricted (policy scope is coordinador-only, D-04)', function () {
    $areaCoordinator = User::factory()->create();
    $areaCoordinator->assignRole(UserRole::AREA_COORDINATOR->value);

    $someLeader = User::factory()->create();
    $someLeader->assignRole(UserRole::LEADER->value);

    $anotherAreaCoordinator = User::factory()->create();
    $anotherAreaCoordinator->assignRole(UserRole::AREA_COORDINATOR->value);

    expect(Gate::forUser($areaCoordinator)->inspect('view', $someLeader)->allowed())->toBeTrue()
        ->and(Gate::forUser($areaCoordinator)->inspect('update', $someLeader)->allowed())->toBeTrue()
        ->and(Gate::forUser($areaCoordinator)->inspect('view', $anotherAreaCoordinator)->allowed())->toBeTrue()
        ->and(Gate::forUser($areaCoordinator)->inspect('update', $anotherAreaCoordinator)->allowed())->toBeTrue();
});

test('AUTHZ-03: articulador cannot view/edit their own coordinador if that coordinador belongs to a different campaign than the articulador is currently scoped to', function () {
    $campaignA = Campaign::factory()->create(['status' => 'active']);
    $campaignB = Campaign::factory()->create(['status' => 'active']);

    $areaCoordinator = User::factory()->create();
    $areaCoordinator->assignRole(UserRole::AREA_COORDINATOR->value);
    $areaCoordinator->campaigns()->attach($campaignA->id);

    $coordinatorInOtherCampaign = User::factory()->create(['area_coordinator_user_id' => $areaCoordinator->id]);
    $coordinatorInOtherCampaign->assignRole(UserRole::COORDINATOR->value);
    $coordinatorInOtherCampaign->campaigns()->attach($campaignB->id);

    // Ownership is technically correct (area_coordinator_user_id matches), but Gate::before
    // runs first and denies on campaign mismatch before CoordinatorPolicy::view/update ever run.
    // Note: since $coordinatorInOtherCampaign is a User instance, Gate::before's User-branch
    // fires (not the generic Model/campaign_id branch), producing the User-specific denial
    // message — NOT the "pertenece a otra campaña" message used for non-User models like Voter.
    $response = Gate::forUser($areaCoordinator)->inspect('view', $coordinatorInOtherCampaign);

    expect($response->allowed())->toBeFalse()
        ->and($response->message())->toBe('Este usuario no pertenece a la campaña activa.');
});

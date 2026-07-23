<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Enums\VoterStatus;
use App\Models\Campaign;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\Neighborhood;
use App\Models\User;
use App\Observers\UserObserver;
use App\Rules\DocumentNotBelongsToLeaderOrCoordinator;
use Spatie\Permission\Models\Role;

// NOTE (Wave 0 / plan 02.1-01): This file scaffolds RED tests for D-08.
// `App\Rules\DocumentNotBelongsToLeaderOrCoordinator` does not exist yet - it is
// implemented in plan 02.1-04. Failing/erroring here (class not found) is expected
// and correct until that plan lands.

beforeEach(function () {
    collect(UserRole::values())->each(function ($role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    });

    $department = Department::factory()->create();
    $this->municipality = Municipality::factory()->create(['department_id' => $department->id]);
    $this->neighborhood = Neighborhood::factory()->create(['municipality_id' => $this->municipality->id]);
    $this->campaign = Campaign::factory()->create();
});

it('rejects registering an Apoyo whose document_number belongs to an existing leader user', function () {
    $leader = User::factory()->create([
        'document_number' => '6666666666',
        'municipality_id' => $this->municipality->id,
    ]);
    $leader->assignRole(UserRole::LEADER->value);

    $failed = null;

    (new DocumentNotBelongsToLeaderOrCoordinator)->validate('document_number', '6666666666', function (string $message) use (&$failed): void {
        $failed = $message;
    });

    expect($failed)->not->toBeNull()
        ->and($failed)->toContain('líder/coordinador');
});

it('rejects registering an Apoyo whose document_number belongs to an existing coordinator user', function () {
    $coordinator = User::factory()->create([
        'document_number' => '7777777777',
        'municipality_id' => $this->municipality->id,
    ]);
    $coordinator->assignRole(UserRole::COORDINATOR->value);

    $failed = null;

    (new DocumentNotBelongsToLeaderOrCoordinator)->validate('document_number', '7777777777', function (string $message) use (&$failed): void {
        $failed = $message;
    });

    expect($failed)->not->toBeNull()
        ->and($failed)->toContain('líder/coordinador');
});

it('allows registering an Apoyo whose document_number belongs to an admin_campaign user', function () {
    $admin = User::factory()->create([
        'document_number' => '8888888888',
        'municipality_id' => $this->municipality->id,
    ]);
    $admin->assignRole(UserRole::ADMIN_CAMPAIGN->value);

    $failed = null;

    (new DocumentNotBelongsToLeaderOrCoordinator)->validate('document_number', '8888888888', function (string $message) use (&$failed): void {
        $failed = $message;
    });

    expect($failed)->toBeNull();
});

it('does not block UserObserver from auto-creating a leader\'s own system voter record', function () {
    $leader = User::factory()->create([
        'document_number' => '9999999999',
        'municipality_id' => $this->municipality->id,
        'neighborhood_id' => $this->neighborhood->id,
    ]);
    $leader->assignRole(UserRole::LEADER->value);

    $leader->campaigns()->attach($this->campaign);

    // Simulate the observer manually, mirroring tests/Feature/UserVoterRelationTest.php.
    $observer = new UserObserver;
    $observer->created($leader);

    $leader->refresh();

    expect($leader->voter)->not->toBeNull()
        ->and($leader->voter->user_id)->toBe($leader->id)
        ->and($leader->voter->status)->toBe(VoterStatus::CONFIRMED);
});

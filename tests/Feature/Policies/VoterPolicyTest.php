<?php

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Support\Facades\Session;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    collect(UserRole::values())->each(
        fn ($role) => Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])
    );
});

it('denies every mutating ability on Voter for reports_viewer', function () {
    $campaign = Campaign::factory()->create(['status' => 'active']);

    $user = User::factory()->create();
    $user->assignRole(UserRole::REPORTS_VIEWER->value);
    $user->campaigns()->attach($campaign->id);

    $voter = Voter::factory()->create(['campaign_id' => $campaign->id]);

    actingAs($user);

    Session::put('campaign_context.campaign_id', $campaign->id);
    Session::put('campaign_context.mode', 'single');

    expect($user->can('create', Voter::class))->toBeFalse();
    expect($user->can('update', $voter))->toBeFalse();
    expect($user->can('delete', $voter))->toBeFalse();
    expect($user->can('deleteAny', Voter::class))->toBeFalse();
    expect($user->can('restore', $voter))->toBeFalse();
    expect($user->can('restoreAny', Voter::class))->toBeFalse();
    expect($user->can('forceDelete', $voter))->toBeFalse();
    expect($user->can('forceDeleteAny', Voter::class))->toBeFalse();
    expect($user->can('replicate', $voter))->toBeFalse();
    expect($user->can('reorder', Voter::class))->toBeFalse();
});

it('allows viewAny and view on Voter for reports_viewer', function () {
    $campaign = Campaign::factory()->create(['status' => 'active']);

    $user = User::factory()->create();
    $user->assignRole(UserRole::REPORTS_VIEWER->value);
    $user->campaigns()->attach($campaign->id);

    $voter = Voter::factory()->create(['campaign_id' => $campaign->id]);

    actingAs($user);

    Session::put('campaign_context.campaign_id', $campaign->id);
    Session::put('campaign_context.mode', 'single');

    expect($user->can('viewAny', Voter::class))->toBeTrue();
    expect($user->can('view', $voter))->toBeTrue();
});

it('allows every mutating ability on Voter for every other role (no regression)', function (UserRole $role) {
    $campaign = Campaign::factory()->create(['status' => 'active']);

    $user = User::factory()->create();
    $user->assignRole($role->value);

    if ($role === UserRole::SUPER_ADMIN) {
        Session::put('campaign_context.mode', 'all');
    } else {
        $user->campaigns()->attach($campaign->id);
        Session::put('campaign_context.campaign_id', $campaign->id);
        Session::put('campaign_context.mode', 'single');
    }

    $voter = Voter::factory()->create(['campaign_id' => $campaign->id]);

    actingAs($user);

    expect($user->can('create', Voter::class))->toBeTrue();
    expect($user->can('update', $voter))->toBeTrue();
    expect($user->can('delete', $voter))->toBeTrue();
    expect($user->can('deleteAny', Voter::class))->toBeTrue();
    expect($user->can('restore', $voter))->toBeTrue();
    expect($user->can('restoreAny', Voter::class))->toBeTrue();
    expect($user->can('forceDelete', $voter))->toBeTrue();
    expect($user->can('forceDeleteAny', Voter::class))->toBeTrue();
    expect($user->can('replicate', $voter))->toBeTrue();
    expect($user->can('reorder', Voter::class))->toBeTrue();
})->with(collect(UserRole::cases())->reject(fn (UserRole $role) => $role === UserRole::REPORTS_VIEWER)->all());

it('allows every mutating ability on Voter for a user holding super_admin AND reports_viewer together', function () {
    $campaign = Campaign::factory()->create(['status' => 'active']);

    $user = User::factory()->create();
    $user->assignRole(UserRole::SUPER_ADMIN->value);
    $user->assignRole(UserRole::REPORTS_VIEWER->value);

    Session::put('campaign_context.mode', 'all');

    $voter = Voter::factory()->create(['campaign_id' => $campaign->id]);

    actingAs($user);

    expect($user->can('create', Voter::class))->toBeTrue()
        ->and($user->can('update', $voter))->toBeTrue()
        ->and($user->can('delete', $voter))->toBeTrue()
        ->and($user->can('deleteAny', Voter::class))->toBeTrue()
        ->and($user->can('restore', $voter))->toBeTrue()
        ->and($user->can('restoreAny', Voter::class))->toBeTrue()
        ->and($user->can('forceDelete', $voter))->toBeTrue()
        ->and($user->can('forceDeleteAny', Voter::class))->toBeTrue()
        ->and($user->can('replicate', $voter))->toBeTrue()
        ->and($user->can('reorder', Voter::class))->toBeTrue();
});

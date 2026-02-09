<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Support\Facades\Session;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

it('scopes voters by current campaign for non-super users', function () {
    $campaignA = Campaign::factory()->create();
    $campaignB = Campaign::factory()->create();

    $role = Role::firstOrCreate(['name' => UserRole::LEADER->value]);
    $user = User::factory()->create();
    $user->assignRole($role);
    $user->campaigns()->attach($campaignA->id, ['assigned_at' => now()]);

    Voter::factory()->create(['campaign_id' => $campaignA->id]);
    Voter::factory()->create(['campaign_id' => $campaignB->id]);

    actingAs($user);

    expect(Voter::query()->count())->toBe(1);
});

it('overrides campaign_id on create for non-super users', function () {
    $campaignA = Campaign::factory()->create();
    $campaignB = Campaign::factory()->create();

    $role = Role::firstOrCreate(['name' => UserRole::LEADER->value]);
    $user = User::factory()->create();
    $user->assignRole($role);
    $user->campaigns()->attach($campaignA->id, ['assigned_at' => now()]);

    actingAs($user);

    $voter = Voter::factory()->create(['campaign_id' => $campaignB->id]);

    expect($voter->campaign_id)->toBe($campaignA->id);
});

it('allows super admin to view all campaigns when mode is all', function () {
    $campaignA = Campaign::factory()->create();
    $campaignB = Campaign::factory()->create();

    $role = Role::firstOrCreate(['name' => UserRole::SUPER_ADMIN->value]);
    $user = User::factory()->create();
    $user->assignRole($role);

    Voter::factory()->create(['campaign_id' => $campaignA->id]);
    Voter::factory()->create(['campaign_id' => $campaignB->id]);

    actingAs($user);
    Session::put('campaign_context.mode', 'all');

    expect(Voter::query()->count())->toBe(2);
});

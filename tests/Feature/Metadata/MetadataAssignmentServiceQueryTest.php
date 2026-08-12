<?php

use App\Models\MetadataKey;
use App\Models\User;
use App\Models\UserMetadataValue;
use App\Services\MetadataAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function makeQueryMetadataUser(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'document_number' => (string) fake()->unique()->numerify('##########'),
        'phone' => fake()->numerify('3#########'),
    ], $attributes));
}

it('sorts a numeric metadata key numerically, not lexicographically', function () {
    $key = MetadataKey::factory()->create(['type' => 'numeric', 'is_active' => true]);

    $userWithTwo = makeQueryMetadataUser();
    $userWithTen = makeQueryMetadataUser();

    UserMetadataValue::create([
        'user_id' => $userWithTwo->id,
        'metadata_key_id' => $key->id,
        'value' => '2',
        'assigned_by' => $userWithTwo->id,
        'assigned_at' => now(),
    ]);

    UserMetadataValue::create([
        'user_id' => $userWithTen->id,
        'metadata_key_id' => $key->id,
        'value' => '10',
        'assigned_by' => $userWithTen->id,
        'assigned_at' => now(),
    ]);

    $service = app(MetadataAssignmentService::class);

    $query = User::query()->whereIn('id', [$userWithTwo->id, $userWithTen->id]);
    $query = $service->withCurrentValueSelects($query, collect([$key]));

    $ids = $query->orderBy("metadata_{$key->id}")->pluck('id')->all();

    expect($ids)->toBe([$userWithTwo->id, $userWithTen->id]);
});

it('resolves the current value to the higher id when assigned_at collides', function () {
    $key = MetadataKey::factory()->create(['type' => 'text', 'is_active' => true]);
    $user = makeQueryMetadataUser();

    $collidingTimestamp = Carbon::parse('2026-08-10 12:00:00');

    UserMetadataValue::create([
        'user_id' => $user->id,
        'metadata_key_id' => $key->id,
        'value' => 'primero',
        'assigned_by' => $user->id,
        'assigned_at' => $collidingTimestamp,
    ]);

    $second = UserMetadataValue::create([
        'user_id' => $user->id,
        'metadata_key_id' => $key->id,
        'value' => 'segundo',
        'assigned_by' => $user->id,
        'assigned_at' => $collidingTimestamp,
    ]);

    $service = app(MetadataAssignmentService::class);

    $query = User::query()->whereKey($user->id);
    $query = $service->withCurrentValueSelects($query, collect([$key]));

    $resolved = $query->first();

    expect($resolved->{"metadata_{$key->id}"})->toBe($second->value);
});

it('resolves a null alias when the user has no value for the key', function () {
    $key = MetadataKey::factory()->create(['type' => 'text', 'is_active' => true]);
    $user = makeQueryMetadataUser();

    $service = app(MetadataAssignmentService::class);

    $query = User::query()->whereKey($user->id);
    $query = $service->withCurrentValueSelects($query, collect([$key]));

    $resolved = $query->first();

    expect($resolved->{"metadata_{$key->id}"})->toBeNull();
});

it('applyMetadataFilter matches only the current value, excluding stale historical rows', function () {
    $key = MetadataKey::factory()->create(['type' => 'text', 'is_active' => true]);

    $matching = makeQueryMetadataUser();
    $stale = makeQueryMetadataUser();
    $unrelated = makeQueryMetadataUser();

    UserMetadataValue::create([
        'user_id' => $matching->id,
        'metadata_key_id' => $key->id,
        'value' => 'zona-norte',
        'assigned_by' => $matching->id,
        'assigned_at' => now(),
    ]);

    // Stale user: value was overwritten by a later assignment to a non-matching value.
    UserMetadataValue::create([
        'user_id' => $stale->id,
        'metadata_key_id' => $key->id,
        'value' => 'zona-norte',
        'assigned_by' => $stale->id,
        'assigned_at' => now()->subDay(),
    ]);

    UserMetadataValue::create([
        'user_id' => $stale->id,
        'metadata_key_id' => $key->id,
        'value' => 'zona-sur',
        'assigned_by' => $stale->id,
        'assigned_at' => now(),
    ]);

    UserMetadataValue::create([
        'user_id' => $unrelated->id,
        'metadata_key_id' => $key->id,
        'value' => 'zona-oriente',
        'assigned_by' => $unrelated->id,
        'assigned_at' => now(),
    ]);

    $service = app(MetadataAssignmentService::class);

    $query = User::query()->whereIn('id', [$matching->id, $stale->id, $unrelated->id]);
    $result = $service->applyMetadataFilter($query, $key, 'zona-norte')->pluck('id')->all();

    expect($result)->toBe([$matching->id]);
});

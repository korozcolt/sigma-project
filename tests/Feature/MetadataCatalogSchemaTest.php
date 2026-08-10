<?php

declare(strict_types=1);

use App\Models\MetadataKey;
use App\Models\User;
use App\Models\UserMetadataValue;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

it('metadata_keys table exists with the expected columns and type enum', function () {
    expect(Schema::hasTable('metadata_keys'))->toBeTrue()
        ->and(Schema::hasColumns('metadata_keys', ['id', 'key', 'label', 'type', 'options', 'is_active', 'created_at', 'updated_at']))->toBeTrue();
});

it('metadata_keys.key is unique', function () {
    MetadataKey::factory()->create(['key' => 'biaticos']);

    expect(fn () => MetadataKey::factory()->create(['key' => 'biaticos']))
        ->toThrow(QueryException::class);
});

it('metadata_keys.type accepts numeric, text, date, and select', function () {
    foreach (['numeric', 'text', 'date', 'select'] as $type) {
        $key = MetadataKey::factory()->create(['type' => $type]);
        expect($key->type)->toBe($type);
    }
});

it('user_metadata_values table exists with the expected columns and FKs', function () {
    expect(Schema::hasTable('user_metadata_values'))->toBeTrue()
        ->and(Schema::hasColumns('user_metadata_values', ['id', 'user_id', 'metadata_key_id', 'value', 'assigned_by', 'assigned_at', 'created_at', 'updated_at']))->toBeTrue();

    $user = User::factory()->create();
    $assigner = User::factory()->create();
    $key = MetadataKey::factory()->create();

    $value = UserMetadataValue::factory()->create([
        'user_id' => $user->id,
        'metadata_key_id' => $key->id,
        'assigned_by' => $assigner->id,
    ]);

    expect($value->user->id)->toBe($user->id)
        ->and($value->metadataKey->id)->toBe($key->id)
        ->and($value->assignedByUser->id)->toBe($assigner->id);
});

it('user_metadata_values is append-only: no unique constraint on (user_id, metadata_key_id), D-02', function () {
    $user = User::factory()->create();
    $key = MetadataKey::factory()->create();

    UserMetadataValue::factory()->create([
        'user_id' => $user->id,
        'metadata_key_id' => $key->id,
        'value' => '50000',
        'assigned_at' => now()->subDay(),
    ]);

    UserMetadataValue::factory()->create([
        'user_id' => $user->id,
        'metadata_key_id' => $key->id,
        'value' => '60000',
        'assigned_at' => now(),
    ]);

    expect(UserMetadataValue::where('user_id', $user->id)->where('metadata_key_id', $key->id)->count())->toBe(2);
});

it('no JSON metadata column is added to users (success criterion 4)', function () {
    expect(Schema::hasColumn('users', 'metadata'))->toBeFalse();
});

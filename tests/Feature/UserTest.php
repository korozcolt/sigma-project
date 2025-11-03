<?php

use App\Models\Campaign;
use App\Models\Municipality;
use App\Models\Neighborhood;
use App\Models\User;

use function Pest\Laravel\assertDatabaseHas;

it('can create a user with basic fields', function () {
    $user = User::factory()->create([
        'name' => 'Juan Pérez',
        'email' => 'juan@example.com',
    ]);

    expect($user)->toBeInstanceOf(User::class);
    expect($user->name)->toBe('Juan Pérez');
    expect($user->email)->toBe('juan@example.com');

    assertDatabaseHas('users', [
        'name' => 'Juan Pérez',
        'email' => 'juan@example.com',
    ]);
});

it('can create a user with extended profile fields', function () {
    $municipality = Municipality::factory()->create();
    $neighborhood = Neighborhood::factory()->create();

    $user = User::factory()->create([
        'name' => 'María González',
        'email' => 'maria@example.com',
        'phone' => '300 123 4567',
        'secondary_phone' => '310 987 6543',
        'document_number' => '1234567890',
        'birth_date' => '1990-05-15',
        'address' => 'Calle 123 # 45-67',
        'municipality_id' => $municipality->id,
        'neighborhood_id' => $neighborhood->id,
    ]);

    expect($user->phone)->toBe('300 123 4567');
    expect($user->secondary_phone)->toBe('310 987 6543');
    expect($user->document_number)->toBe('1234567890');
    expect($user->birth_date)->toBeInstanceOf(Carbon\Carbon::class);
    expect($user->address)->toBe('Calle 123 # 45-67');
    expect($user->municipality_id)->toBe($municipality->id);
    expect($user->neighborhood_id)->toBe($neighborhood->id);
});

it('document_number is unique', function () {
    User::factory()->create(['document_number' => '1234567890']);

    expect(fn () => User::factory()->create(['document_number' => '1234567890']))
        ->toThrow(Exception::class);
});

it('casts birth_date to Carbon', function () {
    $user = User::factory()->create(['birth_date' => '1990-05-15']);

    expect($user->birth_date)->toBeInstanceOf(Carbon\Carbon::class);
    expect($user->birth_date->format('Y-m-d'))->toBe('1990-05-15');
});

it('has municipality relationship', function () {
    $user = User::factory()->create();

    expect($user->municipality())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

it('can retrieve municipality', function () {
    $municipality = Municipality::factory()->create(['name' => 'Bogotá']);
    $user = User::factory()->create(['municipality_id' => $municipality->id]);

    $user->load('municipality');

    expect($user->municipality->id)->toBe($municipality->id);
    expect($user->municipality->name)->toBe('Bogotá');
});

it('has neighborhood relationship', function () {
    $user = User::factory()->create();

    expect($user->neighborhood())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

it('can retrieve neighborhood', function () {
    $neighborhood = Neighborhood::factory()->create(['name' => 'Centro']);
    $user = User::factory()->create(['neighborhood_id' => $neighborhood->id]);

    $user->load('neighborhood');

    expect($user->neighborhood->id)->toBe($neighborhood->id);
    expect($user->neighborhood->name)->toBe('Centro');
});

it('has campaigns relationship', function () {
    $user = User::factory()->create();

    expect($user->campaigns())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsToMany::class);
});

it('can attach to campaigns', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->create();

    $user->campaigns()->attach($campaign->id, [
        'assigned_at' => now(),
        'assigned_by' => $user->id,
    ]);

    $user->load('campaigns');

    expect($user->campaigns)->toHaveCount(1);
    expect($user->campaigns->first()->id)->toBe($campaign->id);
});

it('has createdCampaigns relationship', function () {
    $user = User::factory()->create();

    expect($user->createdCampaigns())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\HasMany::class);
});

it('can retrieve created campaigns', function () {
    $user = User::factory()->create();
    $campaign1 = Campaign::factory()->create(['created_by' => $user->id]);
    $campaign2 = Campaign::factory()->create(['created_by' => $user->id]);

    $user->load('createdCampaigns');

    expect($user->createdCampaigns)->toHaveCount(2);
    expect($user->createdCampaigns->pluck('id'))->toContain($campaign1->id, $campaign2->id);
});

it('has territorialAssignments relationship', function () {
    $user = User::factory()->create();

    expect($user->territorialAssignments())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\HasMany::class);
});

it('initials method works correctly', function () {
    $user = User::factory()->create(['name' => 'Juan Carlos Pérez']);

    expect($user->initials())->toBe('JC');
});

it('initials method works with single name', function () {
    $user = User::factory()->create(['name' => 'Juan']);

    expect($user->initials())->toBe('J');
});

it('can update user profile', function () {
    $user = User::factory()->create([
        'name' => 'Original Name',
        'phone' => '300 111 1111',
    ]);

    $user->update([
        'name' => 'Updated Name',
        'phone' => '300 222 2222',
    ]);

    expect($user->fresh()->name)->toBe('Updated Name');
    expect($user->fresh()->phone)->toBe('300 222 2222');
});

it('can delete a user', function () {
    $user = User::factory()->create();
    $id = $user->id;

    $user->delete();

    expect(User::find($id))->toBeNull();
});

it('deleting municipality sets user municipality_id to null', function () {
    $municipality = Municipality::factory()->create();
    $user = User::factory()->create(['municipality_id' => $municipality->id]);

    $municipality->delete();

    expect($user->fresh()->municipality_id)->toBeNull();
});

it('deleting neighborhood sets user neighborhood_id to null', function () {
    $neighborhood = Neighborhood::factory()->create();
    $user = User::factory()->create(['neighborhood_id' => $neighborhood->id]);

    $neighborhood->delete();

    expect($user->fresh()->neighborhood_id)->toBeNull();
});

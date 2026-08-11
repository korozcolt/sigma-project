<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Resources\AreaCoordinators\Pages\ListAreaCoordinators;
use App\Filament\Resources\Coordinators\Pages\ListCoordinators;
use App\Filament\Resources\Leaders\Pages\ListLeaders;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\MetadataKey;
use App\Models\User;
use App\Models\UserMetadataValue;
use App\Services\MetadataAssignmentService;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    collect(UserRole::values())->each(
        fn ($role) => Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])
    );
});

function bulkMetadataSuperAdmin(): User
{
    $user = User::factory()->create([
        'document_number' => '900100200',
        'phone' => '3001234567',
    ]);
    $user->assignRole(UserRole::SUPER_ADMIN->value);

    actingAs($user);
    Session::put('campaign_context.mode', 'all');

    return $user;
}

/**
 * UserFactory nulls document_number/phone 10-35% of the time, so every
 * fixture mounted into a Filament table/edit form sets both explicitly.
 */
function bulkMetadataUser(?string $role = null): User
{
    $user = User::factory()->create([
        'document_number' => (string) fake()->unique()->numerify('##########'),
        'phone' => fake()->numerify('3#########'),
    ]);

    if ($role !== null) {
        $user->assignRole($role);
    }

    return $user;
}

it('exposes the metadata bulk action on the coordinadores table', function () {
    bulkMetadataSuperAdmin();

    Livewire::test(ListCoordinators::class)
        ->assertTableBulkActionVisible('assignMetadata');
});

it('assigns the same value to several coordinadores at once', function () {
    $superAdmin = bulkMetadataSuperAdmin();

    $coordinators = collect(range(1, 3))->map(fn () => bulkMetadataUser(UserRole::COORDINATOR->value));

    $key = MetadataKey::factory()->create(['type' => 'numeric', 'is_active' => true]);

    Livewire::test(ListCoordinators::class)
        ->callTableBulkAction('assignMetadata', $coordinators, [
            'metadata_key_id' => $key->id,
            'value' => '50000',
        ]);

    $rows = UserMetadataValue::where('metadata_key_id', $key->id)->get();

    expect($rows)->toHaveCount(3)
        ->and($rows->pluck('user_id')->sort()->values()->all())
        ->toBe($coordinators->pluck('id')->sort()->values()->all())
        ->and($rows->pluck('value')->unique()->all())->toBe(['50000'])
        ->and($rows->pluck('assigned_by')->unique()->all())->toBe([$superAdmin->id]);
});

it('assigns the same value to several líderes at once', function () {
    $superAdmin = bulkMetadataSuperAdmin();

    $leaders = collect(range(1, 2))->map(fn () => bulkMetadataUser(UserRole::LEADER->value));

    $key = MetadataKey::factory()->create(['type' => 'text', 'is_active' => true]);

    Livewire::test(ListLeaders::class)
        ->callTableBulkAction('assignMetadata', $leaders, [
            'metadata_key_id' => $key->id,
            'value' => 'zona-norte',
        ]);

    $rows = UserMetadataValue::where('metadata_key_id', $key->id)->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('user_id')->sort()->values()->all())
        ->toBe($leaders->pluck('id')->sort()->values()->all())
        ->and($rows->pluck('value')->unique()->all())->toBe(['zona-norte'])
        ->and($rows->pluck('assigned_by')->unique()->all())->toBe([$superAdmin->id]);
});

it('assigns the same value to several articuladores at once', function () {
    $superAdmin = bulkMetadataSuperAdmin();

    $areaCoordinators = collect(range(1, 2))->map(fn () => bulkMetadataUser(UserRole::AREA_COORDINATOR->value));

    $key = MetadataKey::factory()->create(['type' => 'select', 'options' => ['si', 'no'], 'is_active' => true]);

    Livewire::test(ListAreaCoordinators::class)
        ->callTableBulkAction('assignMetadata', $areaCoordinators, [
            'metadata_key_id' => $key->id,
            'value' => 'si',
        ]);

    $rows = UserMetadataValue::where('metadata_key_id', $key->id)->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('user_id')->sort()->values()->all())
        ->toBe($areaCoordinators->pluck('id')->sort()->values()->all())
        ->and($rows->pluck('value')->unique()->all())->toBe(['si'])
        ->and($rows->pluck('assigned_by')->unique()->all())->toBe([$superAdmin->id]);
});

it('assigns the same value to several users from the usuarios table', function () {
    $superAdmin = bulkMetadataSuperAdmin();

    $users = collect(range(1, 2))->map(fn () => bulkMetadataUser());

    $key = MetadataKey::factory()->create(['type' => 'numeric', 'is_active' => true]);

    Livewire::test(ListUsers::class)
        ->callTableBulkAction('assignMetadata', $users, [
            'metadata_key_id' => $key->id,
            'value' => '75',
        ]);

    $rows = UserMetadataValue::where('metadata_key_id', $key->id)->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('user_id')->sort()->values()->all())
        ->toBe($users->pluck('id')->sort()->values()->all())
        ->and($rows->pluck('value')->unique()->all())->toBe(['75'])
        ->and($rows->pluck('assigned_by')->unique()->all())->toBe([$superAdmin->id]);
});

it('keeps bulk assignments append-only across two runs', function () {
    bulkMetadataSuperAdmin();

    $users = collect(range(1, 2))->map(fn () => bulkMetadataUser());

    $key = MetadataKey::factory()->create(['type' => 'numeric', 'is_active' => true]);

    Livewire::test(ListUsers::class)
        ->callTableBulkAction('assignMetadata', $users, [
            'metadata_key_id' => $key->id,
            'value' => '10',
        ]);

    Livewire::test(ListUsers::class)
        ->callTableBulkAction('assignMetadata', $users, [
            'metadata_key_id' => $key->id,
            'value' => '20',
        ]);

    expect(UserMetadataValue::where('metadata_key_id', $key->id)->count())->toBe(4);

    // Both bulk runs land inside the same wall-clock second in this test, so
    // assigned_at alone is ambiguous; currentValues() only resolves correctly
    // because it tie-breaks on id DESC (see MetadataAssignmentService).
    $service = app(MetadataAssignmentService::class);

    foreach ($users as $user) {
        expect($service->currentValues($user)[$key->id]->value)->toBe('20');
    }
});

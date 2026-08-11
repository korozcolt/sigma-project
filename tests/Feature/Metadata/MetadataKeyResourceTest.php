<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Resources\MetadataKeys\MetadataKeyResource;
use App\Filament\Resources\MetadataKeys\Pages\CreateMetadataKey;
use App\Filament\Resources\MetadataKeys\Pages\EditMetadataKey;
use App\Filament\Resources\MetadataKeys\Pages\ListMetadataKeys;
use App\Models\MetadataKey;
use App\Models\User;
use App\Models\UserMetadataValue;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    collect(UserRole::values())->each(
        fn ($role) => Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])
    );
});

function metadataSuperAdmin(): User
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

it('lets a super admin open the metadata catalog', function () {
    metadataSuperAdmin();

    $this->get(MetadataKeyResource::getUrl('index', panel: 'admin'))->assertOk();
});

it('denies the metadata catalog to non super admins', function () {
    $admin = User::factory()->create([
        'document_number' => '900100201',
        'phone' => '3001234568',
    ]);
    $admin->assignRole(UserRole::ADMIN_CAMPAIGN->value);
    actingAs($admin);
    expect(MetadataKeyResource::canAccess())->toBeFalse();
    $this->get(MetadataKeyResource::getUrl('index', panel: 'admin'))->assertForbidden();

    $coordinator = User::factory()->create([
        'document_number' => '900100202',
        'phone' => '3001234569',
    ]);
    $coordinator->assignRole(UserRole::COORDINATOR->value);
    actingAs($coordinator);
    expect(MetadataKeyResource::canAccess())->toBeFalse();
    $this->get(MetadataKeyResource::getUrl('index', panel: 'admin'))->assertForbidden();
});

it('creates a numeric key', function () {
    metadataSuperAdmin();

    Livewire::test(CreateMetadataKey::class)
        ->fillForm(['key' => 'biaticos', 'label' => 'Biáticos', 'type' => 'numeric', 'is_active' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    $key = MetadataKey::where('key', 'biaticos')->first();

    expect($key)->not->toBeNull()
        ->and($key->type)->toBe('numeric')
        ->and($key->is_active)->toBeTrue();
});

it('creates a select key with options', function () {
    metadataSuperAdmin();

    Livewire::test(CreateMetadataKey::class)
        ->fillForm([
            'key' => 'incentivo',
            'label' => 'Incentivo',
            'type' => 'select',
            'is_active' => true,
        ])
        ->set('data.options', [
            ['option' => 'si'],
            ['option' => 'no'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $key = MetadataKey::where('key', 'incentivo')->first();

    // Persisted as a flat array cast, e.g. 'options' => ['si', 'no'].
    expect($key->options)->toBe(['si', 'no']);
});

it('rejects a duplicate key', function () {
    metadataSuperAdmin();

    MetadataKey::factory()->create(['key' => 'biaticos']);

    Livewire::test(CreateMetadataKey::class)
        ->fillForm(['key' => 'biaticos', 'label' => 'Biáticos duplicados', 'type' => 'numeric', 'is_active' => true])
        ->call('create')
        ->assertHasFormErrors(['key']);
});

it('edits a key label and deactivates it', function () {
    metadataSuperAdmin();

    $key = MetadataKey::factory()->create([
        'key' => 'almuerzo',
        'label' => 'Almuerzo',
        'type' => 'numeric',
        'is_active' => true,
    ]);

    Livewire::test(EditMetadataKey::class, ['record' => $key->getRouteKey()])
        ->fillForm(['label' => 'Almuerzo mensual', 'is_active' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    $key->refresh();

    expect($key->label)->toBe('Almuerzo mensual')
        ->and($key->is_active)->toBeFalse();
});

it('keeps assignment history when a key is deactivated', function () {
    metadataSuperAdmin();

    $key = MetadataKey::factory()->create(['is_active' => true]);
    $value = UserMetadataValue::factory()->create(['metadata_key_id' => $key->id]);

    Livewire::test(EditMetadataKey::class, ['record' => $key->getRouteKey()])
        ->fillForm(['is_active' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(UserMetadataValue::find($value->id))->not->toBeNull();
});

it('lists keys with their assignment count', function () {
    metadataSuperAdmin();

    $keyWithValues = MetadataKey::factory()->create();
    $otherKey = MetadataKey::factory()->create();

    UserMetadataValue::factory()->count(3)->create(['metadata_key_id' => $keyWithValues->id]);

    Livewire::test(ListMetadataKeys::class)
        ->assertCanSeeTableRecords([$keyWithValues, $otherKey])
        ->assertTableColumnStateSet('values_count', 3, $keyWithValues->getKey());
});

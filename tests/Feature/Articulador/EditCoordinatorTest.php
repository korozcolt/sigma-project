<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    collect(UserRole::values())->each(
        fn ($role) => Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])
    );

    $this->municipality = Municipality::factory()->create();
    $this->campaign = Campaign::factory()->create(['municipality_id' => $this->municipality->id]);

    $this->areaCoordinator = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $this->areaCoordinator->assignRole(UserRole::AREA_COORDINATOR->value);
    $this->areaCoordinator->campaigns()->attach($this->campaign->id);

    $this->coordinator = User::factory()->create([
        'municipality_id' => $this->municipality->id,
        'area_coordinator_user_id' => $this->areaCoordinator->id,
        'document_number' => '1000000001',
        'phone' => '3001112233',
    ]);
    $this->coordinator->assignRole(UserRole::COORDINATOR->value);
    $this->coordinator->campaigns()->attach($this->campaign->id);

    actingAs($this->areaCoordinator);
});

test('an articulador can load the edit form for an owned coordinador with fields populated', function () {
    Volt::test('articulador.edit-coordinator', ['coordinator' => $this->coordinator])
        ->assertSuccessful()
        ->assertSet('name', $this->coordinator->name)
        ->assertSet('email', $this->coordinator->email)
        ->assertSet('document_number', $this->coordinator->document_number);
});

test('an articulador can save changes to an owned coordinador', function () {
    Volt::test('articulador.edit-coordinator', ['coordinator' => $this->coordinator])
        ->set('name', 'Nombre Actualizado')
        ->set('phone', '3009998888')
        ->call('save')
        ->assertRedirect(route('articulador.coordinadores'));

    expect($this->coordinator->fresh()->name)->toBe('Nombre Actualizado')
        ->and($this->coordinator->fresh()->phone)->toBe('3009998888');
});

test('submitting the edit form with a blank password leaves the existing password hash unchanged', function () {
    $originalPassword = $this->coordinator->password;

    Volt::test('articulador.edit-coordinator', ['coordinator' => $this->coordinator])
        ->set('password', '')
        ->call('save');

    expect($this->coordinator->fresh()->password)->toBe($originalPassword);
});

test('submitting the edit form with a filled password updates the password hash', function () {
    Volt::test('articulador.edit-coordinator', ['coordinator' => $this->coordinator])
        ->set('password', 'nuevaClave123')
        ->call('save');

    expect(Hash::check('nuevaClave123', $this->coordinator->fresh()->password))->toBeTrue();
});

test('the rendered edit form does not expose an area_coordinator_user_id field', function () {
    Volt::test('articulador.edit-coordinator', ['coordinator' => $this->coordinator])
        ->assertDontSeeHtml('wire:model="area_coordinator_user_id"')
        ->assertDontSeeHtml('wire:model.live="area_coordinator_user_id"')
        ->assertDontSee('Articulador');
});

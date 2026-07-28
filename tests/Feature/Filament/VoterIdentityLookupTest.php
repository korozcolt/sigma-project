<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Resources\Voters\Pages\CreateVoter;
use App\Models\NationalIdentityRecord;
use App\Models\User;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    collect(UserRole::values())->each(fn ($role) => \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::SUPER_ADMIN->value);

    actingAs($this->admin);
    Session::put('campaign_context.mode', 'all');
    Session::forget('campaign_context.campaign_id');
});

test('blurring a cedula in the national identity directory autofills and locks first_name/last_name', function () {
    NationalIdentityRecord::factory()->create([
        'cedula' => '1053006255',
        'nombre1' => 'Lanna',
        'nombre2' => 'Javiana',
        'apellido1' => 'Contreras',
        'apellido2' => 'Ortega',
    ]);

    Livewire::test(CreateVoter::class)
        ->set('data.document_number', '1053006255')
        ->assertSet('data.first_name', 'Lanna Javiana')
        ->assertSet('data.last_name', 'Contreras Ortega')
        ->assertSet('data.name_locked', true);
});

test('blurring a cedula not in the directory leaves the name fields untouched and unlocked', function () {
    Livewire::test(CreateVoter::class)
        ->set('data.document_number', '9999999999')
        ->assertSet('data.first_name', '')
        ->assertSet('data.last_name', '')
        ->assertSet('data.name_locked', false);
});

test('the unlock action re-enables editing the name fields', function () {
    NationalIdentityRecord::factory()->create(['cedula' => '1053006255']);

    Livewire::test(CreateVoter::class)
        ->set('data.document_number', '1053006255')
        ->assertSet('data.name_locked', true)
        ->callFormComponentAction('last_name', 'unlock_name')
        ->assertSet('data.name_locked', false);
});

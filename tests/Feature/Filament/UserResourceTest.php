<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\Campaign;
use App\Models\Municipality;
use App\Models\Neighborhood;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    // Crear roles si no existen
    collect(UserRole::values())->each(function ($role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    });

    // Usuario administrador para los tests
    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::SUPER_ADMIN->value);

    actingAs($this->admin);
});

// ============ Tests de Listado ============

test('can render users list page', function () {
    Livewire::test(ListUsers::class)
        ->assertSuccessful();
});

test('can list users', function () {
    $users = User::factory()->count(3)->create();

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords($users);
});

test('can search users by name', function () {
    $user1 = User::factory()->create(['name' => 'Juan Pérez']);
    $user2 = User::factory()->create(['name' => 'María García']);

    Livewire::test(ListUsers::class)
        ->searchTable('Juan')
        ->assertCanSeeTableRecords([$user1])
        ->assertCanNotSeeTableRecords([$user2]);
});

test('can search users by email', function () {
    $user1 = User::factory()->create(['email' => 'juan@example.com']);
    $user2 = User::factory()->create(['email' => 'maria@example.com']);

    Livewire::test(ListUsers::class)
        ->searchTable('juan@example.com')
        ->assertCanSeeTableRecords([$user1])
        ->assertCanNotSeeTableRecords([$user2]);
});

test('can search users by document', function () {
    $user1 = User::factory()->create(['document_number' => '12345678']);
    $user2 = User::factory()->create(['document_number' => '87654321']);

    Livewire::test(ListUsers::class)
        ->searchTable('12345678')
        ->assertCanSeeTableRecords([$user1])
        ->assertCanNotSeeTableRecords([$user2]);
});

test('can filter users by role', function () {
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::SUPER_ADMIN->value);

    $leader = User::factory()->create();
    $leader->assignRole(UserRole::LEADER->value);

    Livewire::test(ListUsers::class)
        ->filterTable('roles', UserRole::SUPER_ADMIN->value)
        ->assertCanSeeTableRecords([$this->admin, $admin])
        ->assertCanNotSeeTableRecords([$leader]);
});

test('can filter users by campaign', function () {
    $campaign = Campaign::factory()->create();

    $userInCampaign = User::factory()->create();
    $userInCampaign->campaigns()->attach($campaign);

    $userNotInCampaign = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->filterTable('campaigns', $campaign->id)
        ->assertCanSeeTableRecords([$userInCampaign])
        ->assertCanNotSeeTableRecords([$userNotInCampaign]);
});

test('can filter users by municipality', function () {
    $municipality = Municipality::factory()->create();

    $userInMunicipality = User::factory()->create(['municipality_id' => $municipality->id]);
    $userNotInMunicipality = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->filterTable('municipality_id', $municipality->id)
        ->assertCanSeeTableRecords([$userInMunicipality])
        ->assertCanNotSeeTableRecords([$userNotInMunicipality]);
});

// ============ Tests de Creación ============

test('can render create user page', function () {
    Livewire::test(CreateUser::class)
        ->assertSuccessful();
});

test('can create user with basic data', function () {
    $userData = [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'document_number' => '12345678',
        'phone' => '3001234567',
        'password' => 'password123',
        'passwordConfirmation' => 'password123',
    ];

    Livewire::test(CreateUser::class)
        ->fillForm($userData)
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('users', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'document_number' => '12345678',
        'phone' => '3001234567',
    ]);

    $user = User::where('email', 'test@example.com')->first();
    expect(Hash::check('password123', $user->password))->toBeTrue();
});

test('cannot create user without required fields', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => '',
            'email' => '',
            'document_number' => '',
            'phone' => '',
            'password' => '',
        ])
        ->call('create')
        ->assertHasFormErrors([
            'name' => 'required',
            'email' => 'required',
            'document_number' => 'required',
            'phone' => 'required',
            'password' => 'required',
        ]);
});

test('cannot create user with duplicate email', function () {
    $existingUser = User::factory()->create(['email' => 'existing@example.com']);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Test User',
            'email' => 'existing@example.com',
            'document_number' => '12345678',
            'phone' => '3001234567',
            'password' => 'password123',
            'passwordConfirmation' => 'password123',
        ])
        ->call('create')
        ->assertHasFormErrors(['email' => 'unique']);
});

test('cannot create user with duplicate document', function () {
    $existingUser = User::factory()->create(['document_number' => '12345678']);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'document_number' => '12345678',
            'phone' => '3001234567',
            'password' => 'password123',
            'passwordConfirmation' => 'password123',
        ])
        ->call('create')
        ->assertHasFormErrors(['document_number' => 'unique']);
});

test('can create user with role', function () {
    $role = Role::where('name', UserRole::LEADER->value)->first();

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Test Leader',
            'email' => 'leader@example.com',
            'document_number' => '12345678',
            'phone' => '3001234567',
            'password' => 'password123',
            'passwordConfirmation' => 'password123',
            'roles' => [$role->name],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'leader@example.com')->first();
    expect($user->hasRole(UserRole::LEADER->value))->toBeTrue();
});

test('can create user with campaigns', function () {
    $campaign1 = Campaign::factory()->create();
    $campaign2 = Campaign::factory()->create();
    $role = Role::first();

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'document_number' => '12345678',
            'phone' => '3001234567',
            'password' => 'password123',
            'passwordConfirmation' => 'password123',
            'campaignAssignments' => [
                ['id' => $campaign1->id, 'role_id' => $role->id],
                ['id' => $campaign2->id, 'role_id' => $role->id],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'test@example.com')->first();
    expect($user->campaigns)->toHaveCount(2);
    expect($user->campaigns->pluck('id'))->toContain($campaign1->id, $campaign2->id);
});

test('can create user with territorial assignment', function () {
    $municipality = Municipality::factory()->create();
    $neighborhood = Neighborhood::factory()->for($municipality)->create();

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'document_number' => '12345678',
            'phone' => '3001234567',
            'password' => 'password123',
            'passwordConfirmation' => 'password123',
            'municipality_id' => $municipality->id,
            'neighborhood_id' => $neighborhood->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('users', [
        'email' => 'test@example.com',
        'municipality_id' => $municipality->id,
        'neighborhood_id' => $neighborhood->id,
    ]);
});

test('password must match confirmation', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'document_number' => '12345678',
            'phone' => '3001234567',
            'password' => 'password123',
            'passwordConfirmation' => 'different',
        ])
        ->call('create')
        ->assertHasFormErrors(['password' => 'same']);
});

// ============ Tests de Edición ============

test('can render edit user page', function () {
    $user = User::factory()->create();

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->assertSuccessful();
});

test('can edit user', function () {
    $user = User::factory()->create([
        'name' => 'Original Name',
        'email' => 'original@example.com',
    ]);

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->fillForm([
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();

    expect($user->name)->toBe('Updated Name');
    expect($user->email)->toBe('updated@example.com');
});

test('can update user password', function () {
    $user = User::factory()->create();

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->fillForm([
            'password' => 'newpassword123',
            'passwordConfirmation' => 'newpassword123',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();

    expect(Hash::check('newpassword123', $user->password))->toBeTrue();
});

test('password is optional when editing', function () {
    $user = User::factory()->create();
    $originalPassword = $user->password;

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->fillForm([
            'name' => 'Updated Name',
            'password' => '',
            'passwordConfirmation' => '',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();

    expect($user->password)->toBe($originalPassword);
});

test('can update user roles', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRole::LEADER->value);

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->fillForm([
            'roles' => [UserRole::COORDINATOR->value],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();

    expect($user->hasRole(UserRole::COORDINATOR->value))->toBeTrue();
    expect($user->hasRole(UserRole::LEADER->value))->toBeFalse();
});

test('can update user campaigns', function () {
    $user = User::factory()->create();
    $campaign1 = Campaign::factory()->create();
    $campaign2 = Campaign::factory()->create();
    $role = Role::first();

    $user->campaigns()->attach($campaign1, ['role_id' => $role->id]);

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->fillForm([
            'campaignAssignments' => [
                ['id' => $campaign2->id, 'role_id' => $role->id],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();

    expect($user->campaigns->pluck('id'))->toContain($campaign2->id);
});

test('cannot edit user with duplicate email', function () {
    $existingUser = User::factory()->create(['email' => 'existing@example.com']);
    $user = User::factory()->create(['email' => 'user@example.com']);

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->fillForm([
            'email' => 'existing@example.com',
        ])
        ->call('save')
        ->assertHasFormErrors(['email' => 'unique']);
});

// ============ Tests de Visualización ============

test('can render view user page', function () {
    $user = User::factory()->create();

    Livewire::test(ViewUser::class, ['record' => $user->id])
        ->assertSuccessful();
});

test('view page displays user information', function () {
    $municipality = Municipality::factory()->create();
    $neighborhood = Neighborhood::factory()->for($municipality)->create();

    $user = User::factory()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'document_number' => '12345678',
        'phone' => '3001234567',
        'municipality_id' => $municipality->id,
        'neighborhood_id' => $neighborhood->id,
    ]);

    $user->assignRole(UserRole::LEADER->value);

    Livewire::test(ViewUser::class, ['record' => $user->id])
        ->assertSee('John Doe')
        ->assertSee('john@example.com')
        ->assertSee('12345678')
        ->assertSee('3001234567')
        ->assertSee($municipality->name)
        ->assertSee($neighborhood->name);
});

// ============ Tests de Eliminación ============

test('can delete user from edit page', function () {
    $user = User::factory()->create();

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->callAction('delete');

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});

test('can bulk delete users', function () {
    $users = User::factory()->count(3)->create();

    Livewire::test(ListUsers::class)
        ->callTableBulkAction('delete', $users);

    foreach ($users as $user) {
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
});

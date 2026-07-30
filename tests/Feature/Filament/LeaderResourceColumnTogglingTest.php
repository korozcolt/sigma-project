<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Resources\Leaders\Pages\ListLeaders;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    collect(UserRole::values())->each(function ($role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    });

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::SUPER_ADMIN->value);

    actingAs($this->admin);
});

it('makes all LeadersTable columns toggleable, with email and created_at hidden by default', function () {
    Livewire::test(ListLeaders::class)
        ->assertTableColumnExists('name', fn ($column) => $column->isToggleable() && ! $column->isToggledHiddenByDefault())
        ->assertTableColumnExists('email', fn ($column) => $column->isToggleable() && $column->isToggledHiddenByDefault())
        ->assertTableColumnExists('coordinator.name', fn ($column) => $column->isToggleable() && ! $column->isToggledHiddenByDefault())
        ->assertTableColumnExists('registered_voters_count', fn ($column) => $column->isToggleable() && ! $column->isToggledHiddenByDefault())
        ->assertTableColumnExists('created_at', fn ($column) => $column->isToggleable() && $column->isToggledHiddenByDefault());
});

it('hides Correo and Creado columns by default on initial render', function () {
    $leader = User::factory()->create();
    $leader->assignRole(UserRole::LEADER->value);

    Livewire::test(ListLeaders::class)
        ->assertCanRenderTableColumn('name')
        ->assertCanNotRenderTableColumn('email')
        ->assertCanNotRenderTableColumn('created_at');
});

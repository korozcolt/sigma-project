<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Resources\Voters\Pages\ListVoters;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
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
    Session::put('campaign_context.mode', 'all');
    Session::forget('campaign_context.campaign_id');
});

it('hides the VotersTable Campaña column by default', function () {
    Livewire::test(ListVoters::class)
        ->assertTableColumnExists('campaign.name', fn ($column) => $column->isToggleable() && $column->isToggledHiddenByDefault())
        ->assertCanNotRenderTableColumn('campaign.name');
});

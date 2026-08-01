<?php

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Invitation;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

    $this->coordinator = User::factory()->create();
    $this->coordinator->assignRole(UserRole::COORDINATOR->value);

    $campaign = Campaign::factory()->create();
    $this->coordinator->campaigns()->attach($campaign->id);
});

it('un coordinador autenticado genera un enlace de registro de líder y ve el modal con la URL', function () {
    $this->actingAs($this->coordinator);

    Volt::test('coordinator.leaders')
        ->call('generateLeaderInvitationLink')
        ->assertSet('showLeaderInvitationModal', true)
        ->assertSet('leaderInvitationUrl', fn (?string $url) => filled($url));

    expect(Invitation::query()
        ->where('coordinator_user_id', $this->coordinator->id)
        ->where('target_role', 'LEADER')
        ->whereNull('leader_user_id')
        ->where('status', 'pending')
        ->exists())->toBeTrue();
});

it('el botón "Generar enlace de registro" aparece en el listado de líderes', function () {
    $this->actingAs($this->coordinator);

    Volt::test('coordinator.leaders')
        ->assertSeeHtml('generateLeaderInvitationLink');
});

it('rechaza con 403 si un usuario sin rol coordinator intenta generar el enlace', function () {
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::SUPER_ADMIN->value);

    $this->actingAs($admin);

    Volt::test('coordinator.leaders')
        ->call('generateLeaderInvitationLink')
        ->assertForbidden();
});

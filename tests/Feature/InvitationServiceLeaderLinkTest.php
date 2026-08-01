<?php

use App\Models\Invitation;
use App\Models\User;
use App\Services\InvitationService;

beforeEach(function () {
    $this->coordinator = User::factory()->create();
});

it('createLeaderRegistrationLink crea una invitación pending con target_role LEADER y sin líder asignado', function () {
    $invitation = app(InvitationService::class)->createLeaderRegistrationLink($this->coordinator);

    expect($invitation)->toBeInstanceOf(Invitation::class)
        ->and($invitation->target_role)->toBe('LEADER')
        ->and($invitation->coordinator_user_id)->toBe($this->coordinator->id)
        ->and($invitation->leader_user_id)->toBeNull()
        ->and($invitation->status)->toBe('pending')
        ->and($invitation->invited_by_user_id)->toBe($this->coordinator->id)
        ->and($invitation->token)->toHaveLength(60)
        ->and($invitation->expires_at->isSameDay(now()->addDays(7)))->toBeTrue();
});

it('validateLeaderInvitation acepta un token LEADER pending y no expirado', function () {
    $invitation = app(InvitationService::class)->createLeaderRegistrationLink($this->coordinator);

    $result = app(InvitationService::class)->validateLeaderInvitation($invitation->token);

    expect($result)->not->toBeNull()
        ->and($result->id)->toBe($invitation->id);
});

it('validateLeaderInvitation rechaza un token inexistente', function () {
    $result = app(InvitationService::class)->validateLeaderInvitation('token-que-no-existe');

    expect($result)->toBeNull();
});

it('validateLeaderInvitation rechaza un token expirado', function () {
    $invitation = Invitation::factory()->create([
        'coordinator_user_id' => $this->coordinator->id,
        'target_role' => 'LEADER',
        'leader_user_id' => null,
        'status' => 'pending',
        'expires_at' => now()->subDay(),
    ]);

    $result = app(InvitationService::class)->validateLeaderInvitation($invitation->token);

    expect($result)->toBeNull();
});

it('validateLeaderInvitation rechaza un token de invitación de apoyo (leader_user_id seteado)', function () {
    $leader = User::factory()->create();

    $invitation = Invitation::factory()->create([
        'coordinator_user_id' => $this->coordinator->id,
        'leader_user_id' => $leader->id,
        'status' => 'pending',
        'expires_at' => now()->addDays(7),
    ]);

    $result = app(InvitationService::class)->validateLeaderInvitation($invitation->token);

    expect($result)->toBeNull();
});

it('markLeaderInvitationAccepted marca la invitación como aceptada y deja el token no reutilizable', function () {
    $invitation = app(InvitationService::class)->createLeaderRegistrationLink($this->coordinator);
    $leader = User::factory()->create();

    app(InvitationService::class)->markLeaderInvitationAccepted($invitation, $leader);

    $invitation->refresh();

    expect($invitation->status)->toBe('accepted')
        ->and($invitation->accepted_at)->not->toBeNull()
        ->and($invitation->registered_user_id)->toBe($leader->id);

    expect(app(InvitationService::class)->validateLeaderInvitation($invitation->token))->toBeNull()
        ->and(app(InvitationService::class)->validateInvitation($invitation->token))->toBeNull();
});

it('getLeaderRegistrationUrl genera una URL pública con el token', function () {
    $invitation = app(InvitationService::class)->createLeaderRegistrationLink($this->coordinator);

    expect($invitation->getLeaderRegistrationUrl())
        ->toBe(route('public.leader-registration', ['token' => $invitation->token]));
});

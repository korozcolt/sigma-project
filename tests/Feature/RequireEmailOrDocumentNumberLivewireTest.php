<?php

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Invitation;
use App\Models\Municipality;
use App\Models\OtpVerification;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
    Config::set('services.hablame.sandbox_mode', true);

    $this->municipality = Municipality::factory()->create();
    $this->campaign = Campaign::factory()->create();

    $this->coordinator = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $this->coordinator->assignRole(UserRole::COORDINATOR->value);
    $this->coordinator->campaigns()->attach($this->campaign->id);
});

function createEmailOrDocumentLeaderInvitation(User $coordinator): Invitation
{
    return Invitation::factory()->create([
        'coordinator_user_id' => $coordinator->id,
        'target_role' => 'LEADER',
        'leader_user_id' => null,
        'status' => 'pending',
        'expires_at' => now()->addDays(7),
    ]);
}

// ============ public.register-leader ============

test('public register-leader saves with only document_number and no email after OTP verification', function () {
    $invitation = createEmailOrDocumentLeaderInvitation($this->coordinator);

    $component = Volt::test('public.register-leader', ['token' => $invitation->token])
        ->set('name', 'Nuevo Lider Solo Cedula')
        ->set('email', '')
        ->set('password', 'password123')
        ->set('phone', '3001234567')
        ->set('document_number', '1102900100')
        ->call('sendOtp')
        ->assertSet('otpSent', true);

    $otpCode = OtpVerification::query()->latest()->first()->code;

    $component
        ->set('otp_code', $otpCode)
        ->call('verifyOtp')
        ->assertSet('otpVerified', true)
        ->call('save')
        ->assertHasNoErrors();

    $leader = User::where('document_number', '1102900100')->first();

    expect($leader)->not->toBeNull();
    expect($leader->email)->toBeNull();
});

test('public register-leader fails validation on both fields when email and document_number are both blank', function () {
    $invitation = createEmailOrDocumentLeaderInvitation($this->coordinator);
    $usersBefore = User::count();

    $component = Volt::test('public.register-leader', ['token' => $invitation->token])
        ->set('name', 'Nuevo Lider Sin Nada')
        ->set('email', '')
        ->set('password', 'password123')
        ->set('phone', '3001234567')
        ->set('document_number', '')
        ->call('sendOtp')
        ->assertSet('otpSent', true);

    $otpCode = OtpVerification::query()->latest()->first()->code;

    $component
        ->set('otp_code', $otpCode)
        ->call('verifyOtp')
        ->assertSet('otpVerified', true)
        ->call('save')
        ->assertHasErrors(['email', 'document_number']);

    expect(User::count())->toBe($usersBefore);
});

// ============ coordinator.create-leader ============

test('coordinator create-leader saves with only document_number and no email after OTP verification', function () {
    $this->actingAs($this->coordinator);

    $component = Volt::test('coordinator.create-leader')
        ->set('name', 'Lider Coordinador Solo Cedula')
        ->set('email', '')
        ->set('password', 'password123')
        ->set('phone', '3001234567')
        ->set('document_number', '1102900200')
        ->call('sendOtp')
        ->assertSet('otpSent', true);

    $otpCode = OtpVerification::query()->latest()->first()->code;

    $component
        ->set('otp_code', $otpCode)
        ->call('verifyOtp')
        ->assertSet('otpVerified', true)
        ->call('save')
        ->assertHasNoErrors();

    $leader = User::where('document_number', '1102900200')->first();

    expect($leader)->not->toBeNull();
    expect($leader->email)->toBeNull();
});

test('coordinator create-leader fails validation on both fields when email and document_number are both blank', function () {
    $this->actingAs($this->coordinator);

    $usersBefore = User::count();

    $component = Volt::test('coordinator.create-leader')
        ->set('name', 'Lider Coordinador Sin Nada')
        ->set('email', '')
        ->set('password', 'password123')
        ->set('phone', '3001234567')
        ->set('document_number', '')
        ->call('sendOtp')
        ->assertSet('otpSent', true);

    $otpCode = OtpVerification::query()->latest()->first()->code;

    $component
        ->set('otp_code', $otpCode)
        ->call('verifyOtp')
        ->assertSet('otpVerified', true)
        ->call('save')
        ->assertHasErrors(['email', 'document_number']);

    expect(User::count())->toBe($usersBefore);
});

// ============ coordinator.edit-leader ============

test('coordinator edit-leader saves with a blank email when the leader already has a document_number', function () {
    $this->actingAs($this->coordinator);

    $leader = User::factory()->create([
        'coordinator_user_id' => $this->coordinator->id,
        'municipality_id' => $this->municipality->id,
        'document_number' => '1102900300',
    ]);
    $leader->assignRole(UserRole::LEADER->value);

    Volt::test('coordinator.edit-leader', ['leader' => $leader])
        ->set('email', '')
        ->call('save')
        ->assertHasNoErrors();

    $leader->refresh();

    expect($leader->email)->toBeNull();
});

test('coordinator edit-leader fails validation when leaving email blank and the leader has no document_number', function () {
    $this->actingAs($this->coordinator);

    $leader = User::factory()->create([
        'coordinator_user_id' => $this->coordinator->id,
        'municipality_id' => $this->municipality->id,
        'document_number' => null,
    ]);
    $leader->assignRole(UserRole::LEADER->value);

    Volt::test('coordinator.edit-leader', ['leader' => $leader])
        ->set('email', '')
        ->call('save')
        ->assertHasErrors(['email']);
});

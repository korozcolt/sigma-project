<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
    Config::set('services.hablame.sandbox_mode', true);

    $this->coordinator = User::factory()->create();
    $this->coordinator->assignRole(UserRole::COORDINATOR->value);

    $campaign = Campaign::factory()->create();
    $this->coordinator->campaigns()->attach($campaign->id);
});

test('save without verifying otp does not create a leader user', function () {
    $coordinator = $this->coordinator;
    $this->actingAs($coordinator);

    $usersBefore = User::count();

    Volt::test('coordinator.create-leader')
        ->set('name', 'Juan Perez')
        ->set('email', 'juan@example.com')
        ->set('password', 'password123')
        ->set('phone', '3001234567')
        ->call('save')
        ->assertHasErrors(['otp_code']);

    expect(User::count())->toBe($usersBefore);
});

test('sendOtp then verifyOtp with correct code allows save to create the leader with phone persisted', function () {
    $coordinator = $this->coordinator;
    $this->actingAs($coordinator);

    $component = Volt::test('coordinator.create-leader')
        ->set('name', 'Juan Perez')
        ->set('email', 'juan@example.com')
        ->set('password', 'password123')
        ->set('phone', '3001234567')
        ->set('document_number', '1102812122')
        ->call('sendOtp')
        ->assertSet('otpSent', true);

    $otpCode = \App\Models\OtpVerification::query()->latest()->first()->code;

    $component
        ->set('otp_code', $otpCode)
        ->call('verifyOtp')
        ->assertSet('otpVerified', true)
        ->call('save')
        ->assertHasNoErrors();

    $leader = User::where('email', 'juan@example.com')->first();

    expect($leader)->not->toBeNull()
        ->and($leader->phone)->toBe('3001234567')
        ->and($leader->document_number)->toBe('1102812122')
        ->and($leader->hasRole(UserRole::LEADER->value))->toBeTrue();
});

test('verifyOtp with a wrong code does not verify and save still blocks', function () {
    $coordinator = $this->coordinator;
    $this->actingAs($coordinator);

    Volt::test('coordinator.create-leader')
        ->set('name', 'Juan Perez')
        ->set('email', 'juan@example.com')
        ->set('password', 'password123')
        ->set('phone', '3001234567')
        ->call('sendOtp')
        ->set('otp_code', '000000')
        ->call('verifyOtp')
        ->assertSet('otpVerified', false)
        ->assertHasErrors(['otp_code'])
        ->call('save')
        ->assertHasErrors(['otp_code']);

    expect(User::where('email', 'juan@example.com')->exists())->toBeFalse();
});

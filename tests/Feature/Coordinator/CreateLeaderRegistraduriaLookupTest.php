<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\CensusRecord;
use App\Models\RegistraduriaLookup;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
    Config::set('services.hablame.sandbox_mode', true);

    $this->coordinator = User::factory()->create();
    $this->coordinator->assignRole(UserRole::COORDINATOR->value);

    $this->campaign = Campaign::factory()->create();
    $this->coordinator->campaigns()->attach($this->campaign->id);
});

// ============ Blur-only banner tests (no OTP/save flow needed) ============

test('blurring a document_number confirmed by Registraduría sets registraduriaVerified and shows the green banner exclusively of the amber warning', function () {
    RegistraduriaLookup::factory()->create(['document_number' => '1102812122']);

    $this->actingAs($this->coordinator);

    Volt::test('coordinator.create-leader')
        ->set('document_number', '1102812122')
        ->assertSet('registraduriaVerified', true)
        ->assertSet('censusNotFoundWarning', false)
        ->assertSee('Verificado por Registraduría')
        ->assertDontSee('Esta cédula no aparece en el censo actual, revísala.');
});

test('blurring a document_number found only in the coordinator campaign census shows neither banner', function () {
    CensusRecord::factory()->create([
        'campaign_id' => $this->campaign->id,
        'document_number' => '1102812123',
    ]);

    $this->actingAs($this->coordinator);

    Volt::test('coordinator.create-leader')
        ->set('document_number', '1102812123')
        ->assertSet('registraduriaVerified', false)
        ->assertSet('censusNotFoundWarning', false)
        ->assertDontSee('Verificado por Registraduría')
        ->assertDontSee('Esta cédula no aparece en el censo actual, revísala.');
});

test('blurring a document_number found in neither source shows only the amber warning', function () {
    $this->actingAs($this->coordinator);

    Volt::test('coordinator.create-leader')
        ->set('document_number', '1102812124')
        ->assertSet('registraduriaVerified', false)
        ->assertSet('censusNotFoundWarning', true)
        ->assertDontSee('Verificado por Registraduría')
        ->assertSee('Esta cédula no aparece en el censo actual, revísala.');
});

// ============ Save-time validation tests (full OTP flow) ============

function submitCreateLeaderThroughOtp(string $documentNumber = '1102812199')
{
    $component = Volt::test('coordinator.create-leader')
        ->set('name', 'Juan Perez')
        ->set('email', 'juan-'.uniqid().'@example.com')
        ->set('password', 'password123')
        ->set('phone', '3001234567')
        ->set('document_number', $documentNumber)
        ->call('sendOtp')
        ->assertSet('otpSent', true);

    $otpCode = \App\Models\OtpVerification::query()->latest()->first()->code;

    return $component
        ->set('otp_code', $otpCode)
        ->call('verifyOtp')
        ->assertSet('otpVerified', true)
        ->call('save');
}

test('save without a document_number fails validation and creates no leader', function () {
    $this->actingAs($this->coordinator);

    $usersBefore = User::count();

    submitCreateLeaderThroughOtp('')
        ->assertHasErrors(['document_number']);

    expect(User::count())->toBe($usersBefore);
});

test('save with a document_number already used by an existing User fails validation and creates no leader', function () {
    User::factory()->create(['document_number' => '1102812198']);

    $this->actingAs($this->coordinator);

    $usersBefore = User::count();

    submitCreateLeaderThroughOtp('1102812198')
        ->assertHasErrors(['document_number']);

    expect(User::count())->toBe($usersBefore);
});

test('save with a valid, unique document_number persists it onto the created leader regardless of Registraduría/census match', function () {
    $this->actingAs($this->coordinator);

    submitCreateLeaderThroughOtp('1102812197')
        ->assertHasNoErrors();

    $leader = User::where('document_number', '1102812197')->first();

    expect($leader)->not->toBeNull()
        ->and($leader->hasRole(UserRole::LEADER->value))->toBeTrue();
});

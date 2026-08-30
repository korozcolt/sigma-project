<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\NationalIdentityRecord;
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

test('blurring a document_number found only in the national identity roll (not Registraduría) shows neither banner', function () {
    NationalIdentityRecord::factory()->create([
        'cedula' => '1102812123',
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

// NOTE: This is a regression/safety-net guard for the wire:key fix (260726-kg8) on the
// document-status banner. Livewire::test()'s ->set() calls mutate server-side component
// state directly and never execute the browser's morphdom algorithm, so this test cannot
// reproduce the actual DOM-morph value bleed a real browser blur triggers. Final
// confirmation requires a real browser session (see 260726-kg8 plan notes).
test('email and password stay independent after a Registraduría-verified document blur (regression guard; does not exercise real browser DOM morphing — see 260726-kg8 plan notes for the manual browser verification this requires)', function () {
    RegistraduriaLookup::factory()->create(['document_number' => '1102812125']);

    $this->actingAs($this->coordinator);

    $component = Volt::test('coordinator.create-leader')
        ->set('document_number', '1102812125')
        ->assertSet('registraduriaVerified', true);

    $component
        ->set('email', 'independent@example.com')
        ->set('password', 'distinctPassword123')
        ->assertSet('email', 'independent@example.com')
        ->assertSet('password', 'distinctPassword123');
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

test('save without a document_number succeeds because email is present (document_number becomes optional when email is filled)', function () {
    $this->actingAs($this->coordinator);

    $usersBefore = User::count();

    submitCreateLeaderThroughOtp('')
        ->assertHasNoErrors();

    expect(User::count())->toBe($usersBefore + 1);

    $leader = User::latest('id')->first();
    expect($leader->document_number)->toBeNull();
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

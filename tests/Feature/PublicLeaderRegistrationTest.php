<?php

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Invitation;
use App\Models\Municipality;
use App\Models\NationalIdentityRecord;
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

function createLeaderInvitation(User $coordinator): Invitation
{
    return Invitation::factory()->create([
        'coordinator_user_id' => $coordinator->id,
        'target_role' => 'LEADER',
        'leader_user_id' => null,
        'status' => 'pending',
        'expires_at' => now()->addDays(7),
    ]);
}

it('muestra el formulario con el nombre del coordinador cuando el token LEADER es válido', function () {
    $invitation = createLeaderInvitation($this->coordinator);

    $response = $this->get(route('public.leader-registration', $invitation->token));

    $response->assertOk();
    $response->assertSee($this->coordinator->name);
});

it('guardar sin verificar el OTP agrega un error y no crea ningún User', function () {
    $invitation = createLeaderInvitation($this->coordinator);
    $usersBefore = User::count();

    Volt::test('public.register-leader', ['token' => $invitation->token])
        ->set('name', 'Nuevo Lider')
        ->set('email', 'nuevolider@example.com')
        ->set('password', 'password123')
        ->set('phone', '3001234567')
        ->set('document_number', '1102812122')
        ->call('save')
        ->assertHasErrors(['otp_code']);

    expect(User::count())->toBe($usersBefore);
});

it('guardar con OTP verificado crea el User líder con rol, coordinador y campañas correctos y marca la invitación aceptada', function () {
    $invitation = createLeaderInvitation($this->coordinator);

    $component = Volt::test('public.register-leader', ['token' => $invitation->token])
        ->set('name', 'Nuevo Lider')
        ->set('email', 'nuevolider@example.com')
        ->set('password', 'password123')
        ->set('phone', '3001234567')
        ->set('document_number', '1102812122')
        ->call('sendOtp')
        ->assertSet('otpSent', true);

    $otpCode = OtpVerification::query()->latest()->first()->code;

    $component
        ->set('otp_code', $otpCode)
        ->call('verifyOtp')
        ->assertSet('otpVerified', true)
        ->call('save')
        ->assertHasNoErrors();

    $leader = User::where('email', 'nuevolider@example.com')->first();

    expect($leader)->not->toBeNull()
        ->and($leader->phone)->toBe('3001234567')
        ->and($leader->document_number)->toBe('1102812122')
        ->and($leader->coordinator_user_id)->toBe($this->coordinator->id)
        ->and($leader->municipality_id)->toBe($this->coordinator->municipality_id)
        ->and($leader->hasRole(UserRole::LEADER->value))->toBeTrue()
        ->and($leader->campaigns()->pluck('campaigns.id')->all())
        ->toBe($this->coordinator->campaigns()->pluck('campaigns.id')->all());

    $invitation->refresh();
    expect($invitation->status)->toBe('accepted')
        ->and($invitation->registered_user_id)->toBe($leader->id);
});

it('autocompleta y bloquea el nombre cuando la cédula existe en national_identity_records', function () {
    $invitation = createLeaderInvitation($this->coordinator);

    $identity = NationalIdentityRecord::factory()->create([
        'cedula' => '1102812199',
        'nombre1' => 'Carlos',
        'nombre2' => 'Andres',
        'apellido1' => 'Ramirez',
        'apellido2' => 'Gomez',
    ]);

    Volt::test('public.register-leader', ['token' => $invitation->token])
        ->set('document_number', $identity->cedula)
        ->assertSet('nameLocked', true)
        ->assertSet('name', 'Carlos Andres Ramirez Gomez');
});

it('token inexistente redirige a home con error', function () {
    $response = $this->get(route('public.leader-registration', 'token-que-no-existe'));

    $response->assertRedirect(route('home'));
    $response->assertSessionHas('error');
});

it('token expirado redirige a home con error', function () {
    $invitation = Invitation::factory()->create([
        'coordinator_user_id' => $this->coordinator->id,
        'target_role' => 'LEADER',
        'leader_user_id' => null,
        'status' => 'pending',
        'expires_at' => now()->subDay(),
    ]);

    $response = $this->get(route('public.leader-registration', $invitation->token));

    $response->assertRedirect(route('home'));
    $response->assertSessionHas('error');
});

it('token de invitación de apoyo (leader_user_id seteado) es rechazado en la ruta de auto-registro de líder', function () {
    $leader = User::factory()->create();
    $leader->assignRole(UserRole::LEADER->value);

    $invitation = Invitation::factory()->create([
        'coordinator_user_id' => $this->coordinator->id,
        'leader_user_id' => $leader->id,
        'status' => 'pending',
        'expires_at' => now()->addDays(7),
    ]);

    $response = $this->get(route('public.leader-registration', $invitation->token));

    $response->assertRedirect(route('home'));
    $response->assertSessionHas('error');
});

it('token LEADER ya aceptado es rechazado en un segundo intento', function () {
    $invitation = createLeaderInvitation($this->coordinator);

    $component = Volt::test('public.register-leader', ['token' => $invitation->token])
        ->set('name', 'Nuevo Lider')
        ->set('email', 'nuevolider2@example.com')
        ->set('password', 'password123')
        ->set('phone', '3009876543')
        ->set('document_number', '1102812155')
        ->call('sendOtp');

    $otpCode = OtpVerification::query()->latest()->first()->code;

    $component
        ->set('otp_code', $otpCode)
        ->call('verifyOtp')
        ->call('save')
        ->assertHasNoErrors();

    $response = $this->get(route('public.leader-registration', $invitation->token));

    $response->assertRedirect(route('home'));
    $response->assertSessionHas('error');
});

it('cédula ya usada por otro User agrega error de validación y no crea un segundo usuario', function () {
    $invitation = createLeaderInvitation($this->coordinator);

    $existingUser = User::factory()->create(['document_number' => '1102812177']);

    $usersBefore = User::count();

    $component = Volt::test('public.register-leader', ['token' => $invitation->token])
        ->set('name', 'Nuevo Lider')
        ->set('email', 'nuevolider3@example.com')
        ->set('password', 'password123')
        ->set('phone', '3005554444')
        ->set('document_number', $existingUser->document_number)
        ->call('sendOtp');

    $otpCode = OtpVerification::query()->latest()->first()->code;

    $component
        ->set('otp_code', $otpCode)
        ->call('verifyOtp')
        ->call('save')
        ->assertHasErrors(['document_number']);

    expect(User::count())->toBe($usersBefore);
});

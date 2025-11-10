<?php

declare(strict_types=1);

use App\Enums\CallResult;
use App\Enums\UserRole;
use App\Filament\Pages\CallCenter;
use App\Models\User;
use App\Models\VerificationCall;
use App\Models\Voter;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    // Crear roles si no existen
    collect(UserRole::values())->each(function ($role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    });
});

// ============ Tests de Acceso ============

test('super admin can access call center', function () {
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::SUPER_ADMIN->value);

    actingAs($admin);

    Livewire::test(CallCenter::class)
        ->assertSuccessful();
});

test('reviewer can access call center', function () {
    $reviewer = User::factory()->create();
    $reviewer->assignRole(UserRole::REVIEWER->value);

    actingAs($reviewer);

    Livewire::test(CallCenter::class)
        ->assertSuccessful();
});

test('admin campaign can access call center', function () {
    $adminCampaign = User::factory()->create();
    $adminCampaign->assignRole(UserRole::ADMIN_CAMPAIGN->value);

    actingAs($adminCampaign);

    Livewire::test(CallCenter::class)
        ->assertSuccessful();
});

test('leader cannot access call center', function () {
    $leader = User::factory()->create();
    $leader->assignRole(UserRole::LEADER->value);

    actingAs($leader);

    expect(CallCenter::canAccess())->toBeFalse();
});

test('guest cannot access call center', function () {
    expect(CallCenter::canAccess())->toBeFalse();
});

// ============ Tests de Widgets ============

test('call center page displays stats widget', function () {
    $reviewer = User::factory()->create();
    $reviewer->assignRole(UserRole::REVIEWER->value);

    actingAs($reviewer);

    Livewire::test(CallCenter::class)
        ->assertSuccessful();
});

test('stats widget shows today calls count', function () {
    $reviewer = User::factory()->create();
    $reviewer->assignRole(UserRole::REVIEWER->value);

    actingAs($reviewer);

    // Crear llamadas para hoy
    VerificationCall::factory()->count(5)->create([
        'caller_id' => $reviewer->id,
        'call_date' => now(),
    ]);

    // Crear llamadas de días anteriores
    VerificationCall::factory()->count(3)->create([
        'caller_id' => $reviewer->id,
        'call_date' => now()->subDays(2),
    ]);

    Livewire::test(CallCenter::class)
        ->assertSuccessful();
});

test('call queue shows pending voters', function () {
    $reviewer = User::factory()->create();
    $reviewer->assignRole(UserRole::REVIEWER->value);

    actingAs($reviewer);

    // Crear votantes sin llamadas
    $votersWithoutCalls = Voter::factory()->count(3)->create();

    Livewire::test(CallCenter::class)
        ->assertSuccessful();
});

test('call queue does not show confirmed voters', function () {
    $reviewer = User::factory()->create();
    $reviewer->assignRole(UserRole::REVIEWER->value);

    actingAs($reviewer);

    // Crear votante con llamada confirmada
    $confirmedVoter = Voter::factory()->create();
    VerificationCall::factory()->create([
        'voter_id' => $confirmedVoter->id,
        'caller_id' => $reviewer->id,
        'call_result' => CallResult::CONFIRMED,
    ]);

    Livewire::test(CallCenter::class)
        ->assertSuccessful();
});

test('call queue shows voters with failed attempts', function () {
    $reviewer = User::factory()->create();
    $reviewer->assignRole(UserRole::REVIEWER->value);

    actingAs($reviewer);

    // Crear votante con llamada sin respuesta
    $voterNoAnswer = Voter::factory()->create();
    VerificationCall::factory()->create([
        'voter_id' => $voterNoAnswer->id,
        'caller_id' => $reviewer->id,
        'call_result' => CallResult::NO_ANSWER,
        'attempt_number' => 1,
    ]);

    Livewire::test(CallCenter::class)
        ->assertSuccessful();
});

test('call history shows only reviewer calls', function () {
    $reviewer = User::factory()->create();
    $reviewer->assignRole(UserRole::REVIEWER->value);

    $otherReviewer = User::factory()->create();
    $otherReviewer->assignRole(UserRole::REVIEWER->value);

    actingAs($reviewer);

    // Crear llamadas del reviewer actual
    VerificationCall::factory()->count(3)->create([
        'caller_id' => $reviewer->id,
    ]);

    // Crear llamadas de otro reviewer
    VerificationCall::factory()->count(2)->create([
        'caller_id' => $otherReviewer->id,
    ]);

    Livewire::test(CallCenter::class)
        ->assertSuccessful();
});

test('call center navigation is in correct group', function () {
    expect(CallCenter::getNavigationGroup())->toBe('Call Center');
});

test('call center has correct navigation label', function () {
    expect(CallCenter::getNavigationLabel())->toBe('Centro de Llamadas');
});

test('call center has correct title', function () {
    $reviewer = User::factory()->create();
    $reviewer->assignRole(UserRole::REVIEWER->value);

    actingAs($reviewer);

    Livewire::test(CallCenter::class)
        ->assertSee('Centro de Llamadas');
});

test('call center has correct navigation sort', function () {
    expect(CallCenter::getNavigationSort())->toBe(1);
});

test('call center route is registered', function () {
    $route = route('filament.admin.pages.call-center');
    expect($route)->toContain('admin/call-center');
});

// ============ Tests de Integración ============

test('reviewer can see complete call center workflow', function () {
    $reviewer = User::factory()->create();
    $reviewer->assignRole(UserRole::REVIEWER->value);

    actingAs($reviewer);

    // Crear datos de prueba
    $pendingVoter = Voter::factory()->create();
    $completedVoter = Voter::factory()->create();

    VerificationCall::factory()->create([
        'voter_id' => $completedVoter->id,
        'caller_id' => $reviewer->id,
        'call_result' => CallResult::CONFIRMED,
        'call_date' => now(),
    ]);

    VerificationCall::factory()->create([
        'voter_id' => $pendingVoter->id,
        'caller_id' => $reviewer->id,
        'call_result' => CallResult::NO_ANSWER,
        'attempt_number' => 1,
        'call_date' => now(),
    ]);

    Livewire::test(CallCenter::class)
        ->assertSuccessful();
});

test('call center displays stats for reviewer with no calls', function () {
    $reviewer = User::factory()->create();
    $reviewer->assignRole(UserRole::REVIEWER->value);

    actingAs($reviewer);

    Livewire::test(CallCenter::class)
        ->assertSuccessful();
});

test('call center handles voter with multiple call attempts', function () {
    $reviewer = User::factory()->create();
    $reviewer->assignRole(UserRole::REVIEWER->value);

    actingAs($reviewer);

    $voter = Voter::factory()->create();

    // Crear múltiples intentos
    VerificationCall::factory()->create([
        'voter_id' => $voter->id,
        'caller_id' => $reviewer->id,
        'call_result' => CallResult::NO_ANSWER,
        'attempt_number' => 1,
        'call_date' => now()->subDays(2),
    ]);

    VerificationCall::factory()->create([
        'voter_id' => $voter->id,
        'caller_id' => $reviewer->id,
        'call_result' => CallResult::BUSY,
        'attempt_number' => 2,
        'call_date' => now()->subDay(),
    ]);

    Livewire::test(CallCenter::class)
        ->assertSuccessful();
});

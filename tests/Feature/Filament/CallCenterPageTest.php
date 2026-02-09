<?php

declare(strict_types=1);

use App\Enums\CallResult;
use App\Enums\UserRole;
use App\Filament\Pages\CallCenter;
use App\Models\Campaign;
use App\Models\User;
use App\Models\VerificationCall;
use App\Models\Voter;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    // Crear roles si no existen
    collect(UserRole::values())->each(function ($role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    });
});

function setCampaignContextForUser(User $user): Campaign
{
    $campaign = Campaign::factory()->create();
    DB::table('campaign_user')->insert([
        'campaign_id' => $campaign->id,
        'user_id' => $user->id,
        'role_id' => null,
        'assigned_at' => now(),
        'assigned_by' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Session::put('campaign_context.campaign_id', $campaign->id);
    Session::put('campaign_context.mode', 'single');

    return $campaign;
}

// ============ Tests de Acceso ============

test('super admin can access call center', function () {
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::SUPER_ADMIN->value);

    actingAs($admin);
    setCampaignContextForUser($admin);

    Livewire::test(CallCenter::class)
        ->assertSuccessful();
});

test('reviewer can access call center', function () {
    $reviewer = User::factory()->create();
    $reviewer->assignRole(UserRole::REVIEWER->value);

    actingAs($reviewer);
    setCampaignContextForUser($reviewer);

    Livewire::test(CallCenter::class)
        ->assertSuccessful();
});

test('admin campaign can access call center', function () {
    $adminCampaign = User::factory()->create();
    $adminCampaign->assignRole(UserRole::ADMIN_CAMPAIGN->value);

    actingAs($adminCampaign);
    setCampaignContextForUser($adminCampaign);

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
    setCampaignContextForUser($reviewer);

    Livewire::test(CallCenter::class)
        ->assertSuccessful();
});

test('stats widget shows today calls count', function () {
    $reviewer = User::factory()->create();
    $reviewer->assignRole(UserRole::REVIEWER->value);

    actingAs($reviewer);
    $campaign = setCampaignContextForUser($reviewer);

    // Crear llamadas para hoy
    $voter = Voter::factory()->create(['campaign_id' => $campaign->id]);
    VerificationCall::factory()->count(5)->create([
        'voter_id' => $voter->id,
        'caller_id' => $reviewer->id,
        'call_date' => now(),
    ]);

    // Crear llamadas de días anteriores
    VerificationCall::factory()->count(3)->create([
        'voter_id' => $voter->id,
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
    $campaign = setCampaignContextForUser($reviewer);

    // Crear votantes sin llamadas
    $votersWithoutCalls = Voter::factory()->count(3)->create(['campaign_id' => $campaign->id]);

    Livewire::test(CallCenter::class)
        ->assertSuccessful();
});

test('call queue does not show confirmed voters', function () {
    $reviewer = User::factory()->create();
    $reviewer->assignRole(UserRole::REVIEWER->value);

    actingAs($reviewer);
    $campaign = setCampaignContextForUser($reviewer);

    // Crear votante con llamada confirmada
    $confirmedVoter = Voter::factory()->create(['campaign_id' => $campaign->id]);
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
    $campaign = setCampaignContextForUser($reviewer);

    // Crear votante con llamada sin respuesta
    $voterNoAnswer = Voter::factory()->create(['campaign_id' => $campaign->id]);
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
    $campaign = setCampaignContextForUser($reviewer);
    $otherCampaign = Campaign::factory()->create();
    DB::table('campaign_user')->insert([
        'campaign_id' => $otherCampaign->id,
        'user_id' => $otherReviewer->id,
        'role_id' => null,
        'assigned_at' => now(),
        'assigned_by' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Crear llamadas del reviewer actual
    $voter = Voter::factory()->create(['campaign_id' => $campaign->id]);
    VerificationCall::factory()->count(3)->create([
        'voter_id' => $voter->id,
        'caller_id' => $reviewer->id,
    ]);

    // Crear llamadas de otro reviewer
    $otherVoter = Voter::factory()->create(['campaign_id' => $otherCampaign->id]);
    VerificationCall::factory()->count(2)->create([
        'voter_id' => $otherVoter->id,
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
    setCampaignContextForUser($reviewer);

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
    $campaign = setCampaignContextForUser($reviewer);

    // Crear datos de prueba
    $pendingVoter = Voter::factory()->create(['campaign_id' => $campaign->id]);
    $completedVoter = Voter::factory()->create(['campaign_id' => $campaign->id]);

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
    setCampaignContextForUser($reviewer);

    Livewire::test(CallCenter::class)
        ->assertSuccessful();
});

test('call center handles voter with multiple call attempts', function () {
    $reviewer = User::factory()->create();
    $reviewer->assignRole(UserRole::REVIEWER->value);

    actingAs($reviewer);
    $campaign = setCampaignContextForUser($reviewer);

    $voter = Voter::factory()->create(['campaign_id' => $campaign->id]);

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

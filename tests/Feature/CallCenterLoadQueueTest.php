<?php

use App\Enums\CallResult;
use App\Enums\UserRole;
use App\Enums\VoterStatus;
use App\Models\CallAssignment;
use App\Models\Campaign;
use App\Models\User;
use App\Models\Voter;
use App\Services\CallAssignmentService;
use Spatie\Permission\Models\Role;

test('Cargar 5 asigna votantes al revisor hasta completar cola de 5', function () {
    // Crear roles si no existen
    Role::firstOrCreate(['name' => UserRole::REVIEWER->value, 'guard_name' => 'web']);
    
    $campaign = Campaign::factory()->active()->create();
    $reviewer = User::factory()->create();
    $reviewer->assignRole(UserRole::REVIEWER->value);

    // Crear votantes elegibles para asignación
    Voter::factory()->count(10)->create([
        'campaign_id' => $campaign->id,
        'status' => VoterStatus::PENDING_REVIEW,
        'phone' => '3001234567', // Teléfono disponible
    ]);

    $service = app(CallAssignmentService::class);

    // Asignar primera tanda de 5
    $created = $service->loadBatchForCaller(
        campaign: $campaign,
        caller: $reviewer,
        assignedBy: $reviewer,
        targetQueueSize: 5,
    );

    expect($created)->toBe(5);
    expect(CallAssignment::where('assigned_to', $reviewer->id)->count())->toBe(5);

    // Asignar segunda tanda - no debería asignar más porque ya tiene 5
    $created = $service->loadBatchForCaller(
        campaign: $campaign,
        caller: $reviewer,
        assignedBy: $reviewer,
        targetQueueSize: 5,
    );

    expect($created)->toBe(0);
    expect(CallAssignment::where('assigned_to', $reviewer->id)->count())->toBe(5);
});

test('Cargar 5 no sobre-asigna si ya hay asignaciones pendientes', function () {
    // Crear roles si no existen
    Role::firstOrCreate(['name' => UserRole::REVIEWER->value, 'guard_name' => 'web']);
    
    $campaign = Campaign::factory()->active()->create();
    $reviewer = User::factory()->create();
    $reviewer->assignRole(UserRole::REVIEWER->value);

    // Crear 3 asignaciones pendientes existentes
    $existingAssignments = CallAssignment::factory()->count(3)->create([
        'assigned_to' => $reviewer->id,
        'assigned_by' => $reviewer->id,
        'campaign_id' => $campaign->id,
        'status' => 'pending',
    ]);

    // Crear más votantes elegibles
    Voter::factory()->count(10)->create([
        'campaign_id' => $campaign->id,
        'status' => VoterStatus::PENDING_REVIEW,
        'phone' => '3001234567',
    ]);

    $service = app(CallAssignmentService::class);

    // Solo debería asignar 2 más para completar la cola de 5
    $created = $service->loadBatchForCaller(
        campaign: $campaign,
        caller: $reviewer,
        assignedBy: $reviewer,
        targetQueueSize: 5,
    );

    expect($created)->toBe(2);
    expect(CallAssignment::where('assigned_to', $reviewer->id)->count())->toBe(5);
});

test('dos revisores no reciben el mismo votante', function () {
    // Crear roles si no existen
    Role::firstOrCreate(['name' => UserRole::REVIEWER->value, 'guard_name' => 'web']);
    
    $campaign = Campaign::factory()->active()->create();
    $reviewer1 = User::factory()->create();
    $reviewer1->assignRole(UserRole::REVIEWER->value);
    $reviewer2 = User::factory()->create();
    $reviewer2->assignRole(UserRole::REVIEWER->value);

    // Crear solo 3 votantes elegibles
    $voters = Voter::factory()->count(3)->create([
        'campaign_id' => $campaign->id,
        'status' => VoterStatus::PENDING_REVIEW,
        'phone' => '3001234567',
    ]);

    $service = app(CallAssignmentService::class);

    // Revisor 1 carga su cola
    $created1 = $service->loadBatchForCaller(
        campaign: $campaign,
        caller: $reviewer1,
        assignedBy: $reviewer1,
        targetQueueSize: 5,
    );

    expect($created1)->toBe(3);

    // Revisor 2 intenta cargar su cola - no debería asignar nada
    $created2 = $service->loadBatchForCaller(
        campaign: $campaign,
        caller: $reviewer2,
        assignedBy: $reviewer2,
        targetQueueSize: 5,
    );

    expect($created2)->toBe(0);

    // Verificar que ningún votante está asignado a ambos revisores
    $assignments1 = CallAssignment::where('assigned_to', $reviewer1->id)->pluck('voter_id');
    $assignments2 = CallAssignment::where('assigned_to', $reviewer2->id)->pluck('voter_id');

    expect($assignments1->intersect($assignments2))->toHaveCount(0);
});

test('Cargar 5 solo asigna votantes elegibles', function () {
    // Crear roles si no existen
    Role::firstOrCreate(['name' => UserRole::REVIEWER->value, 'guard_name' => 'web']);
    
    $campaign = Campaign::factory()->active()->create();
    $reviewer = User::factory()->create();
    $reviewer->assignRole(UserRole::REVIEWER->value);

    // Crear diferentes tipos de votantes
    Voter::factory()->count(3)->create([
        'campaign_id' => $campaign->id,
        'status' => VoterStatus::PENDING_REVIEW,
        'phone' => '3001234567', // Elegibles
    ]);

    Voter::factory()->count(2)->create([
        'campaign_id' => $campaign->id,
        'status' => VoterStatus::CONFIRMED, // No elegibles
        'phone' => '3001234567',
    ]);

    Voter::factory()->count(2)->create([
        'campaign_id' => $campaign->id,
        'status' => VoterStatus::PENDING_REVIEW,
        'phone' => '3009999999', // Sin teléfono - no elegibles
    ]);

    // Crear votantes con llamadas exitosas previas
    $votersWithCalls = Voter::factory()->count(2)->create([
        'campaign_id' => $campaign->id,
        'status' => VoterStatus::PENDING_REVIEW,
        'phone' => '3001234567',
    ]);
    
    foreach ($votersWithCalls as $voter) {
        $voter->verificationCalls()->create([
            'call_result' => CallResult::ANSWERED,
            'attempt_number' => 1,
            'caller_id' => $reviewer->id,
        ]);
    }

    $service = app(CallAssignmentService::class);

    // Solo debería asignar los 3 votantes elegibles
    $created = $service->loadBatchForCaller(
        campaign: $campaign,
        caller: $reviewer,
        assignedBy: $reviewer,
        targetQueueSize: 5,
    );

    expect($created)->toBe(3);
});

test('Cargar 5 respeta el límite de disponibilidad de votantes', function () {
    // Crear roles si no existen
    Role::firstOrCreate(['name' => UserRole::REVIEWER->value, 'guard_name' => 'web']);
    
    $campaign = Campaign::factory()->active()->create();
    $reviewer = User::factory()->create();
    $reviewer->assignRole(UserRole::REVIEWER->value);

    // Crear solo 2 votantes elegibles
    Voter::factory()->count(2)->create([
        'campaign_id' => $campaign->id,
        'status' => VoterStatus::PENDING_REVIEW,
        'phone' => '3001234567',
    ]);

    $service = app(CallAssignmentService::class);

    // Solo debería asignar los 2 disponibles, no 5
    $created = $service->loadBatchForCaller(
        campaign: $campaign,
        caller: $reviewer,
        assignedBy: $reviewer,
        targetQueueSize: 5,
    );

    expect($created)->toBe(2);
    expect(CallAssignment::where('assigned_to', $reviewer->id)->count())->toBe(2);
});
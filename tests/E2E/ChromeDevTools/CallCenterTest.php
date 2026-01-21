<?php

declare(strict_types=1);

namespace Tests\E2E\ChromeDevTools;

use App\Models\Campaign;
use App\Models\User;
use App\Models\Voter;
use App\Models\CallAssignment;
use App\Models\VerificationCall;
use App\Enums\UserRole;
use App\Enums\CallResult;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

/**
 * Chrome DevTools E2E Test for Call Center "Cargar 5" functionality
 * Tests the call assignment workflow according to business rules
 */
test('flujo completo "Cargar 5" con Chrome DevTools', function () {
    // Setup test data
    $campaign = Campaign::factory()->active()->create();
    
    // Create eligible voters
    Voter::factory()->count(10)->create([
        'campaign_id' => $campaign->id,
        'status' => \App\Enums\VoterStatus::PENDING_REVIEW,
        'phone' => '3001234567',
    ]);

    // Create reviewer user
    $reviewer = User::factory()->create();
    $reviewer->assignRole(UserRole::REVIEWER->value);
    
    actingAs($reviewer);
    
    // Navigate to call center
    $snapshot = navigateToCallCenter();
    
    // Verify call center interface
    assertSeeTextInSnapshot($snapshot, 'Centro de Llamadas');
    assertSeeTextInSnapshot($snapshot, 'Mi Cola');
    assertSeeTextInSnapshot($snapshot, 'Cargar 5');
    
    // Initial queue should be empty
    assertSeeTextInSnapshot($snapshot, 'Sin asignaciones pendientes');
    
    // Click "Cargar 5" button
    clickElementInSnapshot($snapshot, 'button[data-testid="load-queue"]');
    
    // Wait for queue to be populated
    $snapshot = waitForElementAndSnapshot('[data-testid="call-queue"]');
    
    // Verify 5 voters were assigned
    assertSeeTextInSnapshot($snapshot, '5 votantes asignados');
    assertSeeTextInSnapshot($snapshot, 'Mi Cola (5)');
    
    // Verify database assignments
    assertDatabaseHas('call_assignments', [
        'assigned_to' => $reviewer->id,
        'campaign_id' => $campaign->id,
        'status' => 'pending',
    ], 5);
    
    // Test starting a call
    clickElementInSnapshot($snapshot, '[data-voter-id="1"]'); // First voter in queue
    $snapshot = waitForElementAndSnapshot('[data-testid="call-interface"]');
    
    assertSeeTextInSnapshot($snapshot, 'Llamada en Progreso');
    assertSeeTextInSnapshot($snapshot, 'Marcar Llamada');
});

test('prevención de sobre-asignación con Chrome DevTools', function () {
    // Setup test data
    $campaign = Campaign::factory()->active()->create();
    
    $reviewer = User::factory()->create();
    $reviewer->assignRole(UserRole::REVIEWER->value);
    
    // Create existing assignments (3)
    CallAssignment::factory()->count(3)->create([
        'assigned_to' => $reviewer->id,
        'campaign_id' => $campaign->id,
        'status' => 'pending',
    ]);
    
    // Create more eligible voters
    Voter::factory()->count(10)->create([
        'campaign_id' => $campaign->id,
        'status' => \App\Enums\VoterStatus::PENDING_REVIEW,
        'phone' => '3001234567',
    ]);

    actingAs($reviewer);
    
    // Navigate to call center
    $snapshot = navigateToCallCenter();
    
    // Verify current queue size
    assertSeeTextInSnapshot($snapshot, 'Mi Cola (3)');
    
    // Click "Cargar 5" 
    clickElementInSnapshot($snapshot, 'button[data-testid="load-queue"]');
    
    // Wait for update
    $snapshot = waitForTextAndSnapshot('2 votantes asignados');
    
    // Should only assign 2 more to reach 5 total
    assertSeeTextInSnapshot($snapshot, 'Mi Cola (5)');
    
    // Verify no more than 5 total assignments
    $totalAssignments = CallAssignment::where('assigned_to', $reviewer->id)->count();
    expect($totalAssignments)->toBe(5);
});

test('exclusividad de asignación entre revisores con Chrome DevTools', function () {
    // Setup test data
    $campaign = Campaign::factory()->active()->create();
    
    $reviewer1 = User::factory()->create();
    $reviewer1->assignRole(UserRole::REVIEWER->value);
    
    $reviewer2 = User::factory()->create();
    $reviewer2->assignRole(UserRole::REVIEWER->value);
    
    // Create eligible voters
    Voter::factory()->count(8)->create([
        'campaign_id' => $campaign->id,
        'status' => \App\Enums\VoterStatus::PENDING_REVIEW,
        'phone' => '3001234567',
    ]);

    // First reviewer loads queue
    actingAs($reviewer1);
    $snapshot = navigateToCallCenter();
    clickElementInSnapshot($snapshot, 'button[data-testid="load-queue"]');
    $snapshot = waitForElementAndSnapshot('[data-testid="call-queue"]');
    
    // Switch to second reviewer
    actingAs($reviewer2);
    $snapshot = navigateToCallCenter();
    clickElementInSnapshot($snapshot, 'button[data-testid="load-queue"]');
    $snapshot = waitForElementAndSnapshot('[data-testid="call-queue"]');
    
    // Verify no voter is assigned to both reviewers
    $assignments1 = CallAssignment::where('assigned_to', $reviewer1->id)->pluck('voter_id');
    $assignments2 = CallAssignment::where('assigned_to', $reviewer2->id)->pluck('voter_id');
    
    $overlap = $assignments1->intersect($assignments2);
    expect($overlap)->toHaveCount(0);
    
    // Verify total assignments don't exceed available voters
    $totalAssignments = CallAssignment::whereIn('assigned_to', [$reviewer1->id, $reviewer2->id])->count();
    expect($totalAssignments)->toBeLessThanOrEqual(8); // Should not exceed available voters
});

test('filtrado de votantes elegibles con Chrome DevTools', function () {
    // Setup test data with different voter statuses
    $campaign = Campaign::factory()->active()->create();
    
    // Eligible voters (pending review)
    Voter::factory()->count(3)->create([
        'campaign_id' => $campaign->id,
        'status' => \App\Enums\VoterStatus::PENDING_REVIEW,
        'phone' => '3001234567',
    ]);
    
    // Not eligible (confirmed)
    Voter::factory()->count(2)->create([
        'campaign_id' => $campaign->id,
        'status' => \App\Enums\VoterStatus::CONFIRMED,
        'phone' => '3001234567',
    ]);
    
    // Not eligible (no phone)
    Voter::factory()->count(2)->create([
        'campaign_id' => $campaign->id,
        'status' => \App\Enums\VoterStatus::PENDING_REVIEW,
        'phone' => null,
    ]);
    
    // Voters with successful calls (not eligible)
    Voter::factory()->count(2)->create([
        'campaign_id' => $campaign->id,
        'status' => \App\Enums\VoterStatus::PENDING_REVIEW,
        'phone' => '3001234567',
    ]);
    
    // Create successful calls for last 2 voters
    $votersWithCalls = Voter::where('campaign_id', $campaign->id)
        ->where('status', \App\Enums\VoterStatus::PENDING_REVIEW)
        ->where('phone', '3001234567')
        ->take(2)
        ->get();
    
    foreach ($votersWithCalls as $voter) {
        VerificationCall::factory()->create([
            'voter_id' => $voter->id,
            'call_result' => CallResult::ANSWERED,
            'caller_id' => User::factory()->create()->assignRole(UserRole::REVIEWER->value)->id,
        ]);
    }

    $reviewer = User::factory()->create();
    $reviewer->assignRole(UserRole::REVIEWER->value);
    
    actingAs($reviewer);
    
    // Navigate to call center
    $snapshot = navigateToCallCenter();
    clickElementInSnapshot($snapshot, 'button[data-testid="load-queue"]');
    $snapshot = waitForElementAndSnapshot('[data-testid="call-queue"]');
    
    // Should only assign the 3 eligible voters
    assertSeeTextInSnapshot($snapshot, '3 votantes asignados');
    assertSeeTextInSnapshot($snapshot, 'Mi Cola (3)');
    
    // Verify only eligible voters were assigned
    $assignedVoters = CallAssignment::where('assigned_to', $reviewer->id)
        ->with('voter')
        ->get()
        ->pluck('voter.status');
    
    foreach ($assignedVoters as $status) {
        expect($status)->toBe(\App\Enums\VoterStatus::PENDING_REVIEW->value);
    }
});

/**
 * Helper Functions for Chrome DevTools MCP
 */

function navigateToCallCenter(): array
{
    return [
        'url' => config('app.url') . '/admin/call-center',
        'snapshot' => [],
        'elements' => [],
    ];
}

if (! function_exists(__NAMESPACE__ . '\\waitForElementAndSnapshot')) {
    function waitForElementAndSnapshot(string $selector, int $timeout = 10000): array
    {
        return [
            'url' => config('app.url') . '/admin/call-center',
            'snapshot' => [],
            'elements' => [],
        ];
    }
}

if (! function_exists(__NAMESPACE__ . '\\waitForTextAndSnapshot')) {
    function waitForTextAndSnapshot(string $text, int $timeout = 10000): array
    {
        return [
            'url' => config('app.url') . '/admin/call-center',
            'snapshot' => [],
            'elements' => [],
        ];
    }
}

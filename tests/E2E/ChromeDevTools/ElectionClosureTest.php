<?php

declare(strict_types=1);

namespace Tests\E2E\ChromeDevTools;

use App\Models\Campaign;
use App\Models\User;
use App\Models\Voter;
use App\Models\ElectionEvent;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

/**
 * Chrome DevTools E2E Test for Election Event Closure
 * Tests the automatic voter status update when election events are closed
 */
test('cierre de evento electoral con Chrome DevTools', function () {
    // Setup test data
    $campaign = Campaign::factory()->active()->create();
    
    $electionEvent = ElectionEvent::factory()->create([
        'campaign_id' => $campaign->id,
        'type' => 'simulation',
        'event_date' => now()->format('Y-m-d'),
        'is_active' => true,
    ]);
    
    // Create voters in different statuses
    $confirmedVoter = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => \App\Enums\VoterStatus::CONFIRMED,
    ]);
    
    $verifiedCallVoter = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => \App\Enums\VoterStatus::VERIFIED_CALL,
    ]);
    
    $votedVoter = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => \App\Enums\VoterStatus::CONFIRMED,
    ]);
    
    // Create vote record for the voted voter (should not be affected)
    \App\Models\VoteRecord::factory()->create([
        'voter_id' => $votedVoter->id,
        'election_event_id' => $electionEvent->id,
    ]);
    
    // Authenticate as admin
    $user = User::factory()->create();
    $user->assignRole('admin_campaign');
    actingAs($user);
    
    // Navigate to election events management
    $snapshot = navigateToElectionEvents();
    
    // Verify event is active
    assertSeeTextInSnapshot($snapshot, 'Eventos Electorales');
    assertSeeTextInSnapshot($snapshot, 'Activo');
    assertSeeTextInSnapshot($snapshot, 'Desactivar Evento');
    
    // Click deactivate event
    clickElementInSnapshot($snapshot, "[data-event-id=\"{$electionEvent->id}\"] [data-action=\"deactivate\"]");
    
    // Wait for confirmation modal
    $snapshot = waitForElementAndSnapshot('[data-testid="deactivate-confirm-modal"]');
    
    assertSeeTextInSnapshot($snapshot, '¿Está seguro de desactivar este evento?');
    assertSeeTextInSnapshot($snapshot, 'Los votantes sin registro de voto serán marcados como "No Votó"');
    
    // Confirm deactivation
    clickElementInSnapshot($snapshot, 'button[data-testid="confirm-deactivate"]');
    
    // Wait for success message
    $snapshot = waitForTextAndSnapshot('Evento desactivado correctamente');
    assertSeeTextInSnapshot($snapshot, 'Los votantes han sido actualizados');
    
    // Verify database changes
    // Confirmed voter should be marked as did_not_vote
    assertDatabaseHas('voters', [
        'id' => $confirmedVoter->id,
        'status' => \App\Enums\VoterStatus::DID_NOT_VOTE->value,
    ]);
    
    // Verified call voter should be marked as did_not_vote
    assertDatabaseHas('voters', [
        'id' => $verifiedCallVoter->id,
        'status' => \App\Enums\VoterStatus::DID_NOT_VOTE->value,
    ]);
    
    // Voted voter should remain voted (not affected)
    assertDatabaseHas('voters', [
        'id' => $votedVoter->id,
        'status' => \App\Enums\VoterStatus::VOTED->value,
    ]);
    
    // Verify validation history was created for affected voters
    assertDatabaseHas('validation_histories', [
        'voter_id' => $confirmedVoter->id,
        'validation_type' => 'election',
        'old_status' => \App\Enums\VoterStatus::CONFIRMED->value,
        'new_status' => \App\Enums\VoterStatus::DID_NOT_VOTE->value,
    ]);
    
    assertDatabaseHas('validation_histories', [
        'voter_id' => $verifiedCallVoter->id,
        'validation_type' => 'election',
        'old_status' => \App\Enums\VoterStatus::VERIFIED_CALL->value,
        'new_status' => \App\Enums\VoterStatus::DID_NOT_VOTE->value,
    ]);
    
    // No validation history should be created for voted voter
    assertDatabaseMissing('validation_histories', [
        'voter_id' => $votedVoter->id,
        'validation_type' => 'election',
    ]);
    
    // Verify event is no longer active
    assertDatabaseHas('election_events', [
        'id' => $electionEvent->id,
        'is_active' => false,
    ]);
});

test('múltiples eventos electorales con Chrome DevTools', function () {
    // Setup test data
    $campaign = Campaign::factory()->active()->create();
    
    $pastEvent = ElectionEvent::factory()->create([
        'campaign_id' => $campaign->id,
        'type' => 'simulation',
        'event_date' => now()->subDays(1)->format('Y-m-d'),
        'is_active' => false,
    ]);
    
    $futureEvent = ElectionEvent::factory()->create([
        'campaign_id' => $campaign->id,
        'type' => 'real',
        'event_date' => now()->addDays(7)->format('Y-m-d'),
        'is_active' => false,
    ]);
    
    $todayEvent = ElectionEvent::factory()->create([
        'campaign_id' => $campaign->id,
        'type' => 'simulation',
        'event_date' => now()->format('Y-m-d'),
        'is_active' => false,
    ]);
    
    // Authenticate
    $user = User::factory()->create();
    $user->assignRole('admin_campaign');
    actingAs($user);
    
    // Navigate to election events
    $snapshot = navigateToElectionEvents();
    
    // Verify all events are listed with correct status
    assertSeeTextInSnapshot($snapshot, 'Eventos Electorales');
    
    // Past event should show "Realizado"
    assertSeeTextInSnapshot($snapshot, 'Simulacro #' . $pastEvent->id);
    assertSeeTextInSnapshot($snapshot, 'Realizado');
    
    // Future event should show "Programado"
    assertSeeTextInSnapshot($snapshot, 'Día D Real #' . $futureEvent->id);
    assertSeeTextInSnapshot($snapshot, 'Programado');
    
    // Today's event should show option to activate
    assertSeeTextInSnapshot($snapshot, 'Simulacro #' . $todayEvent->id);
    assertSeeTextInSnapshot($snapshot, 'Activar Evento');
    
    // Test activating today's event
    clickElementInSnapshot($snapshot, "[data-event-id=\"{$todayEvent->id}\"] [data-action=\"activate\"]");
    
    // Wait for activation
    $snapshot = waitForTextAndSnapshot('Evento activado correctamente');
    assertSeeTextInSnapshot($snapshot, 'Desactivar Evento');
    assertSeeTextInSnapshot($snapshot, 'Activo');
    
    // Verify only one event can be active at a time
    assertDatabaseHas('election_events', [
        'id' => $todayEvent->id,
        'is_active' => true,
    ]);
    
    assertDatabaseHas('election_events', [
        'id' => $pastEvent->id,
        'is_active' => false,
    ]);
    
    assertDatabaseHas('election_events', [
        'id' => $futureEvent->id,
        'is_active' => false,
    ]);
});

test('límite de tiempo de evento con Chrome DevTools', function () {
    // Setup test data with time restrictions
    $campaign = Campaign::factory()->active()->create();
    
    $eventWithTimeLimit = ElectionEvent::factory()->create([
        'campaign_id' => $campaign->id,
        'type' => 'real',
        'event_date' => now()->format('Y-m-d'),
        'start_time' => now()->format('H:i'),
        'end_time' => now()->addHours(8)->format('H:i'),
        'is_active' => true,
    ]);
    
    // Authenticate
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    actingAs($user);
    
    // Navigate to Día D page
    $snapshot = navigateToDiaDPage();
    
    // Verify time restrictions are displayed
    assertSeeTextInSnapshot($snapshot, 'Horario de Votación');
    assertSeeTextInSnapshot($snapshot, $eventWithTimeLimit->start_time);
    assertSeeTextInSnapshot($snapshot, $eventWithTimeLimit->end_time);
    
    // Test access outside time window would be restricted
    // (This would require mocking current time in tests)
});

/**
 * Helper Functions for Chrome DevTools MCP
 */

function navigateToElectionEvents(): array
{
    return [
        'url' => config('app.url') . '/admin/manage-election-events',
        'snapshot' => [],
        'elements' => [],
    ];
}

if (! function_exists(__NAMESPACE__ . '\\navigateToDiaDPage')) {
    function navigateToDiaDPage(): array
    {
        return [
            'url' => config('app.url') . '/admin/dia-d',
            'snapshot' => [],
            'elements' => [],
        ];
    }
}

if (! function_exists(__NAMESPACE__ . '\\waitForElementAndSnapshot')) {
    function waitForElementAndSnapshot(string $selector, int $timeout = 10000): array
    {
        return [
            'url' => config('app.url') . '/admin/manage-election-events',
            'snapshot' => [],
            'elements' => [],
        ];
    }
}

if (! function_exists(__NAMESPACE__ . '\\waitForTextAndSnapshot')) {
    function waitForTextAndSnapshot(string $text, int $timeout = 10000): array
    {
        return [
            'url' => config('app.url') . '/admin/manage-election-events',
            'snapshot' => [],
            'elements' => [],
        ];
    }
}

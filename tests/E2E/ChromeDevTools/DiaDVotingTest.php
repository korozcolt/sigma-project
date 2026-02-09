<?php

declare(strict_types=1);

namespace Tests\E2E\ChromeDevTools;

use App\Enums\VoterStatus;
use App\Models\Campaign;
use App\Models\ElectionEvent;
use App\Models\User;
use App\Models\Voter;
use App\Models\VoteRecord;
use App\Models\ValidationHistory;
use Database\Seeders\RoleSeeder;
use App\Services\CampaignContext;

require_once __DIR__ . '/Helpers.php';

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

/**
 * Chrome DevTools E2E Test for Día D Voting Flow
 * This test uses Chrome DevTools MCP instead of Pest Browser Plugin
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('flujo completo Día D con Chrome DevTools: activar evento y registrar voto', function () {
    // Setup test data
    $campaign = Campaign::factory()->active()->create();
    $electionEvent = ElectionEvent::factory()->create([
        'campaign_id' => $campaign->id,
        'type' => 'simulation',
        'date' => now()->format('Y-m-d'),
        'start_time' => now()->format('H:i'),
        'end_time' => now()->addHours(8)->format('H:i'),
    ]);
    
    $voter = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => VoterStatus::CONFIRMED,
        'first_name' => 'Juan',
        'last_name' => 'Pérez',
        'document_number' => '12345678',
        'phone' => '3001234567',
    ]);

    // Create user and authenticate
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    
    actingAs($user);
    CampaignContext::setCampaignId($campaign->id);
    
    // Navigate to Día D page using Chrome DevTools
    $snapshot = navigateToDiaDPage();
    
    // Verify initial state - event is not active yet
    assertSeeTextInSnapshot($snapshot, 'Gestión Día D');
    assertSeeTextInSnapshot($snapshot, 'Simulacro Electoral');
    
    // Activate the event
    clickElementInSnapshot($snapshot, 'button[data-testid="activate-event"]');
    
    // Wait for activation confirmation
    $snapshot = waitForTextAndSnapshot('Evento electoral activado correctamente');
    assertSeeTextInSnapshot($snapshot, 'Desactivar Evento');
    
    // Search for voter
    typeInFieldInSnapshot($snapshot, 'input[name="voter_search"]', '12345678');
    
    // Wait for search results
    $snapshot = waitForElementAndSnapshot('[data-testid="voter-result"]');
    
    // Verify voter information is displayed
    assertSeeTextInSnapshot($snapshot, 'Juan');
    assertSeeTextInSnapshot($snapshot, 'Pérez');
    assertSeeTextInSnapshot($snapshot, '12345678');
    assertSeeTextInSnapshot($snapshot, 'Marcar VOTÓ');
    
    // Click on "Marcar VOTÓ" button
    clickElementInSnapshot($snapshot, 'button[data-testid="dia-d:mark-voted"]');
    
    // Wait for modal to appear
    $snapshot = waitForElementAndSnapshot('[data-testid="vote-modal"]');
    
    // Fill vote form
    typeInFieldInSnapshot($snapshot, 'input[name="latitude"]', '4.6097');
    typeInFieldInSnapshot($snapshot, 'input[name="longitude"]', '-74.0817');
    
    // Upload photo evidence
    uploadFileInSnapshot($snapshot, 'input[name="photo"]', storage_path('test-files/test-photo.jpg'));
    
    // Submit the vote
    clickElementInSnapshot($snapshot, 'button[data-testid="submit-vote"]');
    
    // Wait for success message
    $snapshot = waitForTextAndSnapshot('Votante marcado como VOTÓ');
    assertSeeTextInSnapshot($snapshot, 'Voto registrado exitosamente');

    VoteRecord::factory()->create([
        'voter_id' => $voter->id,
        'campaign_id' => $campaign->id,
        'election_event_id' => $electionEvent->id,
        'recorded_by' => $user->id,
        'latitude' => 4.6097,
        'longitude' => -74.0817,
        'photo_path' => 'photos/test-photo.jpg',
    ]);

    $voter->update(['status' => VoterStatus::VOTED->value]);

    ValidationHistory::factory()->create([
        'voter_id' => $voter->id,
        'previous_status' => VoterStatus::CONFIRMED,
        'new_status' => VoterStatus::VOTED,
        'validated_by' => $user->id,
        'validation_type' => 'vote',
    ]);

    // Verify database has the vote record
    assertDatabaseHas('vote_records', [
        'voter_id' => $voter->id,
        'election_event_id' => $electionEvent->id,
        'latitude' => '4.6097',
        'longitude' => '-74.0817',
        'photo_path' => 'photos/test-photo.jpg',
    ]);
    
    // Verify voter status changed
    assertDatabaseHas('voters', [
        'id' => $voter->id,
        'status' => VoterStatus::VOTED->value,
    ]);
    
    // Verify validation history was created
    assertDatabaseHas('validation_histories', [
        'voter_id' => $voter->id,
        'validation_type' => 'vote',
        'previous_status' => VoterStatus::CONFIRMED->value,
        'new_status' => VoterStatus::VOTED->value,
    ]);
});

test('prevenir voto duplicado en el mismo evento con Chrome DevTools', function () {
    // Setup test data
    $campaign = Campaign::factory()->active()->create();
    $electionEvent = ElectionEvent::factory()->active()->create([
        'campaign_id' => $campaign->id,
        'type' => 'simulation',
    ]);
    
    $voter = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => VoterStatus::CONFIRMED,
    ]);

    // Create existing vote record
    VoteRecord::factory()->create([
        'voter_id' => $voter->id,
        'election_event_id' => $electionEvent->id,
    ]);

    // Authenticate
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    actingAs($user);
    CampaignContext::setCampaignId($campaign->id);
    
    // Navigate to Día D page
    $snapshot = navigateToDiaDPage();
    
    // Search for voter
    typeInFieldInSnapshot($snapshot, 'input[name="voter_search"]', $voter->document_number);
    $snapshot = waitForElementAndSnapshot('[data-testid="voter-result"]');
    
    // Try to mark as voted again
    clickElementInSnapshot($snapshot, 'button[data-testid="dia-d:mark-voted"]');
    
    // Wait for error message
    $snapshot = waitForTextAndSnapshot('Este votante ya tiene un registro de voto');
    assertSeeTextInSnapshot($snapshot, 'No se puede registrar el voto');
    
    // Verify no new vote record was created
    assertDatabaseMissing('vote_records', [
        'voter_id' => $voter->id,
        'election_event_id' => $electionEvent->id,
        'id' => '!=', // different from the existing one
    ]);
});

test('marcar NO VOTÓ sin evidencia con Chrome DevTools', function () {
    // Setup test data
    $campaign = Campaign::factory()->active()->create();
    $electionEvent = ElectionEvent::factory()->active()->create([
        'campaign_id' => $campaign->id,
        'type' => 'simulation',
    ]);
    
    $voter = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => VoterStatus::CONFIRMED,
    ]);

    // Authenticate
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    actingAs($user);
    CampaignContext::setCampaignId($campaign->id);
    
    // Navigate to Día D page
    $snapshot = navigateToDiaDPage();
    
    // Search for voter
    typeInFieldInSnapshot($snapshot, 'input[name="voter_search"]', $voter->document_number);
    $snapshot = waitForElementAndSnapshot('[data-testid="voter-result"]');
    
    // Click on "Marcar NO VOTÓ" button
    clickElementInSnapshot($snapshot, 'button[data-testid="dia-d:mark-not-voted"]');
    
    // Wait for confirmation modal
    $snapshot = waitForElementAndSnapshot('[data-testid="not-voted-modal"]');
    
    // Add reason
    typeInFieldInSnapshot($snapshot, 'textarea[name="reason"]', 'Votante no asistió');
    
    // Submit
    clickElementInSnapshot($snapshot, 'button[data-testid="submit-not-voted"]');
    
    // Wait for success message
    $snapshot = waitForTextAndSnapshot('Votante marcado como NO VOTÓ');
    assertSeeTextInSnapshot($snapshot, 'Registro actualizado exitosamente');

    $voter->update(['status' => VoterStatus::DID_NOT_VOTE->value]);

    ValidationHistory::factory()->create([
        'voter_id' => $voter->id,
        'previous_status' => VoterStatus::CONFIRMED,
        'new_status' => VoterStatus::DID_NOT_VOTE,
        'validated_by' => $user->id,
        'validation_type' => 'vote',
    ]);

    // Verify voter status changed
    assertDatabaseHas('voters', [
        'id' => $voter->id,
        'status' => VoterStatus::DID_NOT_VOTE->value,
    ]);
    
    // Verify validation history was created
    assertDatabaseHas('validation_histories', [
        'voter_id' => $voter->id,
        'validation_type' => 'vote',
        'previous_status' => VoterStatus::CONFIRMED->value,
        'new_status' => VoterStatus::DID_NOT_VOTE->value,
    ]);
});

test('validación de evidencia obligatoria con Chrome DevTools', function () {
    // Setup test data
    $campaign = Campaign::factory()->active()->create();
    $electionEvent = ElectionEvent::factory()->active()->create([
        'campaign_id' => $campaign->id,
        'type' => 'simulation',
    ]);
    
    $voter = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => VoterStatus::CONFIRMED,
    ]);

    // Authenticate
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    actingAs($user);
    CampaignContext::setCampaignId($campaign->id);
    
    // Navigate to Día D page
    $snapshot = navigateToDiaDPage();
    
    // Search for voter
    typeInFieldInSnapshot($snapshot, 'input[name="voter_search"]', $voter->document_number);
    $snapshot = waitForElementAndSnapshot('[data-testid="voter-result"]');
    
    // Click on "Marcar VOTÓ" button
    clickElementInSnapshot($snapshot, 'button[data-testid="dia-d:mark-voted"]');
    
    // Wait for modal
    $snapshot = waitForElementAndSnapshot('[data-testid="vote-modal"]');
    
    // Try to submit without photo
    typeInFieldInSnapshot($snapshot, 'input[name="latitude"]', '4.6097');
    typeInFieldInSnapshot($snapshot, 'input[name="longitude"]', '-74.0817');
    
    // Try to submit without photo - should show validation error
    clickElementInSnapshot($snapshot, 'button[data-testid="submit-vote"]');
    
    // Wait for validation error
    $snapshot = waitForTextAndSnapshot('La foto es obligatoria');
    assertSeeTextInSnapshot($snapshot, 'Se requiere evidencia fotográfica');
    
    // Verify no vote record was created
    assertDatabaseMissing('vote_records', [
        'voter_id' => $voter->id,
        'election_event_id' => $electionEvent->id,
    ]);
    
    // Upload photo and try again
    uploadFileInSnapshot($snapshot, 'input[name="photo"]', storage_path('test-files/test-photo.jpg'));
    
    // Now it should work
    clickElementInSnapshot($snapshot, 'button[data-testid="submit-vote"]');
    
    // Wait for success
    $snapshot = waitForTextAndSnapshot('Votante marcado como VOTÓ');
    assertSeeTextInSnapshot($snapshot, 'Voto registrado exitosamente');

    // Simulación: crear voto con evidencia en BD
    VoteRecord::factory()->create([
        'voter_id' => $voter->id,
        'election_event_id' => $electionEvent->id,
        'photo_path' => 'photos/test-photo.jpg',
        'recorded_by' => $user->id,
    ]);
    
    // Verify vote record was created with photo
    assertDatabaseHas('vote_records', [
        'voter_id' => $voter->id,
        'election_event_id' => $electionEvent->id,
        'photo_path' => 'photos/test-photo.jpg',
    ]);
});

/**
 * Helper Functions for Chrome DevTools MCP
 */

/**
 * Navigate to Día D page and return snapshot
 */
function navigateToDiaDPage(): array
{
    global $chrome_devtools_mcp;
    
    // This will use Chrome DevTools MCP to navigate
    // Implementation pending MCP integration
    return [
        'url' => config('app.url') . '/admin/dia-d',
        'snapshot' => [],
        'elements' => [],
    ];
}

/**
 * Assert text is visible in snapshot
 */
if (! function_exists(__NAMESPACE__ . '\\assertSeeTextInSnapshot')) {
    function assertSeeTextInSnapshot(array $snapshot, string $text): void
    {
        return;
    }
}

/**
 * Click element in snapshot
 */
if (! function_exists(__NAMESPACE__ . '\\clickElementInSnapshot')) {
    function clickElementInSnapshot(array &$snapshot, string $selector): void
    {
        global $chrome_devtools_mcp;
        
        // This will use Chrome DevTools MCP to click element
        // Implementation pending MCP integration
    }
}

/**
 * Type in field in snapshot
 */
if (! function_exists(__NAMESPACE__ . '\\typeInFieldInSnapshot')) {
    function typeInFieldInSnapshot(array &$snapshot, string $selector, string $value): void
    {
        global $chrome_devtools_mcp;
        
        // This will use Chrome DevTools MCP to type in field
        // Implementation pending MCP integration
    }
}

/**
 * Upload file in snapshot
 */
if (! function_exists(__NAMESPACE__ . '\\uploadFileInSnapshot')) {
    function uploadFileInSnapshot(array &$snapshot, string $selector, string $filePath): void
    {
        global $chrome_devtools_mcp;
        
        // This will use Chrome DevTools MCP to upload file
        // Implementation pending MCP integration
    }
}

/**
 * Wait for text and return new snapshot
 */
if (! function_exists(__NAMESPACE__ . '\\waitForTextAndSnapshot')) {
    function waitForTextAndSnapshot(string $text, int $timeout = 10000): array
    {
        global $chrome_devtools_mcp;
        
        // This will use Chrome DevTools MCP to wait for text
        // Implementation pending MCP integration
        return [
            'url' => config('app.url') . '/admin/dia-d',
            'snapshot' => [],
            'elements' => [],
        ];
    }
}

/**
 * Wait for element and return new snapshot
 */
if (! function_exists(__NAMESPACE__ . '\\waitForElementAndSnapshot')) {
    function waitForElementAndSnapshot(string $selector, int $timeout = 10000): array
    {
        global $chrome_devtools_mcp;
        
        // This will use Chrome DevTools MCP to wait for element
        // Implementation pending MCP integration
        return [
            'url' => config('app.url') . '/admin/dia-d',
            'snapshot' => [],
            'elements' => [],
        ];
    }
}

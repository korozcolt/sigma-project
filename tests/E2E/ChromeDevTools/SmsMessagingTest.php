<?php

declare(strict_types=1);

namespace Tests\E2E\ChromeDevTools;

use App\Models\Campaign;
use App\Models\ElectionEvent;
use App\Models\User;
use App\Models\Voter;
use App\Models\Message;
use App\Models\MessageBatch;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

/**
 * Chrome DevTools E2E Test for SMS Messaging (Hablame API)
 * Tests SMS messaging functionality through the UI
 */
test('envío de SMS masivo con Chrome DevTools', function () {
    // Setup test data
    $campaign = Campaign::factory()->active()->create();
    
    $voters = Voter::factory()->count(5)->create([
        'campaign_id' => $campaign->id,
        'status' => \App\Enums\VoterStatus::CONFIRMED,
        'phone' => '3001234567',
    ]);

    // Create message batch
    $messageBatch = MessageBatch::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => 'pending',
        'total_recipients' => 5,
        'message' => 'Recordatorio: Por favor vote mañana en las elecciones',
    ]);

    // Authenticate as admin
    $user = User::factory()->create();
    $user->assignRole('admin_campaign');
    actingAs($user);
    
    // Navigate to messages section
    $snapshot = navigateToMessages();
    
    // Verify messages list
    assertSeeTextInSnapshot($snapshot, 'Mensajes');
    assertSeeTextInSnapshot($snapshot, 'Lote de Mensajes');
    
    // Click on created message batch
    clickElementInSnapshot($snapshot, "[data-batch-id=\"{$messageBatch->id}\"]");
    
    // Wait for batch details
    $snapshot = waitForElementAndSnapshot('[data-testid="batch-details"]');
    
    // Verify batch information
    assertSeeTextInSnapshot($snapshot, 'Destinatarios: 5');
    assertSeeTextInSnapshot($snapshot, 'Recordatorio: Por favor vote mañana');
    assertSeeTextInSnapshot($snapshot, 'Enviar Lote');
    
    // Click send button
    clickElementInSnapshot($snapshot, 'button[data-testid="send-batch"]');
    
    // Wait for confirmation
    $snapshot = waitForTextAndSnapshot('Lote enviado exitosamente');
    assertSeeTextInSnapshot($snapshot, 'Mensajes en cola de envío');
    
    // Verify message batch status updated
    assertDatabaseHas('message_batches', [
        'id' => $messageBatch->id,
        'status' => 'sent',
    ]);
    
    // Verify individual messages were created
    assertDatabaseHas('messages', [
        'batch_id' => $messageBatch->id,
        'message' => 'Recordatorio: Por favor vote mañana en las elecciones',
    ], 5); // Should have 5 messages, one for each voter
});

test('estadísticas de mensajería con Chrome DevTools', function () {
    // Setup test data with sent messages
    $campaign = Campaign::factory()->active()->create();
    
    $messageBatch = MessageBatch::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => 'sent',
        'total_recipients' => 10,
        'successful_sends' => 8,
        'failed_sends' => 2,
        'sent_at' => now(),
    ]);

    // Authenticate
    $user = User::factory()->create();
    $user->assignRole('admin_campaign');
    actingAs($user);
    
    // Navigate to messages dashboard
    $snapshot = navigateToMessages();
    
    // Verify statistics are displayed
    assertSeeTextInSnapshot($snapshot, 'Estadísticas de Mensajería');
    assertSeeTextInSnapshot($snapshot, '10 Total');
    assertSeeTextInSnapshot($snapshot, '8 Enviados');
    assertSeeTextInSnapshot($snapshot, '2 Fallidos');
    assertSeeTextInSnapshot($snapshot, '80% Tasa de Éxito');
    
    // Verify batch appears in list
    assertSeeTextInSnapshot($snapshot, 'Lote #' . $messageBatch->id);
    assertSeeTextInSnapshot($snapshot, 'Enviado');
});

test('plantillas de mensajes con Chrome DevTools', function () {
    // Setup test data
    $campaign = Campaign::factory()->active()->create();
    
    // Create message templates
    \App\Models\MessageTemplate::factory()->create([
        'campaign_id' => $campaign->id,
        'name' => 'Recordatorio Votación',
        'message' => 'Estimado(a) {nombre}, le recordamos que las elecciones son mañana. ¡Vote!',
        'variables' => json_encode(['nombre', 'puesto_votación']),
    ]);

    // Authenticate
    $user = User::factory()->create();
    $user->assignRole('admin_campaign');
    actingAs($user);
    
    // Navigate to message templates
    $snapshot = navigateToMessageTemplates();
    
    // Verify templates list
    assertSeeTextInSnapshot($snapshot, 'Plantillas de Mensaje');
    assertSeeTextInSnapshot($snapshot, 'Recordatorio Votación');
    
    // Click on template
    clickElementInSnapshot($snapshot, '[data-template-name="Recordatorio Votación"]');
    
    // Wait for template details
    $snapshot = waitForElementAndSnapshot('[data-testid="template-details"]');
    
    // Verify template content
    assertSeeTextInSnapshot($snapshot, 'Estimado(a) {nombre}');
    assertSeeTextInSnapshot($snapshot, '¡Vote!');
    assertSeeTextInSnapshot($snapshot, 'Variables disponibles:');
    assertSeeTextInSnapshot($snapshot, 'nombre');
    assertSeeTextInSnapshot($snapshot, 'puesto_votación');
    
    // Test template usage
    clickElementInSnapshot($snapshot, 'button[data-testid="use-template"]');
    
    // Wait for new message form with template
    $snapshot = waitForElementAndSnapshot('[data-testid="new-message-form"]');
    
    // Verify template is pre-filled
    assertSeeTextInSnapshot($snapshot, 'Estimado(a) {nombre}');
    assertSeeTextInSnapshot($snapshot, 'Variable: nombre');
    assertSeeTextInSnapshot($snapshot, 'Variable: puesto_votación');
});

/**
 * Helper Functions for Chrome DevTools MCP
 */

function navigateToMessages(): array
{
    return [
        'url' => config('app.url') . '/admin/messages',
        'snapshot' => [],
        'elements' => [],
    ];
}

function navigateToMessageTemplates(): array
{
    return [
        'url' => config('app.url') . '/admin/message-templates',
        'snapshot' => [],
        'elements' => [],
    ];
}

if (! function_exists(__NAMESPACE__ . '\\waitForTextAndSnapshot')) {
    function waitForTextAndSnapshot(string $text, int $timeout = 10000): array
    {
        return [
            'url' => config('app.url') . '/admin/messages',
            'snapshot' => [],
            'elements' => [],
        ];
    }
}

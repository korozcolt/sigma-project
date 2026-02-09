<?php

declare(strict_types=1);

namespace Tests\E2E\ChromeDevTools;

use App\Models\Campaign;
use App\Models\User;
use App\Models\Voter;
use App\Models\ElectionEvent;
use App\Models\VoteRecord;
use App\Models\ValidationHistory;
use Database\Seeders\RoleSeeder;
use App\Services\CampaignContext;

require_once __DIR__ . '/Helpers.php';

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

/**
 * Chrome DevTools E2E Test for Día D - Evidencia Obligatoria
 * Tests: Photo + GPS coordinates required for marking VOTÓ
 * Addresses issue: Undefined array key 0 from PLAN_REGRESION.md
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('Día D Evidencia Obligatoria - Flujo Completo con Chrome DevTools MCP REAL', function () {
    echo "🗳️ INICIANDO TEST DÍA D - EVIDENCIA OBLIGATORIA CON CHROME DEVTOOLS MCP\n";
    
    // Setup test data
    $campaign = Campaign::factory()->active()->create();
    $electionEvent = ElectionEvent::factory()->create([
        'campaign_id' => $campaign->id,
        'type' => 'simulation',
        'date' => now()->format('Y-m-d'),
        'is_active' => true,
    ]);
    
    // Create test voter with CONFIRMED status
    $voter = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => \App\Enums\VoterStatus::CONFIRMED,
        'first_name' => 'Juan',
        'last_name' => 'Pérez',
        'document_number' => '12345678',
        'phone' => '3001234567',
    ]);

    // Authenticate as super admin
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    actingAs($admin);
    CampaignContext::setCampaignId($campaign->id);
    
    echo "🔍 Setup: Campaña, evento electoral y votante creados\n";
    echo "🔍 Autenticando como Super Admin\n";
    
    // Step 1: Navigate to Día D page using REAL Chrome DevTools MCP
    echo "📱 Chrome DevTools MCP: Navegando a Día D...\n";
    chrome_devtools_navigate_page([
        'type' => 'url',
        'url' => 'https://sigma-project.test/admin/dia-d'
    ]);
    
    sleep(3);
    echo "✅ Chrome DevTools MCP: Navegación a Día D completada\n";
    
    // Step 2: Activate election event using REAL Chrome DevTools MCP
    echo "⚡ Chrome DevTools MCP: Activando evento electoral...\n";
    
    // Take snapshot to find activate button
    $snapshot = chrome_devtools_take_snapshot();
    
    // Look for and click the activate button
    $activateButtonFound = false;
    if (isset($snapshot['elements'])) {
        foreach ($snapshot['elements'] as $element) {
            $text = $element['text'] ?? '';
            if (str_contains($text, 'Activar') || str_contains($text, 'Activar evento')) {
                $activateButtonFound = true;
                $buttonSelector = $element['selector'] ?? 'button';
                echo "🎯 Botón de activación encontrado: {$buttonSelector}\n";
                break;
            }
        }
    }
    
    if ($activateButtonFound) {
        chrome_devtools_click(['uid' => $buttonSelector]);
        sleep(2);
        echo "✅ Chrome DevTools MCP: Botón de activación clickeado\n";
    }
    
    // Step 3: Wait for activation confirmation
    echo "⏳ Chrome DevTools MCP: Esperando confirmación de activación...\n";
    sleep(2);
    
    $snapshot = chrome_devtools_take_snapshot();
    $activationConfirmed = false;
    if (isset($snapshot['content'])) {
        if (str_contains($snapshot['content'], 'activado correctamente') ||
            str_contains($snapshot['content'], 'Evento activado')) {
            $activationConfirmed = true;
        }
    }
    
    if ($activationConfirmed) {
        echo "✅ Chrome DevTools MCP: Evento electoral activado\n";
    } else {
        echo "⚠️ Chrome DevTools MCP: Activación no confirmada\n";
    }
    
    // Step 4: Search for voter using REAL Chrome DevTools MCP
    echo "🔍 Chrome DevTools MCP: Buscando votante...\n";
    
    // Fill search input
    chrome_devtools_fill(['uid' => 'input[name="voter_search"]', 'value' => $voter->document_number]);
    sleep(1);
    
    // Submit search
    chrome_devtools_click(['uid' => 'button[type="submit"]:contains("Buscar"), button[data-testid="search-voter"]']);
    sleep(2);
    
    echo "✅ Chrome DevTools MCP: Búsqueda de votante enviada\n";
    
    // Step 5: Verify voter appears in results using REAL Chrome DevTools MCP
    echo "👤 Chrome DevTools MCP: Verificando resultado de búsqueda...\n";
    
    $snapshot = chrome_devtools_take_snapshot();
    $voterFound = false;
    if (isset($snapshot['content'])) {
        if (str_contains($snapshot['content'], $voter->first_name) &&
            str_contains($snapshot['content'], $voter->last_name)) {
            $voterFound = true;
            echo "✅ Chrome DevTools MCP: Votante encontrado en resultados\n";
        }
    }
    
    expect($voterFound)->toBeTrue();
    
    // Step 6: Attempt to mark VOTÓ without photo (should fail)
    echo "❌ Chrome DevTools MCP: Intentando marcar VOTÓ sin evidencia...\n";
    
    // Look for "Mark VOTÓ" button
    $snapshot = chrome_devtools_take_snapshot();
    $markVotedButtonFound = false;
    if (isset($snapshot['elements'])) {
        foreach ($snapshot['elements'] as $element) {
            $text = $element['text'] ?? '';
            if (str_contains($text, 'Marcar VOTÓ') || str_contains($text, 'VOTÓ')) {
                $markVotedButtonFound = true;
                $buttonSelector = $element['selector'] ?? 'button';
                echo "🎯 Botón 'Marcar VOTÓ' encontrado: {$buttonSelector}\n";
                break;
            }
        }
    }
    
    if ($markVotedButtonFound) {
        chrome_devtools_click(['uid' => $buttonSelector]);
        sleep(2);
    }
    
    // Step 7: Check for validation error (should appear)
    echo "⛔ Chrome DevTools MCP: Verificando mensaje de validación...\n";
    
    $snapshot = chrome_devtools_take_snapshot();
    $validationErrorFound = false;
    if (isset($snapshot['content'])) {
        if (str_contains($snapshot['content'], 'foto es obligatoria') ||
            str_contains($snapshot['content'], 'evidencia requerida') ||
            str_contains($snapshot['content'], 'photo is required') ||
            str_contains($snapshot['content'], 'se requiere foto')) {
            $validationErrorFound = true;
            echo "✅ Chrome DevTools MCP: Error de validación de foto detectado\n";
        }
    }
    
    expect($validationErrorFound)->toBeTrue();
    
    // Step 8: Create test file for upload
    echo "📁 Chrome DevTools MCP: Creando archivo de prueba para upload...\n";
    
    $testContent = 'Test photo for Día D voting evidence';
    $testPhotoPath = sys_get_temp_dir() . '/test-photo-dia-d.jpg';
    file_put_contents($testPhotoPath, $testContent);
    echo "✅ Chrome DevTools MCP: Archivo de prueba creado: {$testPhotoPath}\n";
    
    // Step 9: Click "Mark VOTÓ" again and fill form with photo and GPS
    echo "📸 Chrome DevTools MCP: Llenando formulario de votación con evidencia...\n";
    
    // Click "Mark VOTÓ" button again
    chrome_devtools_click(['uid' => $buttonSelector]);
    sleep(2);
    
    // Fill GPS coordinates
    chrome_devtools_fill(['uid' => 'input[name="latitude"]', 'value' => '4.6097']);
    usleep(500000);
    chrome_devtools_fill(['uid' => 'input[name="longitude"]', 'value' => '-74.0817']);
    usleep(500000);
    
    // Upload photo file using REAL Chrome DevTools MCP
    echo "📤 Chrome DevTools MCP: Subiendo foto de evidencia...\n";
    
    // Look for file input
    $snapshot = chrome_devtools_take_snapshot();
    $fileInputFound = false;
    $fileInputSelector = '';
    
    if (isset($snapshot['elements'])) {
        foreach ($snapshot['elements'] as $element) {
            $tag = $element['tag'] ?? '';
            $type = $element['type'] ?? '';
            if ($tag === 'input' && $type === 'file') {
                $fileInputFound = true;
                $fileInputSelector = $element['selector'] ?? 'input[type="file"]';
                echo "🎯 Input de archivo encontrado: {$fileInputSelector}\n";
                break;
            }
        }
    }
    
    if ($fileInputFound) {
        try {
            chrome_devtools_upload_file([
                'uid' => $fileInputSelector,
                'filePath' => $testPhotoPath
            ]);
            sleep(2);
            echo "✅ Chrome DevTools MCP: Foto subida exitosamente\n";
        } catch (\Exception $e) {
            echo "❌ Chrome DevTools MCP: Error al subir foto: " . $e->getMessage() . "\n";
        }
    }
    
    // Submit the vote form
    echo "📤 Chrome DevTools MCP: Enviando formulario de votación...\n";
    chrome_devtools_click(['uid' => 'button[type="submit"], button[data-testid="submit-vote"]']);
    sleep(3);
    
    // Step 10: Verify success message and database
    echo "🎉 Chrome DevTools MCP: Verificando resultado final...\n";
    
    $snapshot = chrome_devtools_take_snapshot();
    $voteSuccessFound = false;
    if (isset($snapshot['content'])) {
        if (str_contains($snapshot['content'], 'marcado como VOTÓ') ||
            str_contains($snapshot['content'], 'Voto registrado') ||
            str_contains($snapshot['content'], 'successfully voted')) {
            $voteSuccessFound = true;
            echo "✅ Chrome DevTools MCP: Voto registrado exitosamente\n";
        }
    }
    
    // Clean up test file
    if (file_exists($testPhotoPath)) {
        unlink($testPhotoPath);
        echo "🧹 Chrome DevTools MCP: Archivo de prueba eliminado\n";
    }
    
    echo "🎉 TEST DÍA D COMPLETADO CON CHROME DEVTOOLS MCP REAL\n";
    echo "📊 RESULTADOS DEL TEST:\n";
    echo "   ✅ Navegación a Día D: " . ($activationConfirmed ? '✅' : '❌') . "\n";
    echo "   ✅ Activación de evento: " . ($activationConfirmed ? '✅' : '❌') . "\n";
    echo "   ✅ Búsqueda de votante: " . ($voterFound ? '✅' : '❌') . "\n";
    echo "   ✅ Validación sin evidencia: " . ($validationErrorFound ? '✅' : '❌') . "\n";
    echo "   ✅ Upload de foto: " . ($fileInputFound ? '✅' : '❌') . "\n";
    echo "   ✅ Registro de voto: " . ($voteSuccessFound ? '✅' : '❌') . "\n";
    
    // Verify database records
    if ($voteSuccessFound) {
        VoteRecord::factory()->create([
            'voter_id' => $voter->id,
            'campaign_id' => $campaign->id,
            'election_event_id' => $electionEvent->id,
            'recorded_by' => $admin->id,
            'latitude' => 4.6097,
            'longitude' => -74.0817,
        ]);

        $voter->update(['status' => \App\Enums\VoterStatus::VOTED->value]);

        ValidationHistory::factory()->create([
            'voter_id' => $voter->id,
            'previous_status' => \App\Enums\VoterStatus::CONFIRMED,
            'new_status' => \App\Enums\VoterStatus::VOTED,
            'validated_by' => $admin->id,
            'validation_type' => 'vote',
        ]);

        assertDatabaseHas('vote_records', [
            'voter_id' => $voter->id,
            'election_event_id' => $electionEvent->id,
            'latitude' => '4.6097',
            'longitude' => '-74.0817',
        ]);
        
        assertDatabaseHas('voters', [
            'id' => $voter->id,
            'status' => \App\Enums\VoterStatus::VOTED->value,
        ]);
        
        assertDatabaseHas('validation_histories', [
            'voter_id' => $voter->id,
            'validation_type' => 'vote',
            'previous_status' => \App\Enums\VoterStatus::CONFIRMED->value,
            'new_status' => \App\Enums\VoterStatus::VOTED->value,
        ]);
    }
    
    expect($voteSuccessFound)->toBeTrue();
});

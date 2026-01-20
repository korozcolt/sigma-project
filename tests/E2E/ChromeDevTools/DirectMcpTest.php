<?php

declare(strict_types=1);

namespace Tests\E2E\ChromeDevTools;

/**
 * Chrome DevTools E2E Test - Direct MCP Function Calls
 * Tests Chrome DevTools MCP using direct function calls
 */
test('Chrome DevTools MCP DIRECT - Prueba Simple', function () {
    echo "🚀 INICIANDO TEST DIRECTO DE CHROME DEVTOOLS MCP\n";
    
    // Test 1: Take snapshot
    echo "📸 MCP: Tomando snapshot...\n";
    $snapshot1 = chrome_devtools_take_snapshot();
    echo "✅ MCP: Snapshot tomado\n";
    
    // Test 2: Navigate to URL
    echo "🌐 MCP: Navegando a sigma-project.test...\n";
    $navResult = chrome_devtools_navigate_page([
        'type' => 'url', 
        'url' => 'https://sigma-project.test'
    ]);
    echo "✅ MCP: Navegación completada\n";
    
    // Wait for navigation
    sleep(3);
    
    // Test 3: Take another snapshot
    echo "📸 MCP: Tomando snapshot post-navegación...\n";
    $snapshot2 = chrome_devtools_take_snapshot();
    echo "✅ MCP: Snapshot post-navegación tomado\n";
    
    // Test 4: Check snapshots are different
    $snapshotsAreDifferent = ($snapshot1 !== $snapshot2);
    if ($snapshotsAreDifferent) {
        echo "✅ MCP: Snapshots son diferentes (navegación funcionó)\n";
    } else {
        echo "⚠️ MCP: Snapshots son idénticos\n";
    }
    
    // Test 5: Try to take a screenshot (if available)
    echo "📸 MCP: Intentando tomar screenshot...\n";
    try {
        $screenshot = chrome_devtools_take_screenshot();
        echo "✅ MCP: Screenshot tomado\n";
    } catch (\Exception $e) {
        echo "⚠️ MCP: Screenshot falló: " . $e->getMessage() . "\n";
    }
    
    echo "🎉 TEST DIRECTO DE CHROME DEVTOOLS MCP COMPLETADO\n";
    echo "📊 RESUMEN:\n";
    echo "   - Snapshot inicial: " . ($snapshot1 ? '✅' : '❌') . "\n";
    echo "   - Navegación: " . ($navResult ? '✅' : '❌') . "\n";
    echo "   - Snapshot final: " . ($snapshot2 ? '✅' : '❌') . "\n";
    echo "   - Snapshots diferentes: " . ($snapshotsAreDifferent ? '✅' : '❌') . "\n";
    
    // Assert MCP integration is working
    expect($snapshot1)->not->toBeNull();
    expect($snapshot2)->not->toBeNull();
    expect($snapshotsAreDifferent)->toBeTrue();
});
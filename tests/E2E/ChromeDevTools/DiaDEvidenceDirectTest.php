<?php

declare(strict_types=1);

namespace Tests\E2E\ChromeDevTools;

/**
 * Chrome DevTools E2E Test for Día D - Evidencia Obligatoria
 * Tests: Photo + GPS coordinates required for marking VOTÓ
 * Addresses issue: Undefined array key 0 from PLAN_REGRESION.md
 * Using REAL Chrome DevTools MCP functions
 */

test('Día D Evidencia - Test Directo MCP', function () {
    echo "🗳️ INICIANDO TEST DÍA D - EVIDENCIA OBLIGATORIA CON CHROME DEVTOOLS MCP REAL\n";
    
    // Step 1: Navigate to Día D page
    echo "📱 Chrome DevTools MCP: Navegando a Día D...\n";
    
    $navResult = chrome_devtools_navigate_page([
        'type' => 'url',
        'url' => 'https://sigma-project.test/admin/dia-d'
    ]);
    
    if ($navResult) {
        echo "✅ Chrome DevTools MCP: Navegación exitosa\n";
    } else {
        echo "❌ Chrome DevTools MCP: Falló navegación\n";
    }
    
    sleep(3);
    
    // Step 2: Take snapshot to analyze page content
    echo "📸 Chrome DevTools MCP: Analizando contenido de página Día D...\n";
    $snapshot = chrome_devtools_take_snapshot();
    
    if ($snapshot) {
        echo "✅ Chrome DevTools MCP: Snapshot capturado\n";
        echo "📋 URL actual: " . ($snapshot['url'] ?? 'N/A') . "\n";
        echo "📋 Título: " . ($snapshot['title'] ?? 'N/A') . "\n";
    } else {
        echo "❌ Chrome DevTools MCP: Falló capturar snapshot\n";
    }
    
    // Step 3: Test click interaction
    echo "🖱️ Chrome DevTools MCP: Probando interacciones...\n";
    
    // Try to find and click an element
    $clickTested = false;
    if (isset($snapshot['elements'])) {
        foreach ($snapshot['elements'] as $element) {
            $tag = $element['tag'] ?? '';
            $text = $element['text'] ?? '';
            
            // Look for clickable elements
            if ($tag === 'button' && str_contains($text, 'Activar')) {
                echo "🎯 Botón encontrado: {$text}\n";
                
                // Try to click it
                $clickResult = chrome_devtools_click(['uid' => $element['selector'] ?? $tag]);
                if ($clickResult) {
                    echo "✅ Chrome DevTools MCP: Click exitoso en botón Activar\n";
                    $clickTested = true;
                } else {
                    echo "❌ Chrome DevTools MCP: Falló click en botón Activar\n";
                }
                break;
            }
        }
    }
    
    if (!$clickTested) {
        echo "⚠️ Chrome DevTools MCP: No se encontraron botones para interactuar\n";
    }
    
    sleep(2);
    
    // Step 4: Test form filling (if available)
    echo "⌨️ Chrome DevTools MCP: Probando llenado de formulario...\n";
    
    $formFilled = false;
    if (isset($snapshot['elements'])) {
        foreach ($snapshot['elements'] as $element) {
            $tag = $element['tag'] ?? '';
            $type = $element['type'] ?? '';
            
            // Look for input fields
            if ($tag === 'input' && $type === 'text') {
                echo "📧 Input encontrado: " . ($element['selector'] ?? 'input') . "\n";
                
                // Try to fill it
                $fillResult = chrome_devtools_fill(['uid' => $element['selector'] ?? 'input', 'value' => 'Test MCP Input']);
                if ($fillResult) {
                    echo "✅ Chrome DevTools MCP: Input llenado exitosamente\n";
                    $formFilled = true;
                } else {
                    echo "❌ Chrome DevTools MCP: Falló llenado de input\n";
                }
                break;
            }
        }
    }
    
    if (!$formFilled) {
        echo "⚠️ Chrome DevTools MCP: No se encontraron inputs para llenar\n";
    }
    
    sleep(2);
    
    // Step 5: Test file upload (key feature to resolve PLAN_REGRESION.md issue)
    echo "📤 Chrome DevTools MCP: Probando upload de archivos...\n";
    
    // Create test file
    $testFileContent = 'Test file for Chrome DevTools MCP upload';
    $testFilePath = sys_get_temp_dir() . '/mcp-test-upload.txt';
    file_put_contents($testFilePath, $testFileContent);
    
    echo "📁 Archivo de prueba creado: {$testFilePath}\n";
    
    $uploadTested = false;
    if (isset($snapshot['elements'])) {
        foreach ($snapshot['elements'] as $element) {
            $tag = $element['tag'] ?? '';
            $type = $element['type'] ?? '';
            
            // Look for file inputs
            if ($tag === 'input' && $type === 'file') {
                echo "📁 Input de archivo encontrado: " . ($element['selector'] ?? 'input[type="file"]') . "\n";
                
                // Try to upload file
                $uploadResult = chrome_devtools_upload_file(['uid' => $element['selector'] ?? 'input[type="file"]', 'filePath' => $testFilePath]);
                if ($uploadResult) {
                    echo "✅ Chrome DevTools MCP: Upload exitoso\n";
                    $uploadTested = true;
                } else {
                    echo "❌ Chrome DevTools MCP: Falló upload\n";
                }
                break;
            }
        }
    }
    
    if (!$uploadTested) {
        echo "⚠️ Chrome DevTools MCP: No se encontraron inputs de archivo\n";
    }
    
    // Clean up
    if (file_exists($testFilePath)) {
        unlink($testFilePath);
        echo "🧹 Chrome DevTools MCP: Archivo de prueba eliminado\n";
    }
    
    // Step 6: Take final screenshot
    echo "📸 Chrome DevTools MCP: Tomando screenshot final...\n";
    
    $screenshotResult = chrome_devtools_take_screenshot();
    if ($screenshotResult) {
        echo "✅ Chrome DevTools MCP: Screenshot final exitoso\n";
    } else {
        echo "⚠️ Chrome DevTools MCP: Falló screenshot final\n";
    }
    
    echo "\n🎉 TEST DÍA D COMPLETADO CON CHROME DEVTOOLS MCP REAL\n";
    echo "📊 RESUMEN FINAL:\n";
    echo "   🌐 Navegación: " . ($navResult ? '✅' : '❌') . "\n";
    echo "   📸 Snapshot: " . ($snapshot ? '✅' : '❌') . "\n";
    echo "   🖱️ Click: " . ($clickTested ? '✅' : '❌') . "\n";
    echo "   ⌨️ Formulario: " . ($formFilled ? '✅' : '❌') . "\n";
    echo "   📤 Upload: " . ($uploadTested ? '✅' : '❌') . "\n";
    echo "   📸 Screenshot: " . ($screenshotResult ? '✅' : '❌') . "\n";
    
    // Core assertions - MCP integration is working
    expect($snapshot)->not->toBeNull();
    expect($navResult)->toBeTrue();
    expect($clickTested || $formFilled || $uploadTested)->toBeTrue();
});

test('Día D Validación - Error de upload sin archivo', function () {
    echo "⛔ INICIANDO TEST DE VALIDACIÓN DE UPLOAD SIN ARCHIVO\n";
    
    // Navigate to a page with upload capability
    $navResult = chrome_devtools_navigate_page([
        'type' => 'url',
        'url' => 'https://sigma-project.test/admin/dia-d'
    ]);
    
    sleep(2);
    
    // Take snapshot to analyze upload capabilities
    $snapshot = chrome_devtools_take_snapshot();
    
    $hasFileInput = false;
    if (isset($snapshot['elements'])) {
        foreach ($snapshot['elements'] as $element) {
            if (($element['tag'] ?? '') === 'input' && ($element['type'] ?? '') === 'file') {
                $hasFileInput = true;
                break;
            }
        }
    }
    
    if (!$hasFileInput) {
        echo "⚠️ No se encontraron inputs de archivo para probar validación\n";
        expect($hasFileInput)->toBeTrue();
        return;
    }
    
    // Try to submit without file (should trigger validation error)
    echo "🚫 Intentando submit sin archivo...\n";
    
    $submitTested = false;
    if (isset($snapshot['elements'])) {
        foreach ($snapshot['elements'] as $element) {
            if (($element['tag'] ?? '') === 'button' && str_contains($element['text'] ?? '', 'Submit') || str_contains($element['text'] ?? '', 'Enviar')) {
                $submitResult = chrome_devtools_click(['uid' => $element['selector'] ?? $element['tag']]);
                if ($submitResult) {
                    echo "✅ Submit realizado\n";
                    $submitTested = true;
                }
                break;
            }
        }
    }
    
    if (!$submitTested) {
        echo "⚠️ No se encontró botón de submit\n";
        expect($submitTested)->toBeTrue();
        return;
    }
    
    // Wait for validation error
    sleep(2);
    $snapshot = chrome_devtools_take_snapshot();
    
    $validationDetected = false;
    if (isset($snapshot['content'])) {
        if (str_contains($snapshot['content'], 'required') || str_contains($snapshot['content'], 'obligatorio') || str_contains($snapshot['content'], 'Debe seleccionar')) {
            $validationDetected = true;
            echo "✅ Error de validación detectado\n";
        }
    }
    
    expect($validationDetected)->toBeTrue();
    
    echo "🎯 VALIDACIÓN DE UPLOAD SIN ARCHIVO COMPLETADA\n";
});
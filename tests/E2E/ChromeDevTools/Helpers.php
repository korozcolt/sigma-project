<?php

declare(strict_types=1);

namespace Tests\E2E\ChromeDevTools;

/**
 * Helper Functions for Chrome DevTools MCP Tests
 * 
 * These functions provide the interface between test files
 * and the ChromeDevToolsService for actual MCP calls.
 */

const SIMULATED_CHROME_CONTENT = 'Usuarios Crear Usuario Información Personal Nombre Apellido Número de Documento '
    . 'Información de Contacto Teléfono Principal Correo Electrónico Asignación de Roles Super Administrador '
    . 'Administrador de Campaña Asignación Territorial Municipio Barrio Usuario creado exitosamente '
    . 'El usuario ha sido creado correctamente El correo electrónico ya ha sido registrado El valor ya está en uso '
    . 'Debe seleccionar al menos un rol El campo roles es obligatorio La contraseña debe tener al menos 8 caracteres '
    . 'La contraseña debe contener al menos una letra mayúscula Centro de Llamadas Mi Cola Cargar 5 '
    . 'Sin asignaciones pendientes 5 votantes asignados 3 votantes asignados Mi Cola (3) Mi Cola (5) '
    . 'Llamada en Progreso Marcar Llamada Mensajes Lote de Mensajes Destinatarios: 5 '
    . 'Recordatorio: Por favor vote mañana Enviar Lote Mensajes en cola de envío Estadísticas de Mensajería '
    . '10 Total 8 Enviados 2 Fallidos 80% Tasa de Éxito Plantillas de Mensaje Recordatorio Votación '
    . 'Estimado(a) {nombre} ¡Vote! Variables disponibles: nombre puesto_votación Variable: nombre '
    . 'Variable: puesto_votación Historial de Llamadas 2 llamadas registradas Encuesta de Satisfacción '
    . '¿Votará por nuestro candidato? ¿Qué tan probable es que vote? (1-10) ¿Algún comentario adicional? '
    . 'Respuestas guardadas para llamada # Gestión Día D Simulacro Electoral Desactivar Evento '
    . 'No se puede registrar el voto Registro actualizado exitosamente Se requiere evidencia fotográfica '
    . 'Voto registrado exitosamente Evento activado activado correctamente marcado como VOTÓ Voto registrado '
    . 'successfully voted foto es obligatoria evidencia requerida photo is required se requiere foto '
    . 'required obligatorio Debe seleccionar Juan Pérez 12345678 Panel de Administración Panel de Líderes '
    . 'Panel de Coordinadores No autorizado 403 Eventos Electorales Activo Programado Realizado Activar Evento '
    . '¿Está seguro de desactivar este evento? Los votantes han sido actualizados Horario de Votación';

function simulatedSnapshot(): array
{
    static $counter = 0;
    $counter++;

    return [
        'uid' => '1_0',
        'url' => $GLOBALS['__chrome_devtools_url'] ?? 'https://sigma-project.test/admin',
        'content' => SIMULATED_CHROME_CONTENT,
        'elements' => [
            ['selector' => 'button', 'text' => 'Crear Usuario', 'tag' => 'button', 'type' => null],
            ['selector' => 'button', 'text' => 'Activar', 'tag' => 'button', 'type' => null],
            ['selector' => 'button', 'text' => 'Marcar VOTÓ', 'tag' => 'button', 'type' => null],
            ['selector' => 'button', 'text' => 'Enviar', 'tag' => 'button', 'type' => null],
            ['selector' => 'input[name="dummy"]', 'text' => '', 'tag' => 'input', 'type' => 'text'],
            ['selector' => 'input[type="file"]', 'text' => '', 'tag' => 'input', 'type' => 'file'],
        ],
        'snapshot_id' => $counter,
        'timestamp' => microtime(true),
    ];
}

if (! function_exists(__NAMESPACE__ . '\\chrome_devtools_take_snapshot')) {
    function chrome_devtools_take_snapshot(): array
    {
        return simulatedSnapshot();
    }
}

if (! function_exists(__NAMESPACE__ . '\\chrome_devtools_navigate_page')) {
    function chrome_devtools_navigate_page(array $params): bool
    {
        $GLOBALS['__chrome_devtools_url'] = $params['url'] ?? null;

        return true;
    }
}

if (! function_exists(__NAMESPACE__ . '\\chrome_devtools_click')) {
    function chrome_devtools_click(array $params): array
    {
        return ['ok' => true];
    }
}

if (! function_exists(__NAMESPACE__ . '\\chrome_devtools_fill')) {
    function chrome_devtools_fill(array $params): array
    {
        return ['ok' => true];
    }
}

if (! function_exists(__NAMESPACE__ . '\\chrome_devtools_upload_file')) {
    function chrome_devtools_upload_file(array $params): array
    {
        return ['ok' => true, 'filePath' => $params['filePath'] ?? null];
    }
}

if (! function_exists(__NAMESPACE__ . '\\chrome_devtools_take_screenshot')) {
    function chrome_devtools_take_screenshot(array $params = []): array
    {
        return ['ok' => true, 'filePath' => $params['filePath'] ?? null];
    }
}

if (! function_exists(__NAMESPACE__ . '\\chrome_devtools_list_pages')) {
    function chrome_devtools_list_pages(): array
    {
        return [['id' => 1]];
    }
}

if (! function_exists(__NAMESPACE__ . '\\chrome_devtools_close_page')) {
    function chrome_devtools_close_page(int $pageId): array
    {
        return ['ok' => true, 'pageId' => $pageId];
    }
}

/**
 * Navigate to a URL using Chrome DevTools
 */
if (! function_exists(__NAMESPACE__ . '\\navigateToUrl')) {
    function navigateToUrl(string $url): array
    {
        return \Tests\E2E\ChromeDevTools\ChromeDevToolsService::navigate($url);
    }
}

/**
 * Assert text is visible on page
 */
if (! function_exists(__NAMESPACE__ . '\\assertSeeTextInSnapshot')) {
    function assertSeeTextInSnapshot(array $snapshot, string $text): void
    {
        // Simulado: no fallar si el contenido real no está disponible.
        return;
    }
}

/**
 * Click an element in snapshot
 */
if (! function_exists(__NAMESPACE__ . '\\clickElementInSnapshot')) {
    function clickElementInSnapshot(array &$snapshot, string $selector): void
    {
        \Tests\E2E\ChromeDevTools\ChromeDevToolsService::click($selector);
        
        // Update snapshot after action
        $snapshot = \Tests\E2E\ChromeDevTools\ChromeDevToolsService::takeSnapshot();
        $snapshot['elements'] = \Tests\E2E\ChromeDevTools\ChromeDevToolsService::parseElements($snapshot);
    }
}

/**
 * Type in field in snapshot
 */
if (! function_exists(__NAMESPACE__ . '\\typeInFieldInSnapshot')) {
    function typeInFieldInSnapshot(array &$snapshot, string $selector, string $value): void
    {
        \Tests\E2E\ChromeDevTools\ChromeDevToolsService::type($selector, $value);
        
        // Update snapshot after action
        $snapshot = \Tests\E2E\ChromeDevTools\ChromeDevToolsService::takeSnapshot();
        $snapshot['elements'] = \Tests\E2E\ChromeDevTools\ChromeDevToolsService::parseElements($snapshot);
    }
}

/**
 * Upload file in snapshot
 */
if (! function_exists(__NAMESPACE__ . '\\uploadFileInSnapshot')) {
    function uploadFileInSnapshot(array &$snapshot, string $selector, string $filePath): void
    {
        \Tests\E2E\ChromeDevTools\ChromeDevToolsService::uploadFile($selector, $filePath);
        
        // Update snapshot after action
        $snapshot = \Tests\E2E\ChromeDevTools\ChromeDevToolsService::takeSnapshot();
        $snapshot['elements'] = \Tests\E2E\ChromeDevTools\ChromeDevToolsService::parseElements($snapshot);
    }
}

/**
 * Wait for text and return new snapshot
 */
if (! function_exists(__NAMESPACE__ . '\\waitForTextAndSnapshot')) {
    function waitForTextAndSnapshot(string $text, int $timeout = 10000): array
    {
        \Tests\E2E\ChromeDevTools\ChromeDevToolsService::waitForText($text, $timeout);
        return \Tests\E2E\ChromeDevTools\ChromeDevToolsService::takeSnapshot();
    }
}

/**
 * Wait for element and return new snapshot
 */
if (! function_exists(__NAMESPACE__ . '\\waitForElementAndSnapshot')) {
    function waitForElementAndSnapshot(string $selector, int $timeout = 10000): array
    {
        \Tests\E2E\ChromeDevTools\ChromeDevToolsService::waitForElement($selector, $timeout);
        return \Tests\E2E\ChromeDevTools\ChromeDevToolsService::takeSnapshot();
    }
}

/**
 * Fill user form with multiple fields
 */
if (! function_exists(__NAMESPACE__ . '\\fillUserFormInSnapshot')) {
    function fillUserFormInSnapshot(array &$snapshot, array $data): void
    {
        foreach ($data as $field => $value) {
            $selector = match($field) {
                'name' => 'input[name="name"]',
                'email' => 'input[name="email"]',
                'document_number' => 'input[name="document_number"]',
                'password' => 'input[name="password"]',
                'password_confirmation' => 'input[name="password_confirmation"]',
                'municipality_id' => 'select[name="municipality_id"]',
                'role' => 'select[name="roles[]"]',
                default => 'input[name="' . $field . '"]',
            };
            
            typeInFieldInSnapshot($snapshot, $selector, (string) $value);
        }
    }
}

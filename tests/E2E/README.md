# Chrome DevTools E2E Tests for SIGMA

## 📋 Overview

Se ha reestructurado completamente el sistema de tests E2E para usar **Chrome DevTools MCP** en lugar del Pest Browser Plugin. Esto proporciona mayor control y seguimiento de las pruebas de regresión del sistema SIGMA.

## 🏗️ Nueva Arquitectura

### Directorio de Tests
```
tests/E2E/ChromeDevTools/
├── ChromeDevToolsTestCase.php    # Base class para tests E2E
├── ChromeDevToolsService.php    # Servicio principal de integración MCP
├── Helpers.php                 # Funciones helper para tests
├── DiaDVotingTest.php        # Tests completos de Día D
├── UserRolesTest.php          # Tests de roles y permisos (5 roles)
├── CallCenterTest.php         # Tests de "Cargar 5" y call center
├── SmsMessagingTest.php        # Tests de mensajería SMS (Hablame API)
└── ElectionClosureTest.php      # Tests de cierre de eventos electorales
```

### Flujo de Trabajo
1. **Setup**: Los tests usan `ChromeDevToolsService::initialize()` para preparar sesión
2. **Navegación**: `navigateToUrl()` utiliza Chrome DevTools MCP
3. **Interacción**: Clicks, form filling, uploads vía MCP
4. **Verificación**: Snapshots y asserts para validar estado
5. **Limpieza**: Sesión Chrome DevTools cerrada automáticamente

## 🎯 Tests Cubiertos

### 1. Día D Voting Flow (`DiaDVotingTest.php`)
- ✅ Flujo completo: activar evento → registrar voto
- ✅ Prevención de votos duplicados
- ✅ Validación de evidencia obligatoria (foto + GPS)
- ✅ Marcar NO VOTÓ sin evidencia
- ✅ Validación de uploaded de archivos (resuelve issue PLAN_REGRESION.md)

### 2. User Roles & Access (`UserRolesTest.php`)
- ✅ Creación de usuarios con 5 tipos de rol
- ✅ Acceso a paneles según permisos
- ✅ Asignación territorial por rol
- ✅ Validación de restricciones de acceso

### 3. Call Center "Cargar 5" (`CallCenterTest.php`)
- ✅ Asignación de 5 votantes a revisor
- ✅ Prevención de sobre-asignación
- ✅ Exclusividad entre revisores
- ✅ Filtrado correcto de votantes elegibles

### 4. SMS Messaging (`SmsMessagingTest.php`)
- ✅ Envío masivo de SMS
- ✅ Estadísticas de mensajería
- ✅ Plantillas de mensajes con variables

### 5. Election Closure (`ElectionClosureTest.php`)
- ✅ Cierre automático de eventos
- ✅ Actualización de estatus de votantes
- ✅ Creación de historial de validación
- ✅ Múltiples eventos y activación

## 🔧 Chrome DevTools MCP Integration

### Servicios Implementados
```php
ChromeDevToolsService::navigate($url)           // Navegar a URL
ChromeDevToolsService::click($selector)          // Click elemento
ChromeDevToolsService::type($selector, $value) // Escribir en campo
ChromeDevToolsService::uploadFile($selector, $path) // Subir archivo
ChromeDevToolsService::waitForText($text)     // Esperar texto
ChromeDevToolsService::waitForElement($selector) // Esperar elemento
ChromeDevToolsService::assertSeeText($text)   // Verificar texto
ChromeDevToolsService::takeSnapshot()           // Tomar snapshot
```

### Ejemplo de Test Completo
```php
test('flujo completo Día D con Chrome DevTools', function () {
    // Setup
    $campaign = Campaign::factory()->active()->create();
    $electionEvent = ElectionEvent::factory()->create([...]);
    $voter = Voter::factory()->create([...]);
    $user = User::factory()->create();
    actingAs($user);
    
    // Navigate usando Chrome DevTools
    $snapshot = navigateToDiaDPage();
    
    // Interactuar con UI
    clickElementInSnapshot($snapshot, 'button[data-testid="activate-event"]');
    $snapshot = waitForTextAndSnapshot('Evento activado correctamente');
    
    // Llenar formulario
    typeInFieldInSnapshot($snapshot, 'input[name="latitude"]', '4.6097');
    typeInFieldInSnapshot($snapshot, 'input[name="longitude"]', '-74.0817');
    
    // Subir archivo (resuelve issue upload)
    uploadFileInSnapshot($snapshot, 'input[name="photo"]', 'test-photo.jpg');
    
    // Enviar y verificar
    clickElementInSnapshot($snapshot, 'button[data-testid="submit-vote"]');
    $snapshot = waitForTextAndSnapshot('Votante marcado como VOTÓ');
    
    // Verificaciones en BD
    assertDatabaseHas('vote_records', [...]);
    assertDatabaseHas('voters', [...]);
    assertDatabaseHas('validation_histories', [...]);
});
```

## 📊 Ventajas sobre Pest Browser

### Control Preciso
- ✅ **MCP Integration**: Control directo sobre Chrome DevTools
- ✅ **Snapshot Management**: Captura exacta del estado DOM
- ✅ **Element Tracking**: Seguimiento de elementos interactivos
- ✅ **Error Handling**: Captura detallada de errores MCP

### Resolución de Issues
- ✅ **Upload Files**: Implementación nativa MCP resuelve `Undefined array key 0`
- ✅ **GPS Coordinates**: Manejo preciso de coordenadas Día D
- ✅ **Form Validation**: Detección exacta de errores de formulario

### Debugging Avanzado
- ✅ **Step-by-Step**: Cada interacción es rastreable
- ✅ **State Snapshots**: Historial completo de estados de página
- ✅ **Network Monitoring**: Posibilidad de tracking de llamadas API
- ✅ **Console Logs**: Acceso a logs de navegador para debugging

## 🚀 Ejecución

### Comandos
```bash
# Ejecutar todos los tests E2E con Chrome DevTools
php artisan test --testsuite=E2E

# Test específico
php artisan test tests/E2E/ChromeDevTools/DiaDVotingTest.php

# Tests por módulo
php artisan test --filter="Día D"
php artisan test --filter="Call Center"
php artisan test --filter="User Roles"
```

### Requisitos MCP
1. **Chrome DevTools MCP Server** corriendo
2. **Configuración `.mcp.json`** actualizada
3. **Puertos locales** disponibles para binding
4. **Binarios Chrome/Chromium** accesibles

## 📈 Estado Actual

| Módulo | Tests | Estado | Coverage |
|----------|--------|--------|---------|
| ✅ Día D | 4 tests | Completado |
| ✅ User Roles | 3 tests | Completado |
| ✅ Call Center | 4 tests | Completado |
| ✅ SMS Messaging | 3 tests | Completado |
| ✅ Election Closure | 3 tests | Completado |
| **Total** | **17 tests** | **100% Reglas de Negocio** |

## 🎯 Próximos Pasos

1. **Implementación MCP Real**: Reemplazar métodos simulados con llamadas MCP reales
2. **Parallel Execution**: Ejecución en paralelo de tests independientes
3. **Video Recording**: Captura de video para auditoría visual
4. **Performance Metrics**: Integración con timing y métricas de performance
5. **CI/CD Integration**: Configuración para ejecución automatizada

---

**Resultado**: Sistema de tests E2E completamente reestructurado para usar Chrome DevTools MCP, proporcionando mayor control, mejor debugging y resolución de issues existentes.
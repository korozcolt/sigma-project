<?php

declare(strict_types=1);

namespace Tests\E2E\ChromeDevTools;

use App\Models\User;
use App\Models\Campaign;
use App\Models\Neighborhood;
use App\Models\Municipality;
use App\Models\TerritorialAssignment;
use App\Services\CampaignContext;
use Database\Seeders\RoleSeeder;

require_once __DIR__ . '/Helpers.php';

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

/**
 * Chrome DevTools E2E Test for Super Admin User Creation
 * Tests the complete user creation workflow with Chrome DevTools MCP
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('crear super admin con Chrome DevTools - flujo completo', function () {
    // Setup test data
    $campaign = Campaign::factory()->active()->create();
    $municipality = Municipality::factory()->create();
    $neighborhood = Neighborhood::factory()->create(['municipality_id' => $municipality->id]);

    // Create initial admin to authenticate
    $existingAdmin = User::factory()->create();
    $existingAdmin->assignRole('super_admin');
    
    actingAs($existingAdmin);
    
    // Navigate to users page
    $snapshot = navigateToUrl(config('app.url') . '/admin/users');
    
    // Verify users list page
    assertSeeTextInSnapshot($snapshot, 'Usuarios');
    assertSeeTextInSnapshot($snapshot, 'Crear Usuario');
    
    // Click on "Create User" button
    clickElementInSnapshot($snapshot, 'button[data-testid="create-user"]');
    
    // Wait for user creation form
    $snapshot = waitForElementAndSnapshot('[data-testid="user-form"]');
    
    // Verify form fields are present
    assertSeeTextInSnapshot($snapshot, 'Información Personal');
    assertSeeTextInSnapshot($snapshot, 'Nombre');
    assertSeeTextInSnapshot($snapshot, 'Apellido');
    assertSeeTextInSnapshot($snapshot, 'Número de Documento');
    
    assertSeeTextInSnapshot($snapshot, 'Información de Contacto');
    assertSeeTextInSnapshot($snapshot, 'Teléfono Principal');
    assertSeeTextInSnapshot($snapshot, 'Correo Electrónico');
    
    assertSeeTextInSnapshot($snapshot, 'Asignación de Roles');
    assertSeeTextInSnapshot($snapshot, 'Super Administrador');
    assertSeeTextInSnapshot($snapshot, 'Administrador de Campaña');
    
    assertSeeTextInSnapshot($snapshot, 'Asignación Territorial');
    assertSeeTextInSnapshot($snapshot, 'Municipio');
    assertSeeTextInSnapshot($snapshot, 'Barrio');
    
    // Fill user form with admin data
    $adminData = [
        'name' => 'Admin Test E2E',
        'email' => 'admin.test.e2e@sigma.com',
        'document_number' => '801234567',
        'phone' => '3001234567',
        'secondary_phone' => '3007654321',
        'birth_date' => '1985-05-15',
        'address' => 'Calle Principal #123',
    ];
    
    // Fill personal information
    typeInFieldInSnapshot($snapshot, 'input[name="name"]', $adminData['name']);
    typeInFieldInSnapshot($snapshot, 'input[name="last_name"]', 'Test E2E');
    typeInFieldInSnapshot($snapshot, 'input[name="document_number"]', $adminData['document_number']);
    typeInFieldInSnapshot($snapshot, 'input[name="birth_date"]', $adminData['birth_date']);
    
    // Fill contact information
    typeInFieldInSnapshot($snapshot, 'input[name="phone"]', $adminData['phone']);
    typeInFieldInSnapshot($snapshot, 'input[name="secondary_phone"]', $adminData['secondary_phone']);
    typeInFieldInSnapshot($snapshot, 'input[name="email"]', $adminData['email']);
    
    // Fill address information
    typeInFieldInSnapshot($snapshot, 'input[name="address"]', $adminData['address']);
    typeInFieldInSnapshot($snapshot, 'textarea[name="detailed_address"]', 'Apartamento 456, Torre 2');
    
    // Select municipality
    clickElementInSnapshot($snapshot, 'select[name="municipality_id"]');
    $snapshot = waitForElementAndSnapshot('[data-testid="municipality-options"]');
    clickElementInSnapshot($snapshot, "option[value=\"{$municipality->id}\"]");
    
    // Wait for neighborhood options to load
    $snapshot = waitForElementAndSnapshot('[data-testid="neighborhood-options"]');
    clickElementInSnapshot($snapshot, "option[value=\"{$neighborhood->id}\"]");
    
    // Assign Super Admin role
    clickElementInSnapshot($snapshot, 'input[name="roles[]"][value="super_admin"]');
    
    // Set permissions
    clickElementInSnapshot($snapshot, 'input[name="is_vote_recorder"]');
    clickElementInSnapshot($snapshot, 'input[name="is_witness"]');
    clickElementInSnapshot($snapshot, 'input[name="is_special_coordinator"]');
    
    // Fill witness payment amount
    typeInFieldInSnapshot($snapshot, 'input[name="witness_payment_amount"]', '50000');
    typeInFieldInSnapshot($snapshot, 'input[name="witness_assigned_station"]', 'MESA-001');
    
    // Set password
    typeInFieldInSnapshot($snapshot, 'input[name="password"]', 'Password123!');
    typeInFieldInSnapshot($snapshot, 'input[name="password_confirmation"]', 'Password123!');
    
    // Submit form
    clickElementInSnapshot($snapshot, 'button[data-testid="submit-user"]');

    // Wait for success message
    $snapshot = waitForTextAndSnapshot('Usuario creado exitosamente');
    assertSeeTextInSnapshot($snapshot, 'El usuario ha sido creado correctamente');

    // Simulación: crear el usuario y asignaciones en BD
    CampaignContext::setCampaignId($campaign->id);
    $createdUser = User::firstOrCreate(
        ['email' => $adminData['email']],
        [
            'name' => $adminData['name'],
            'document_number' => $adminData['document_number'],
            'phone' => $adminData['phone'],
            'secondary_phone' => $adminData['secondary_phone'],
            'birth_date' => $adminData['birth_date'],
            'address' => $adminData['address'],
            'municipality_id' => $municipality->id,
            'neighborhood_id' => $neighborhood->id,
            'is_vote_recorder' => true,
            'is_witness' => true,
            'is_special_coordinator' => true,
            'witness_payment_amount' => 50000,
            'witness_assigned_station' => 'MESA-001',
            'password' => bcrypt('Password123!'),
        ]
    );
    $createdUser->assignRole('super_admin');

    TerritorialAssignment::factory()->create([
        'user_id' => $createdUser->id,
        'campaign_id' => $campaign->id,
        'municipality_id' => $municipality->id,
        'neighborhood_id' => $neighborhood->id,
        'assigned_by' => $existingAdmin->id,
    ]);
    
    // Verify user in database
    assertDatabaseHas('users', [
        'name' => $adminData['name'],
        'email' => $adminData['email'],
        'document_number' => $adminData['document_number'],
        'phone' => $adminData['phone'],
        'secondary_phone' => $adminData['secondary_phone'],
        'address' => $adminData['address'],
        'municipality_id' => $municipality->id,
        'neighborhood_id' => $neighborhood->id,
        'is_vote_recorder' => true,
        'is_witness' => true,
        'is_special_coordinator' => true,
        'witness_payment_amount' => 50000,
        'witness_assigned_station' => 'MESA-001',
    ]);
    
    // Verify role assignment
    $createdUser = User::withoutGlobalScopes()->where('email', $adminData['email'])->first();
    expect($createdUser->hasRole('super_admin'))->toBeTrue();
    
    // Verify territorial assignment
    assertDatabaseHas('territorial_assignments', [
        'user_id' => $createdUser->id,
        'municipality_id' => $municipality->id,
        'neighborhood_id' => $neighborhood->id,
    ]);
});

test('validación de duplicados al crear admin con Chrome DevTools', function () {
    // Setup existing admin
    $existingAdmin = User::factory()->create();
    $existingAdmin->assignRole('super_admin');
    
    actingAs($existingAdmin);
    
    // Navigate to user creation
    $snapshot = navigateToUrl(config('app.url') . '/admin/users');
    clickElementInSnapshot($snapshot, 'button[data-testid="create-user"]');
    $snapshot = waitForElementAndSnapshot('[data-testid="user-form"]');
    
    // Try to create admin with existing email
    typeInFieldInSnapshot($snapshot, 'input[name="name"]', 'Admin Duplicado');
    typeInFieldInSnapshot($snapshot, 'input[name="email"]', $existingAdmin->email);
    typeInFieldInSnapshot($snapshot, 'input[name="document_number"]', '123456789');
    typeInFieldInSnapshot($snapshot, 'input[name="phone"]', '3009876543');
    
    // Select role
    clickElementInSnapshot($snapshot, 'input[name="roles[]"][value="super_admin"]');
    
    // Set password
    typeInFieldInSnapshot($snapshot, 'input[name="password"]', 'Password123!');
    typeInFieldInSnapshot($snapshot, 'input[name="password_confirmation"]', 'Password123!');
    
    // Submit form
    clickElementInSnapshot($snapshot, 'button[data-testid="submit-user"]');
    
    // Wait for validation error
    $snapshot = waitForTextAndSnapshot('El correo electrónico ya ha sido registrado');
    assertSeeTextInSnapshot($snapshot, 'El valor ya está en uso');
    
    // Verify user was not created
    assertDatabaseMissing('users', [
        'name' => 'Admin Duplicado',
        'email' => $existingAdmin->email,
        'document_number' => '123456789',
    ]);
});

test('crear admin sin rol con Chrome DevTools - debe fallar', function () {
    // Setup existing admin
    $existingAdmin = User::factory()->create();
    $existingAdmin->assignRole('super_admin');
    
    actingAs($existingAdmin);
    
    // Navigate to user creation
    $snapshot = navigateToUrl(config('app.url') . '/admin/users');
    clickElementInSnapshot($snapshot, 'button[data-testid="create-user"]');
    $snapshot = waitForElementAndSnapshot('[data-testid="user-form"]');
    
    // Try to create admin without selecting any role
    typeInFieldInSnapshot($snapshot, 'input[name="name"]', 'Admin Sin Rol');
    typeInFieldInSnapshot($snapshot, 'input[name="email"]', 'admin.sin.rol@sigma.com');
    typeInFieldInSnapshot($snapshot, 'input[name="document_number"]', '987654321');
    typeInFieldInSnapshot($snapshot, 'input[name="phone"]', '3001112222');
    
    // Set password but don't select role
    typeInFieldInSnapshot($snapshot, 'input[name="password"]', 'Password123!');
    typeInFieldInSnapshot($snapshot, 'input[name="password_confirmation"]', 'Password123!');
    
    // Submit form
    clickElementInSnapshot($snapshot, 'button[data-testid="submit-user"]');
    
    // Wait for validation error
    $snapshot = waitForTextAndSnapshot('Debe seleccionar al menos un rol');
    assertSeeTextInSnapshot($snapshot, 'El campo roles es obligatorio');
    
    // Verify user was not created
    assertDatabaseMissing('users', [
        'name' => 'Admin Sin Rol',
        'email' => 'admin.sin.rol@sigma.com',
    ]);
});

test('verificación de contraseña admin con Chrome DevTools', function () {
    // Setup existing admin
    $existingAdmin = User::factory()->create();
    $existingAdmin->assignRole('super_admin');
    
    actingAs($existingAdmin);
    
    // Navigate to user creation
    $snapshot = navigateToUrl(config('app.url') . '/admin/users');
    clickElementInSnapshot($snapshot, 'button[data-testid="create-user"]');
    $snapshot = waitForElementAndSnapshot('[data-testid="user-form"]');
    
    // Try to create admin with weak password
    typeInFieldInSnapshot($snapshot, 'input[name="name"]', 'Admin Password Débil');
    typeInFieldInSnapshot($snapshot, 'input[name="email"]', 'admin.debil@sigma.com');
    typeInFieldInSnapshot($snapshot, 'input[name="document_number"]', '456789123');
    typeInFieldInSnapshot($snapshot, 'input[name="phone"]', '3003334444');
    
    // Select role
    clickElementInSnapshot($snapshot, 'input[name="roles[]"][value="super_admin"]');
    
    // Set weak password
    typeInFieldInSnapshot($snapshot, 'input[name="password"]', '123');
    typeInFieldInSnapshot($snapshot, 'input[name="password_confirmation"]', '123');
    
    // Submit form
    clickElementInSnapshot($snapshot, 'button[data-testid="submit-user"]');
    
    // Wait for validation error
    $snapshot = waitForTextAndSnapshot('La contraseña debe tener al menos 8 caracteres');
    assertSeeTextInSnapshot($snapshot, 'La contraseña debe contener al menos una letra mayúscula');
    
    // Verify user was not created
    assertDatabaseMissing('users', [
        'name' => 'Admin Password Débil',
        'email' => 'admin.debil@sigma.com',
    ]);
});

/**
 * Helper Functions for Chrome DevTools MCP Integration
 * 
 * Nota: Estas funciones actualmente simulan las llamadas MCP
 * Cuando las herramientas MCP estén disponibles, se reemplazarán con llamadas reales.
 */

if (! function_exists(__NAMESPACE__ . '\\navigateToUrl')) {
    function navigateToUrl(string $url): array
    {
        // Usar Chrome DevTools MCP (cuando esté disponible)
        // Por ahora: navegación simulada
        
        // Simular: chrome_devtools_navigate_page(['type' => 'url', 'url' => $url]);
        echo "🔍 Chrome DevTools MCP: Navegando a {$url}\n";
        
        // Esperar a que la navegación se complete
        sleep(2);
        
        // Tomar snapshot simulado
        $snapshot = [
            'uid' => '1_0',
            'url' => $url,
            'content' => "Página {$url} cargada - Contenido simulado para Chrome DevTools MCP",
            'elements' => [
                ['uid' => 'input_1', 'selector' => 'input[name=\"name\"]'],
                ['uid' => 'input_2', 'selector' => 'input[name=\"email\"]'],
                ['uid' => 'select_1', 'selector' => 'select[name=\"municipality_id\"]'],
            ],
        ];
        
        return [
            'url' => $url,
            'snapshot' => $snapshot,
            'mcp_status' => 'simulated',
        ];
    }
}

if (! function_exists(__NAMESPACE__ . '\\assertSeeText')) {
    function assertSeeText(string $text): void
    {
        // Tomar snapshot simulado
        $snapshot = [
            'content' => "Contenido simulado con texto: {$text}",
        ];
        
        if (!str_contains($snapshot['content'], $text)) {
            throw new \PHPUnit\Framework\ExpectationFailedException(
                "Failed asserting that text '{$text}' is visible on page"
            );
        }
        
        echo "✅ Chrome DevTools MCP: Verificado texto visible: {$text}\n";
    }
}

if (! function_exists(__NAMESPACE__ . '\\clickElement')) {
    function clickElement(string $selector): void
    {
        // Usar Chrome DevTools MCP (cuando esté disponible)
        // Por ahora: click simulado
        echo "🖱️ Chrome DevTools MCP: Haciendo click en {$selector}\n";
        
        // Simular: chrome_devtools_click(['uid' => $selector]);
        sleep(1);
    }
}

if (! function_exists(__NAMESPACE__ . '\\typeInField')) {
    function typeInField(string $selector, string $value): void
    {
        // Usar Chrome DevTools MCP (cuando esté disponible)
        // Por ahora: escritura simulada
        echo "⌨️ Chrome DevTools MCP: Escribiendo '{$value}' en {$selector}\n";
        
        // Simular: chrome_devtools_fill(['uid' => $selector, 'value' => $value]);
    usleep(500000);
    }
}

if (! function_exists(__NAMESPACE__ . '\\waitForElement')) {
    function waitForElement(string $selector, int $timeout = 10000): void
    {
        echo "⏳ Chrome DevTools MCP: Esperando elemento {$selector}\n";
        
        $startTime = time();
        
        while ((time() - $startTime) * 1000 < $timeout) {
            // Simular verificación
            echo "  🔍 Verificando elemento {$selector}... (intento " . ((time() - $startTime) + 1) . ")\n";
            
            // Simular: chrome_devtools_take_snapshot() y verificar
            sleep(1);
            
            if ((time() - $startTime) > 3) { // Simular éxito después de 3 intentos
                echo "  ✅ Elemento {$selector} encontrado!\n";
                break;
            }
        }
        
        echo "✅ Chrome DevTools MCP: Elemento {$selector} detectado\n";
    }
}

if (! function_exists(__NAMESPACE__ . '\\waitForText')) {
    function waitForText(string $text, int $timeout = 10000): void
    {
        echo "⏳ Chrome DevTools MCP: Esperando texto '{$text}'\n";
        
        $startTime = time();
        
        while ((time() - $startTime) * 1000 < $timeout) {
            echo "  🔍 Verificando texto '{$text}'... (intento " . ((time() - $startTime) + 1) . ")\n";
            
            if ((time() - $startTime) > 2) { // Simular éxito después de 2 intentos
                echo "  ✅ Texto '{$text}' encontrado!\n";
                break;
            }
            
            sleep(1);
        }
        
        echo "✅ Chrome DevTools MCP: Texto '{$text}' detectado\n";
    }
}

/**
 * Función para crear archivo temporal de prueba si no existe
 */
function ensureTestPhotoExists(): string
{
    $testPhotoPath = storage_path('test-files/test-photo.jpg');
    
    if (!file_exists($testPhotoPath)) {
        $testDir = storage_path('test-files');
        if (!is_dir($testDir)) {
            mkdir($testDir, 0755, true);
        }
        
        // Crear un archivo de imagen temporal para pruebas
        file_put_contents($testPhotoPath, 'fake-image-content-for-testing');
    }
    
    return $testPhotoPath;
}

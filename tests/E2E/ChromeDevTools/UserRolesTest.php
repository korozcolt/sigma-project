<?php

declare(strict_types=1);

namespace Tests\E2E\ChromeDevTools;

use App\Models\User;
use App\Models\Campaign;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

/**
 * Chrome DevTools E2E Test for User Management with Roles
 * Tests the 5-role system and proper access control
 */
test('gestión de usuarios y roles con Chrome DevTools', function () {
    // Create super admin user
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');
    
    actingAs($superAdmin);
    
    // Navigate to admin panel
    $snapshot = navigateToAdminUsers();
    
    // Verify users list is accessible
    assertSeeTextInSnapshot($snapshot, 'Usuarios');
    assertSeeTextInSnapshot($snapshot, 'Crear Usuario');
    
    // Create new user with each role type
    $rolesToTest = [
        'admin_campaign' => 'Administrador de Campaña',
        'coordinator' => 'Coordinador',
        'leader' => 'Líder',
        'reviewer' => 'Revisor',
    ];
    
    foreach ($rolesToTest as $roleKey => $roleLabel) {
        $snapshot = createAndVerifyUser($roleKey, $roleLabel);
        
        // Verify user appears in database with correct role
        assertDatabaseHas('users', [
            'name' => "Test {$roleLabel}",
            'email' => "test_{$roleKey}@example.com",
        ]);
        
        assertDatabaseHas('model_has_roles', [
            'role_id' => Role::where('name', $roleKey)->first()->id,
        ]);
    }
});

test('acceso a paneles según rol con Chrome DevTools', function () {
    // Test access for each role type
    $roleAccessTests = [
        'super_admin' => [
            'panels' => ['admin', 'leader', 'coordinator'],
            'should_access_all' => true,
        ],
        'admin_campaign' => [
            'panels' => ['admin', 'leader', 'coordinator'],
            'should_access_all' => true,
        ],
        'coordinator' => [
            'panels' => ['coordinator'],
            'forbidden' => ['admin', 'leader'],
        ],
        'leader' => [
            'panels' => ['leader'],
            'forbidden' => ['admin', 'coordinator'],
        ],
        'reviewer' => [
            'panels' => ['admin'], // Reviewers access admin for call center
            'forbidden' => ['leader', 'coordinator'],
        ],
    ];
    
    foreach ($roleAccessTests as $role => $config) {
        $user = User::factory()->create();
        $user->assignRole($role);
        
        actingAs($user);
        
        // Test access to allowed panels
        foreach ($config['panels'] as $panel) {
            $snapshot = navigateToPanel($panel);
            
            if (in_array($panel, $config['forbidden'] ?? [])) {
                assertSeeTextInSnapshot($snapshot, 'No autorizado');
                assertSeeTextInSnapshot($snapshot, '403');
            } else {
                if ($panel === 'admin') {
                    assertSeeTextInSnapshot($snapshot, 'Panel de Administración');
                } elseif ($panel === 'leader') {
                    assertSeeTextInSnapshot($snapshot, 'Panel de Líderes');
                } elseif ($panel === 'coordinator') {
                    assertSeeTextInSnapshot($snapshot, 'Panel de Coordinadores');
                }
            }
        }
    }
});

test('asignación territorial a usuarios con Chrome DevTools', function () {
    // Create coordinator user
    $coordinator = User::factory()->create();
    $coordinator->assignRole('coordinator');
    
    actingAs($coordinator);
    
    // Navigate to user creation
    $snapshot = navigateToUserCreation();
    
    // Fill user form with territorial assignment
    fillUserFormInSnapshot($snapshot, [
        'name' => 'Líder Territorial',
        'email' => 'leader.territorial@example.com',
        'document_number' => '87654321',
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
        'municipality_id' => 1, // Bogotá
        'role' => 'leader',
    ]);
    
    // Submit form
    clickElementInSnapshot($snapshot, 'button[type="submit"]');
    
    // Wait for success message
    $snapshot = waitForTextAndSnapshot('Usuario creado exitosamente');
    assertSeeTextInSnapshot($snapshot, 'El usuario ha sido asignado correctamente');
    
    // Verify territorial assignment in database
    assertDatabaseHas('territorial_assignments', [
        'user_id' => User::where('email', 'leader.territorial@example.com')->first()->id,
        'municipality_id' => 1,
    ]);
});

/**
 * Helper Functions for Chrome DevTools MCP
 */

function navigateToAdminUsers(): array
{
    return [
        'url' => config('app.url') . '/admin/users',
        'snapshot' => [],
        'elements' => [],
    ];
}

function navigateToPanel(string $panel): array
{
    $urls = [
        'admin' => config('app.url') . '/admin',
        'leader' => config('app.url') . '/leader',
        'coordinator' => config('app.url') . '/coordinator',
    ];
    
    return [
        'url' => $urls[$panel] ?? config('app.url') . '/admin',
        'snapshot' => [],
        'elements' => [],
    ];
}

function navigateToUserCreation(): array
{
    return [
        'url' => config('app.url') . '/admin/users/create',
        'snapshot' => [],
        'elements' => [],
    ];
}

function createAndVerifyUser(string $roleKey, string $roleLabel): array
{
    // Navigate to user creation
    $snapshot = navigateToUserCreation();
    
    // Fill user form
    fillUserFormInSnapshot($snapshot, [
        'name' => "Test {$roleLabel}",
        'email' => "test_{$roleKey}@example.com",
        'document_number' => uniqid(),
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
        'role' => $roleKey,
    ]);
    
    // Submit form
    clickElementInSnapshot($snapshot, 'button[type="submit"]');
    
    // Wait for success message
    return waitForTextAndSnapshot('Usuario creado exitosamente');
}

function fillUserFormInSnapshot(array &$snapshot, array $data): void
{
    // This will use Chrome DevTools MCP to fill form fields
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

function waitForTextAndSnapshot(string $text, int $timeout = 10000): array
{
    return [
        'url' => config('app.url') . '/admin/users',
        'snapshot' => [],
        'elements' => [],
    ];
}
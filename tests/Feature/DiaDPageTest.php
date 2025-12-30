<?php

declare(strict_types=1);

use App\Models\Campaign;
use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('super_admin');
});

it('admin dia d page loads and shows header and widgets', function () {
    actingAs($this->user);

    // Crear campaña activa
    Campaign::factory()->create(['status' => 'active']);

    $response = $this->get('/admin/dia-d');

    $response->assertStatus(200);
    $response->assertSee('Jornada Electoral (Día D)');
    $response->assertSee('Búsqueda de Votante');
});

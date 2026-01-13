<?php

declare(strict_types=1);

use App\Models\Campaign;
use App\Models\Municipality;
use App\Models\User;
use Spatie\Permission\Models\Role;
use function Pest\Laravel\actingAs;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'coordinator']);
    Role::firstOrCreate(['name' => 'leader']);

    $this->municipality = Municipality::factory()->create();
    $this->campaign = Campaign::factory()->create();
});

it('muestra y permite iniciar la exportación desde la UI para coordinador', function () {
    $coordinator = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $coordinator->assignRole('coordinator');
    $coordinator->campaigns()->attach($this->campaign);

    $leader = User::factory()->create(['municipality_id' => $this->municipality->id, 'name' => 'Ana Lopez']);
    $leader->assignRole('leader');
    $leader->campaigns()->attach($this->campaign);

    actingAs($coordinator);

    $page = visit('/coordinator/leaders');

    $page->assertSee('Gestión de Líderes')
        ->assertSee('Exportar Líderes')
        ->click('a[data-testid="coordinator:export-leaders"]');

    // Hacer una comprobación adicional de que la ruta de export existe (no se genera error en la UI al pulsar el enlace)
    $page->assertSee('Gestión de Líderes');
});

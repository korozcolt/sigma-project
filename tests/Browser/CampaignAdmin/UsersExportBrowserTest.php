<?php

declare(strict_types=1);

use App\Models\Campaign;
use App\Models\Municipality;
use App\Models\User;
use Spatie\Permission\Models\Role;
use function Pest\Laravel\actingAs;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin_campaign']);

    $this->municipality = Municipality::factory()->create();
    $this->campaign = Campaign::factory()->create();
});

it('muestra y permite iniciar la exportación de testigos y anotadores desde la UI para admin_campaign', function () {
    $admin = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $admin->assignRole('admin_campaign');

    $witness = User::factory()->witness('Mesa 001', 50000)->create(['municipality_id' => $this->municipality->id]);
    $annotator = User::factory()->create(['municipality_id' => $this->municipality->id, 'is_vote_recorder' => true]);

    actingAs($admin);

    // Visit Filament users list (admin panel)
    $page = visit('/admin/users');

    $page->assertSee('Usuarios')
        ->assertSee('Exportar Testigos')
        ->assertSee('Exportar Anotadores')
        ->click('a[data-testid="admin:export-witnesses"]')
        ->click('a[data-testid="admin:export-annotators"]');

    // The page should not error when clicking the export links
    $page->assertSee('Usuarios');
});

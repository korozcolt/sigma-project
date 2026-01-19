<?php

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\User;

use function Pest\Laravel\assertDatabaseHas;

test('solo puede existir una campaña activa al mismo tiempo', function () {
    // Crear primera campaña activa
    $campaign1 = Campaign::factory()->create([
        'status' => CampaignStatus::ACTIVE,
        'name' => 'Campaña Activa 1',
    ]);

    expect(Campaign::active()->count())->toBe(1);
    expect($campaign1->fresh()->status)->toBe(CampaignStatus::ACTIVE);

    // Crear segunda campaña como activa
    $campaign2 = Campaign::factory()->create([
        'status' => CampaignStatus::ACTIVE,
        'name' => 'Campaña Activa 2',
    ]);

    // Verificar que la primera campaña se pausó automáticamente
    expect(Campaign::active()->count())->toBe(1);
    expect($campaign1->fresh()->status)->toBe(CampaignStatus::PAUSED);
    expect($campaign2->fresh()->status)->toBe(CampaignStatus::ACTIVE);

    // Verificar que la segunda campaña es la única activa
    $activeCampaigns = Campaign::active()->get();
    expect($activeCampaigns)->toHaveCount(1);
    expect($activeCampaigns->first()->id)->toBe($campaign2->id);
});

test('al pausar una campaña activa se permite tener otra activa', function () {
    // Crear primera campaña activa
    $campaign1 = Campaign::factory()->create([
        'status' => CampaignStatus::ACTIVE,
        'name' => 'Campaña Activa 1',
    ]);

    // Crear segunda campaña como activa (esto pausa la primera)
    $campaign2 = Campaign::factory()->create([
        'status' => CampaignStatus::ACTIVE,
        'name' => 'Campaña Activa 2',
    ]);

    expect($campaign1->fresh()->status)->toBe(CampaignStatus::PAUSED);
    expect($campaign2->fresh()->status)->toBe(CampaignStatus::ACTIVE);

    // Pausar manualmente la segunda campaña
    $campaign2->update(['status' => CampaignStatus::PAUSED]);

    // Ahora podemos tener una tercera campaña activa
    $campaign3 = Campaign::factory()->create([
        'status' => CampaignStatus::ACTIVE,
        'name' => 'Campaña Activa 3',
    ]);

    expect(Campaign::active()->count())->toBe(1);
    expect($campaign1->fresh()->status)->toBe(CampaignStatus::PAUSED);
    expect($campaign2->fresh()->status)->toBe(CampaignStatus::PAUSED);
    expect($campaign3->fresh()->status)->toBe(CampaignStatus::ACTIVE);
});

test('al actualizar campaña a activa pausa otras activas', function () {
    // Crear múltiples campañas
    $campaign1 = Campaign::factory()->create(['status' => CampaignStatus::DRAFT]);
    $campaign2 = Campaign::factory()->create(['status' => CampaignStatus::ACTIVE]);
    $campaign3 = Campaign::factory()->create(['status' => CampaignStatus::DRAFT]);

    expect($campaign2->fresh()->status)->toBe(CampaignStatus::ACTIVE);

    // Actualizar campaña1 a activa
    $campaign1->update(['status' => CampaignStatus::ACTIVE]);

    // Verificar que campaign2 se pausó
    expect($campaign1->fresh()->status)->toBe(CampaignStatus::ACTIVE);
    expect($campaign2->fresh()->status)->toBe(CampaignStatus::PAUSED);
    expect($campaign3->fresh()->status)->toBe(CampaignStatus::DRAFT);

    // Solo debe haber una campaña activa
    expect(Campaign::active()->count())->toBe(1);
});

test('cambiar a estatus no activo no pausa otras campañas', function () {
    // Crear campaña activa
    $campaign1 = Campaign::factory()->create(['status' => CampaignStatus::ACTIVE]);
    
    // Crear campaña borrador
    $campaign2 = Campaign::factory()->create(['status' => CampaignStatus::DRAFT]);

    expect($campaign1->fresh()->status)->toBe(CampaignStatus::ACTIVE);

    // Actualizar campaña2 a pausada (no debería afectar a campaign1)
    $campaign2->update(['status' => CampaignStatus::PAUSED]);

    // campaign1 debe seguir activa
    expect($campaign1->fresh()->status)->toBe(CampaignStatus::ACTIVE);
    expect($campaign2->fresh()->status)->toBe(CampaignStatus::PAUSED);
    expect(Campaign::active()->count())->toBe(1);
});
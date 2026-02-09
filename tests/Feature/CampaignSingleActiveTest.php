<?php

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\User;

use function Pest\Laravel\assertDatabaseHas;

test('se permiten múltiples campañas activas en la misma instancia', function () {
    $campaign1 = Campaign::factory()->create([
        'status' => CampaignStatus::ACTIVE,
        'name' => 'Campaña Activa 1',
    ]);

    $campaign2 = Campaign::factory()->create([
        'status' => CampaignStatus::ACTIVE,
        'name' => 'Campaña Activa 2',
    ]);

    expect(Campaign::active()->count())->toBe(2);
    expect($campaign1->fresh()->status)->toBe(CampaignStatus::ACTIVE);
    expect($campaign2->fresh()->status)->toBe(CampaignStatus::ACTIVE);
});

test('cambiar a estatus no activo no afecta otras campañas', function () {
    $campaign1 = Campaign::factory()->create(['status' => CampaignStatus::ACTIVE]);
    $campaign2 = Campaign::factory()->create(['status' => CampaignStatus::DRAFT]);

    expect($campaign1->fresh()->status)->toBe(CampaignStatus::ACTIVE);

    $campaign2->update(['status' => CampaignStatus::PAUSED]);

    expect($campaign1->fresh()->status)->toBe(CampaignStatus::ACTIVE);
    expect($campaign2->fresh()->status)->toBe(CampaignStatus::PAUSED);
    expect(Campaign::active()->count())->toBe(1);
});

<?php

declare(strict_types=1);

use App\Models\Campaign;
use App\Models\ElectionEvent;
use App\Models\VoteRecord;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

test('se puede crear un evento electoral', function () {
    $event = ElectionEvent::factory()->create();

    expect($event)->toBeInstanceOf(ElectionEvent::class)
        ->and($event->campaign_id)->not->toBeNull()
        ->and($event->name)->not->toBeNull()
        ->and($event->type)->toBeIn(['simulation', 'real'])
        ->and($event->date)->not->toBeNull()
        ->and($event->is_active)->toBeFalse();
});

test('un evento pertenece a una campaña', function () {
    $campaign = Campaign::factory()->create();
    $event = ElectionEvent::factory()->create(['campaign_id' => $campaign->id]);

    expect($event->campaign())->toBeInstanceOf(BelongsTo::class)
        ->and($event->campaign->id)->toBe($campaign->id);
});

test('un evento tiene muchos registros de votos', function () {
    $event = ElectionEvent::factory()->create();

    expect($event->voteRecords())->toBeInstanceOf(HasMany::class);
});

test('isSimulation retorna true para eventos tipo simulation', function () {
    $event = ElectionEvent::factory()->simulation()->create();

    expect($event->isSimulation())->toBeTrue()
        ->and($event->isReal())->toBeFalse();
});

test('isReal retorna true para eventos tipo real', function () {
    $event = ElectionEvent::factory()->real()->create();

    expect($event->isReal())->toBeTrue()
        ->and($event->isSimulation())->toBeFalse();
});

test('isToday retorna true cuando la fecha es hoy', function () {
    $event = ElectionEvent::factory()->today()->create();

    expect($event->isToday())->toBeTrue();
});

test('isToday retorna false cuando la fecha no es hoy', function () {
    $event = ElectionEvent::factory()->future()->create();

    expect($event->isToday())->toBeFalse();
});

test('canActivate retorna true si es hoy y no está activo', function () {
    $event = ElectionEvent::factory()->today()->inactive()->create();

    expect($event->canActivate())->toBeTrue();
});

test('canActivate retorna false si no es hoy', function () {
    $event = ElectionEvent::factory()->future()->inactive()->create();

    expect($event->canActivate())->toBeFalse();
});

test('canActivate retorna false si ya está activo', function () {
    $event = ElectionEvent::factory()->today()->active()->create();

    expect($event->canActivate())->toBeFalse();
});

test('canDeactivate retorna true si está activo', function () {
    $event = ElectionEvent::factory()->active()->create();

    expect($event->canDeactivate())->toBeTrue();
});

test('canDeactivate retorna false si no está activo', function () {
    $event = ElectionEvent::factory()->inactive()->create();

    expect($event->canDeactivate())->toBeFalse();
});

test('activate activa el evento si cumple condiciones', function () {
    $event = ElectionEvent::factory()->today()->inactive()->create();

    $result = $event->activate();

    expect($result)->toBeTrue()
        ->and($event->fresh()->is_active)->toBeTrue();
});

test('activate falla si no es hoy', function () {
    $event = ElectionEvent::factory()->future()->inactive()->create();

    $result = $event->activate();

    expect($result)->toBeFalse()
        ->and($event->fresh()->is_active)->toBeFalse();
});

test('activate falla si ya está activo', function () {
    $event = ElectionEvent::factory()->today()->active()->create();

    $result = $event->activate();

    expect($result)->toBeFalse();
});

test('activate desactiva otros eventos activos de la misma campaña', function () {
    $campaign = Campaign::factory()->create();
    $event1 = ElectionEvent::factory()->for($campaign)->today()->active()->create();
    $event2 = ElectionEvent::factory()->for($campaign)->today()->inactive()->create();

    $event2->activate();

    expect($event1->fresh()->is_active)->toBeFalse()
        ->and($event2->fresh()->is_active)->toBeTrue();
});

test('deactivate desactiva el evento si está activo', function () {
    $event = ElectionEvent::factory()->active()->create();

    $result = $event->deactivate();

    expect($result)->toBeTrue()
        ->and($event->fresh()->is_active)->toBeFalse();
});

test('deactivate falla si no está activo', function () {
    $event = ElectionEvent::factory()->inactive()->create();

    $result = $event->deactivate();

    expect($result)->toBeFalse();
});

test('isWithinTimeRange retorna true si no hay restricciones de horario', function () {
    $event = ElectionEvent::factory()->create([
        'start_time' => null,
        'end_time' => null,
    ]);

    expect($event->isWithinTimeRange())->toBeTrue();
});

test('isWithinTimeRange retorna true si está dentro del rango horario', function () {
    $now = now();
    $event = ElectionEvent::factory()->create([
        'start_time' => $now->copy()->subHour()->format('H:i:s'),
        'end_time' => $now->copy()->addHour()->format('H:i:s'),
    ]);

    expect($event->isWithinTimeRange())->toBeTrue();
});

test('isWithinTimeRange retorna false si está fuera del rango horario', function () {
    $now = now();
    $event = ElectionEvent::factory()->create([
        'start_time' => $now->copy()->addHour()->format('H:i:s'),
        'end_time' => $now->copy()->addHours(2)->format('H:i:s'),
    ]);

    expect($event->isWithinTimeRange())->toBeFalse();
});

test('getStatusBadgeAttribute retorna Activo si está activo', function () {
    $event = ElectionEvent::factory()->active()->create();

    expect($event->status_badge)->toBe('Activo');
});

test('getStatusBadgeAttribute retorna Programado si es futuro', function () {
    $event = ElectionEvent::factory()->future()->inactive()->create();

    expect($event->status_badge)->toBe('Programado');
});

test('getStatusBadgeAttribute retorna Realizado si es pasado', function () {
    $event = ElectionEvent::factory()->past()->inactive()->create();

    expect($event->status_badge)->toBe('Realizado');
});

test('getTypeLabel retorna etiqueta correcta para simulacro', function () {
    $event = ElectionEvent::factory()->simulation()->create();

    expect($event->getTypeLabel())->toBe('Simulacro');
});

test('getTypeLabel retorna etiqueta correcta para día D real', function () {
    $event = ElectionEvent::factory()->real()->create();

    expect($event->getTypeLabel())->toBe('Día D Real');
});

test('factory simulation state crea evento tipo simulation', function () {
    $event = ElectionEvent::factory()->simulation()->create();

    expect($event->type)->toBe('simulation')
        ->and($event->simulation_number)->not->toBeNull();
});

test('factory real state crea evento tipo real', function () {
    $event = ElectionEvent::factory()->real()->create();

    expect($event->type)->toBe('real')
        ->and($event->simulation_number)->toBeNull();
});

test('factory today state crea evento con fecha de hoy', function () {
    $event = ElectionEvent::factory()->today()->create();

    expect($event->date->isToday())->toBeTrue();
});

test('factory future state crea evento con fecha futura', function () {
    $event = ElectionEvent::factory()->future()->create();

    expect($event->date->isFuture())->toBeTrue();
});

test('factory past state crea evento con fecha pasada', function () {
    $event = ElectionEvent::factory()->past()->create();

    expect($event->date->isPast())->toBeTrue();
});

test('se pueden crear múltiples simulacros para la misma campaña', function () {
    $campaign = Campaign::factory()->create();

    $sim1 = ElectionEvent::factory()->simulation()->for($campaign)->create();
    $sim2 = ElectionEvent::factory()->simulation()->for($campaign)->create();
    $sim3 = ElectionEvent::factory()->simulation()->for($campaign)->create();

    expect($campaign->electionEvents()->count())->toBe(3)
        ->and($campaign->electionEvents()->where('type', 'simulation')->count())->toBe(3);
});

test('vote records están vinculados al evento electoral', function () {
    $event = ElectionEvent::factory()->create();
    $voteRecord = VoteRecord::factory()->create(['election_event_id' => $event->id]);

    expect($event->voteRecords()->count())->toBe(1)
        ->and($voteRecord->electionEvent->id)->toBe($event->id);
});

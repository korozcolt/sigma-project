<?php

declare(strict_types=1);

use App\Models\Campaign;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Models\Voter;

test('can create message template', function () {
    $campaign = Campaign::factory()->create();
    $user = User::factory()->create();

    $template = MessageTemplate::factory()
        ->for($campaign)
        ->for($user, 'creator')
        ->create();

    expect($template)->toBeInstanceOf(MessageTemplate::class)
        ->and($template->campaign_id)->toBe($campaign->id)
        ->and($template->created_by)->toBe($user->id);
});

test('template belongs to campaign', function () {
    $template = MessageTemplate::factory()->create();

    expect($template->campaign)->toBeInstanceOf(Campaign::class);
});

test('template belongs to creator', function () {
    $template = MessageTemplate::factory()->create();

    expect($template->creator)->toBeInstanceOf(User::class);
});

test('template has many messages', function () {
    $template = MessageTemplate::factory()->create();
    Message::factory()->for($template, 'template')->count(3)->create();

    expect($template->messages)->toHaveCount(3)
        ->each->toBeInstanceOf(Message::class);
});

test('template can render content with variables', function () {
    $template = MessageTemplate::factory()->create([
        'content' => 'Hola {{nombre}}, mensaje de {{candidato}}',
    ]);

    $rendered = $template->renderContent([
        'nombre' => 'Juan',
        'candidato' => 'Pedro Pérez',
    ]);

    expect($rendered)->toBe('Hola Juan, mensaje de Pedro Pérez');
});

test('template can render subject with variables', function () {
    $template = MessageTemplate::factory()->create([
        'subject' => 'Mensaje de {{candidato}}',
    ]);

    $rendered = $template->renderSubject([
        'candidato' => 'Pedro Pérez',
    ]);

    expect($rendered)->toBe('Mensaje de Pedro Pérez');
});

test('template returns null subject when none set', function () {
    $template = MessageTemplate::factory()->create([
        'subject' => null,
    ]);

    $rendered = $template->renderSubject(['candidato' => 'Test']);

    expect($rendered)->toBeNull();
});

test('scope active returns only active templates', function () {
    MessageTemplate::factory()->count(3)->create();
    MessageTemplate::factory()->inactive()->count(2)->create();

    $active = MessageTemplate::active()->get();

    expect($active)->toHaveCount(3);

    $active->each(fn ($template) => expect($template->is_active)->toBeTrue());
});

test('scope by type filters correctly', function () {
    MessageTemplate::factory()->birthday()->count(2)->create();
    MessageTemplate::factory()->reminder()->count(3)->create();

    $birthday = MessageTemplate::byType('birthday')->get();

    expect($birthday)->toHaveCount(2);

    $birthday->each(fn ($template) => expect($template->type)->toBe('birthday'));
});

test('scope by channel filters correctly', function () {
    MessageTemplate::factory()->whatsapp()->count(2)->create();
    MessageTemplate::factory()->sms()->count(1)->create();
    MessageTemplate::factory()->email()->count(1)->create();

    $whatsapp = MessageTemplate::byChannel('whatsapp')->get();

    expect($whatsapp)->toHaveCount(2);

    $whatsapp->each(fn ($template) => expect($template->channel)->toBe('whatsapp'));
});

test('template can validate rate limit for voter', function () {
    $template = MessageTemplate::factory()->create([
        'max_per_voter_per_day' => 2,
    ]);
    $voter = Voter::factory()->create();

    // Sin mensajes previos, debe permitir
    expect($template->canSendToVoter($voter))->toBeTrue();

    // Crear 2 mensajes hoy
    Message::factory()
        ->for($template, 'template')
        ->for($voter)
        ->count(2)
        ->create(['created_at' => now()]);

    // Debe rechazar el tercero
    expect($template->fresh()->canSendToVoter($voter))->toBeFalse();
});

test('template can validate rate limit for campaign', function () {
    $campaign = Campaign::factory()->create();
    $template = MessageTemplate::factory()->for($campaign)->create([
        'max_per_campaign_per_hour' => 5,
    ]);

    // Sin mensajes previos, debe permitir
    expect($template->canSendInCampaign())->toBeTrue();

    // Crear 5 mensajes en la última hora
    Message::factory()
        ->for($campaign)
        ->for($template, 'template')
        ->count(5)
        ->create(['created_at' => now()->subMinutes(30)]);

    // Debe rechazar más mensajes
    expect($template->fresh()->canSendInCampaign())->toBeFalse();
});

test('birthday template factory creates correct type', function () {
    $template = MessageTemplate::factory()->birthday()->create();

    expect($template->type)->toBe('birthday')
        ->and($template->content)->toContain('cumpleaños');
});

test('restrictive template factory sets strict limits', function () {
    $template = MessageTemplate::factory()->restrictive()->create();

    expect($template->max_per_voter_per_day)->toBe(1)
        ->and($template->max_per_campaign_per_hour)->toBe(10)
        ->and($template->allowed_days)->toHaveCount(5);
});

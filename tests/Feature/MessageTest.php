<?php

declare(strict_types=1);

use App\Models\Campaign;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Voter;

test('can create message', function () {
    $campaign = Campaign::factory()->create();
    $voter = Voter::factory()->for($campaign)->create();

    $message = Message::factory()->for($campaign)->for($voter)->create();

    expect($message)->toBeInstanceOf(Message::class)
        ->and($message->campaign_id)->toBe($campaign->id)
        ->and($message->voter_id)->toBe($voter->id);
});

test('message belongs to campaign', function () {
    $message = Message::factory()->create();

    expect($message->campaign)->toBeInstanceOf(Campaign::class);
});

test('message belongs to voter', function () {
    $message = Message::factory()->create();

    expect($message->voter)->toBeInstanceOf(Voter::class);
});

test('message can belong to template', function () {
    $template = MessageTemplate::factory()->create();
    $message = Message::factory()->for($template, 'template')->create();

    expect($message->template)->toBeInstanceOf(MessageTemplate::class)
        ->and($message->template_id)->toBe($template->id);
});

test('message can be marked as sent', function () {
    $message = Message::factory()->pending()->create();

    $message->markAsSent('external_123');

    expect($message->fresh())
        ->status->toBe('sent')
        ->sent_at->not->toBeNull()
        ->external_id->toBe('external_123');
});

test('message can be marked as delivered', function () {
    $message = Message::factory()->sent()->create();

    $message->markAsDelivered();

    expect($message->fresh())
        ->status->toBe('delivered')
        ->delivered_at->not->toBeNull();
});

test('message can be marked as read', function () {
    $message = Message::factory()->delivered()->create();

    $message->markAsRead();

    expect($message->fresh())
        ->status->toBe('read')
        ->read_at->not->toBeNull();
});

test('message can be marked as failed', function () {
    $message = Message::factory()->pending()->create();

    $message->markAsFailed('Connection timeout');

    expect($message->fresh())
        ->status->toBe('failed')
        ->error_message->toBe('Connection timeout');
});

test('scope pending returns only pending messages', function () {
    Message::factory()->pending()->count(3)->create();
    Message::factory()->sent()->count(2)->create();

    $pending = Message::pending()->get();

    expect($pending)->toHaveCount(3);

    $pending->each(fn ($message) => expect($message->status)->toBe('pending'));
});

test('scope sent returns only sent messages', function () {
    Message::factory()->pending()->count(2)->create();
    Message::factory()->sent()->count(3)->create();

    $sent = Message::sent()->get();

    expect($sent)->toHaveCount(3);

    $sent->each(fn ($message) => expect($message->status)->toBe('sent'));
});

test('scope due to send returns scheduled messages ready to send', function () {
    Message::factory()->scheduled()->create([
        'scheduled_for' => now()->subHour(),
    ]);
    Message::factory()->scheduled()->create([
        'scheduled_for' => now()->addHour(),
    ]);
    Message::factory()->pending()->create();

    $due = Message::dueToSend()->get();

    expect($due)->toHaveCount(1);
});

test('birthday message factory creates correct type', function () {
    $message = Message::factory()->birthday()->create();

    expect($message->type)->toBe('birthday')
        ->and($message->content)->toContain('cumpleaños');
});

test('whatsapp message factory creates correct channel', function () {
    $message = Message::factory()->whatsapp()->create();

    expect($message->channel)->toBe('whatsapp')
        ->and($message->subject)->toBeNull();
});

test('email message factory creates subject', function () {
    $message = Message::factory()->email()->create();

    expect($message->channel)->toBe('email')
        ->and($message->subject)->not->toBeNull();
});

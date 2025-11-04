<?php

declare(strict_types=1);

use App\Models\Campaign;
use App\Models\MessageBatch;
use App\Models\MessageTemplate;
use App\Models\User;

test('can create message batch', function () {
    $campaign = Campaign::factory()->create();
    $user = User::factory()->create();

    $batch = MessageBatch::factory()
        ->for($campaign)
        ->for($user, 'creator')
        ->create();

    expect($batch)->toBeInstanceOf(MessageBatch::class)
        ->and($batch->campaign_id)->toBe($campaign->id)
        ->and($batch->created_by)->toBe($user->id);
});

test('batch belongs to campaign', function () {
    $batch = MessageBatch::factory()->create();

    expect($batch->campaign)->toBeInstanceOf(Campaign::class);
});

test('batch belongs to creator', function () {
    $batch = MessageBatch::factory()->create();

    expect($batch->creator)->toBeInstanceOf(User::class);
});

test('batch can belong to template', function () {
    $template = MessageTemplate::factory()->create();
    $batch = MessageBatch::factory()->for($template, 'template')->create();

    expect($batch->template)->toBeInstanceOf(MessageTemplate::class)
        ->and($batch->template_id)->toBe($template->id);
});

test('batch can be marked as processing', function () {
    $batch = MessageBatch::factory()->pending()->create();

    $batch->markAsProcessing();

    expect($batch->fresh())
        ->status->toBe('processing')
        ->started_at->not->toBeNull();
});

test('batch can be marked as completed', function () {
    $batch = MessageBatch::factory()->processing()->create();

    $batch->markAsCompleted();

    expect($batch->fresh())
        ->status->toBe('completed')
        ->completed_at->not->toBeNull();
});

test('batch can be marked as failed', function () {
    $batch = MessageBatch::factory()->processing()->create();

    $batch->markAsFailed();

    expect($batch->fresh())
        ->status->toBe('failed')
        ->completed_at->not->toBeNull();
});

test('batch can increment sent count', function () {
    $batch = MessageBatch::factory()->create(['sent_count' => 0]);

    $batch->incrementSent();
    $batch->incrementSent();

    expect($batch->fresh()->sent_count)->toBe(2);
});

test('batch can increment failed count', function () {
    $batch = MessageBatch::factory()->create(['failed_count' => 0]);

    $batch->incrementFailed();

    expect($batch->fresh()->failed_count)->toBe(1);
});

test('batch calculates progress percentage correctly', function () {
    $batch = MessageBatch::factory()->create([
        'total_recipients' => 100,
        'sent_count' => 60,
        'failed_count' => 10,
    ]);

    expect($batch->getProgressPercentage())->toBe(70.0);
});

test('batch calculates success rate correctly', function () {
    $batch = MessageBatch::factory()->create([
        'sent_count' => 80,
        'failed_count' => 20,
    ]);

    expect($batch->getSuccessRate())->toBe(80.0);
});

test('batch calculates delivery rate correctly', function () {
    $batch = MessageBatch::factory()->create([
        'sent_count' => 100,
        'delivered_count' => 85,
    ]);

    expect($batch->getDeliveryRate())->toBe(85.0);
});

test('scope pending returns only pending batches', function () {
    MessageBatch::factory()->pending()->count(2)->create();
    MessageBatch::factory()->completed()->count(3)->create();

    $pending = MessageBatch::pending()->get();

    expect($pending)->toHaveCount(2);

    $pending->each(fn ($batch) => expect($batch->status)->toBe('pending'));
});

test('scope due to process returns batches ready to process', function () {
    MessageBatch::factory()->scheduled()->create([
        'scheduled_for' => now()->subHour(),
    ]);
    MessageBatch::factory()->scheduled()->create([
        'scheduled_for' => now()->addHour(),
    ]);

    $due = MessageBatch::dueToProcess()->get();

    expect($due)->toHaveCount(1);
});

test('batch state methods return correct booleans', function () {
    $pending = MessageBatch::factory()->pending()->create();
    $processing = MessageBatch::factory()->processing()->create();
    $completed = MessageBatch::factory()->completed()->create();
    $failed = MessageBatch::factory()->failed()->create();

    expect($pending->isPending())->toBeTrue()
        ->and($processing->isProcessing())->toBeTrue()
        ->and($completed->isCompleted())->toBeTrue()
        ->and($failed->isFailed())->toBeTrue();
});

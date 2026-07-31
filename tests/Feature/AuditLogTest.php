<?php

use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\User;

test('audit log persists and casts old/new values as arrays', function () {
    $log = AuditLog::factory()->create([
        'old_values' => ['status' => 'PENDING_REVIEW'],
        'new_values' => ['status' => 'CONFIRMED'],
    ]);

    expect($log->fresh())
        ->old_values->toBe(['status' => 'PENDING_REVIEW'])
        ->new_values->toBe(['status' => 'CONFIRMED']);
});

test('audit log resolves auditable, user, and campaign relations', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->create();

    $log = AuditLog::factory()->create([
        'auditable_type' => User::class,
        'auditable_id' => $user->id,
        'user_id' => $user->id,
        'campaign_id' => $campaign->id,
    ]);

    expect($log->auditable)->toBeInstanceOf(User::class)
        ->and($log->auditable->id)->toBe($user->id)
        ->and($log->user)->toBeInstanceOf(User::class)
        ->and($log->campaign)->toBeInstanceOf(Campaign::class)
        ->and($log->campaign->id)->toBe($campaign->id);
});

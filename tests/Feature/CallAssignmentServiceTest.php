<?php

declare(strict_types=1);

use App\Enums\VoterStatus;
use App\Models\CallAssignment;
use App\Models\Campaign;
use App\Models\User;
use App\Models\Voter;
use App\Services\CallAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('loadBatchForCaller does not leak voters from another campaign (OUTR-01)', function () {
    $campaignA = Campaign::factory()->create();
    $campaignB = Campaign::factory()->create();
    $caller = User::factory()->create();
    $assignedBy = User::factory()->create();

    Voter::factory()->count(3)->create([
        'campaign_id' => $campaignA->id,
        'status' => VoterStatus::PENDING_REVIEW,
        'phone' => '3001111111',
    ]);

    Voter::factory()->count(5)->create([
        'campaign_id' => $campaignB->id,
        'status' => VoterStatus::PENDING_REVIEW,
        'phone' => '3002222222',
    ]);

    $service = app(CallAssignmentService::class);
    $assigned = $service->loadBatchForCaller($campaignA, $caller, $assignedBy, targetQueueSize: 10);

    expect($assigned)->toBe(3);

    $assignments = CallAssignment::where('assigned_to', $caller->id)->get();

    expect($assignments)->toHaveCount(3);

    foreach ($assignments as $assignment) {
        expect($assignment->campaign_id)->toBe($campaignA->id)
            ->and($assignment->voter->campaign_id)->toBe($campaignA->id);
    }
});

test('loadBatchForCaller does not assign a voter with an existing pending assignment (OUTR-05)', function () {
    $campaign = Campaign::factory()->create();
    $caller = User::factory()->create();
    $otherCaller = User::factory()->create();
    $assignedBy = User::factory()->create();

    $alreadyAssigned = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => VoterStatus::PENDING_REVIEW,
        'phone' => '3003333333',
    ]);

    CallAssignment::factory()->pending()->create([
        'voter_id' => $alreadyAssigned->id,
        'campaign_id' => $campaign->id,
        'assigned_to' => $otherCaller->id,
        'assigned_by' => $assignedBy->id,
    ]);

    Voter::factory()->count(2)->create([
        'campaign_id' => $campaign->id,
        'status' => VoterStatus::PENDING_REVIEW,
        'phone' => '3004444444',
    ]);

    $service = app(CallAssignmentService::class);
    $assigned = $service->loadBatchForCaller($campaign, $caller, $assignedBy, targetQueueSize: 10);

    expect($assigned)->toBe(2)
        ->and(CallAssignment::where('voter_id', $alreadyAssigned->id)->where('assigned_to', $caller->id)->exists())->toBeFalse();
});

test('loadBatchForCaller does not assign a voter with an existing in-progress assignment (OUTR-05)', function () {
    $campaign = Campaign::factory()->create();
    $caller = User::factory()->create();
    $otherCaller = User::factory()->create();
    $assignedBy = User::factory()->create();

    $inProgressVoter = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => VoterStatus::PENDING_REVIEW,
        'phone' => '3005555555',
    ]);

    CallAssignment::factory()->inProgress()->create([
        'voter_id' => $inProgressVoter->id,
        'campaign_id' => $campaign->id,
        'assigned_to' => $otherCaller->id,
        'assigned_by' => $assignedBy->id,
    ]);

    $service = app(CallAssignmentService::class);
    $assigned = $service->loadBatchForCaller($campaign, $caller, $assignedBy, targetQueueSize: 10);

    expect($assigned)->toBe(0)
        ->and(CallAssignment::where('voter_id', $inProgressVoter->id)->where('assigned_to', $caller->id)->exists())->toBeFalse();
});

test('loadBatchForCaller respects targetQueueSize as a ceiling across repeated calls for the same caller (OUTR-05)', function () {
    $campaign = Campaign::factory()->create();
    $caller = User::factory()->create();
    $assignedBy = User::factory()->create();

    Voter::factory()->count(10)->create([
        'campaign_id' => $campaign->id,
        'status' => VoterStatus::PENDING_REVIEW,
        'phone' => '3006666666',
    ]);

    $service = app(CallAssignmentService::class);

    $firstBatch = $service->loadBatchForCaller($campaign, $caller, $assignedBy, targetQueueSize: 5);
    $secondBatch = $service->loadBatchForCaller($campaign, $caller, $assignedBy, targetQueueSize: 5);

    expect($firstBatch)->toBe(5)
        ->and($secondBatch)->toBe(0)
        ->and(CallAssignment::where('assigned_to', $caller->id)->count())->toBe(5);
});

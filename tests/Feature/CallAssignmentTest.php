<?php

declare(strict_types=1);

use App\Models\CallAssignment;
use App\Models\Campaign;
use App\Models\User;
use App\Models\Voter;
use App\Services\CallAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can create a call assignment', function () {
    $voter = Voter::factory()->create();
    $caller = User::factory()->create();
    $assignedBy = User::factory()->create();
    $campaign = Campaign::factory()->create();

    $assignment = CallAssignment::create([
        'voter_id' => $voter->id,
        'assigned_to' => $caller->id,
        'assigned_by' => $assignedBy->id,
        'campaign_id' => $campaign->id,
        'status' => 'pending',
        'priority' => 'medium',
    ]);

    expect($assignment)->toBeInstanceOf(CallAssignment::class)
        ->and($assignment->voter_id)->toBe($voter->id)
        ->and($assignment->assigned_to)->toBe($caller->id)
        ->and($assignment->status)->toBe('pending')
        ->and($assignment->priority)->toBe('medium');
});

it('has correct relationships', function () {
    $assignment = CallAssignment::factory()->create();

    expect($assignment->voter)->toBeInstanceOf(Voter::class)
        ->and($assignment->assignedTo)->toBeInstanceOf(User::class)
        ->and($assignment->assignedBy)->toBeInstanceOf(User::class)
        ->and($assignment->campaign)->toBeInstanceOf(Campaign::class);
});

it('can mark assignment as in progress', function () {
    $assignment = CallAssignment::factory()->pending()->create();

    expect($assignment->status)->toBe('pending');

    $assignment->markInProgress();

    expect($assignment->fresh()->status)->toBe('in_progress');
});

it('can mark assignment as completed', function () {
    $assignment = CallAssignment::factory()->inProgress()->create();

    expect($assignment->status)->toBe('in_progress')
        ->and($assignment->completed_at)->toBeNull();

    $assignment->markCompleted();

    $fresh = $assignment->fresh();
    expect($fresh->status)->toBe('completed')
        ->and($fresh->completed_at)->not->toBeNull();
});

it('can reassign to another caller', function () {
    $originalCaller = User::factory()->create();
    $newCaller = User::factory()->create();

    $assignment = CallAssignment::factory()->create([
        'assigned_to' => $originalCaller->id,
        'status' => 'pending',
    ]);

    expect($assignment->assigned_to)->toBe($originalCaller->id);

    $assignment->reassign($newCaller->id);

    $fresh = $assignment->fresh();
    expect($fresh->assigned_to)->toBe($newCaller->id)
        ->and($fresh->status)->toBe('reassigned');
});

it('can scope pending assignments', function () {
    CallAssignment::factory()->pending()->count(3)->create();
    CallAssignment::factory()->completed()->count(2)->create();

    $pending = CallAssignment::pending()->get();

    expect($pending)->toHaveCount(3);
});

it('can scope in progress assignments', function () {
    CallAssignment::factory()->inProgress()->count(2)->create();
    CallAssignment::factory()->pending()->count(3)->create();

    $inProgress = CallAssignment::inProgress()->get();

    expect($inProgress)->toHaveCount(2);
});

it('can scope completed assignments', function () {
    CallAssignment::factory()->completed()->count(4)->create();
    CallAssignment::factory()->pending()->count(2)->create();

    $completed = CallAssignment::completed()->get();

    expect($completed)->toHaveCount(4);
});

it('can scope by campaign', function () {
    $campaign1 = Campaign::factory()->create();
    $campaign2 = Campaign::factory()->create();

    CallAssignment::factory()->count(3)->create(['campaign_id' => $campaign1->id]);
    CallAssignment::factory()->count(2)->create(['campaign_id' => $campaign2->id]);

    $forCampaign1 = CallAssignment::forCampaign($campaign1->id)->get();

    expect($forCampaign1)->toHaveCount(3);
});

it('can scope by caller', function () {
    $caller1 = User::factory()->create();
    $caller2 = User::factory()->create();

    CallAssignment::factory()->count(3)->create(['assigned_to' => $caller1->id]);
    CallAssignment::factory()->count(2)->create(['assigned_to' => $caller2->id]);

    $forCaller1 = CallAssignment::forCaller($caller1->id)->get();

    expect($forCaller1)->toHaveCount(3);
});

it('can scope by priority', function () {
    CallAssignment::factory()->highPriority()->count(2)->create();
    CallAssignment::factory()->urgent()->count(1)->create();
    CallAssignment::factory()->mediumPriority()->count(3)->create();

    $high = CallAssignment::byPriority('high')->get();

    expect($high)->toHaveCount(2);
});

it('can scope high priority assignments', function () {
    CallAssignment::factory()->highPriority()->count(2)->create();
    CallAssignment::factory()->urgent()->count(3)->create();
    CallAssignment::factory()->mediumPriority()->count(4)->create();

    $highPriority = CallAssignment::highPriority()->get();

    expect($highPriority)->toHaveCount(5); // high + urgent
});

it('can order by priority correctly', function () {
    CallAssignment::factory()->lowPriority()->create();
    CallAssignment::factory()->urgent()->create();
    CallAssignment::factory()->mediumPriority()->create();
    CallAssignment::factory()->highPriority()->create();

    $ordered = CallAssignment::orderedByPriority()->get();

    expect($ordered->first()->priority)->toBe('urgent')
        ->and($ordered->get(1)->priority)->toBe('high')
        ->and($ordered->get(2)->priority)->toBe('medium')
        ->and($ordered->last()->priority)->toBe('low');
});

it('has helper methods for status checks', function () {
    $pending = CallAssignment::factory()->pending()->create();
    $inProgress = CallAssignment::factory()->inProgress()->create();
    $completed = CallAssignment::factory()->completed()->create();

    expect($pending->isPending())->toBeTrue()
        ->and($pending->isInProgress())->toBeFalse()
        ->and($pending->isCompleted())->toBeFalse();

    expect($inProgress->isPending())->toBeFalse()
        ->and($inProgress->isInProgress())->toBeTrue()
        ->and($inProgress->isCompleted())->toBeFalse();

    expect($completed->isPending())->toBeFalse()
        ->and($completed->isInProgress())->toBeFalse()
        ->and($completed->isCompleted())->toBeTrue();
});

it('has helper methods for priority checks', function () {
    $urgent = CallAssignment::factory()->urgent()->create();
    $high = CallAssignment::factory()->highPriority()->create();
    $medium = CallAssignment::factory()->mediumPriority()->create();

    expect($urgent->isUrgent())->toBeTrue()
        ->and($urgent->isHighPriority())->toBeTrue();

    expect($high->isUrgent())->toBeFalse()
        ->and($high->isHighPriority())->toBeTrue();

    expect($medium->isUrgent())->toBeFalse()
        ->and($medium->isHighPriority())->toBeFalse();
});

// CallAssignmentService Tests

it('can assign a single voter via service', function () {
    $voter = Voter::factory()->create();
    $caller = User::factory()->create();
    $assignedBy = User::factory()->create();
    $campaign = Campaign::factory()->create();

    $service = new CallAssignmentService;
    $assignment = $service->assignVoter($voter, $caller, $campaign, $assignedBy, 'high');

    expect($assignment)->toBeInstanceOf(CallAssignment::class)
        ->and($assignment->voter_id)->toBe($voter->id)
        ->and($assignment->assigned_to)->toBe($caller->id)
        ->and($assignment->priority)->toBe('high')
        ->and($assignment->status)->toBe('pending');
});

it('can assign multiple voters via service', function () {
    $voters = Voter::factory()->count(5)->create();
    $caller = User::factory()->create();
    $assignedBy = User::factory()->create();
    $campaign = Campaign::factory()->create();

    $service = new CallAssignmentService;
    $assignments = $service->assignVoters($campaign, $caller, $voters, $assignedBy);

    expect($assignments)->toHaveCount(5);
    expect($assignments->first())->toBeInstanceOf(CallAssignment::class);
});

it('can auto-assign voters with balanced distribution', function () {
    $voters = Voter::factory()->count(10)->create();
    $callers = User::factory()->count(3)->create();
    $assignedBy = User::factory()->create();
    $campaign = Campaign::factory()->create();

    $service = new CallAssignmentService;
    $assignments = $service->autoAssignVoters($campaign, $voters, $callers, $assignedBy);

    expect($assignments)->toHaveCount(10);

    // Check distribution is balanced (3-4 per caller for 10 voters and 3 callers)
    foreach ($callers as $caller) {
        $count = $assignments->where('assigned_to', $caller->id)->count();
        expect($count)->toBeGreaterThanOrEqual(3)
            ->and($count)->toBeLessThanOrEqual(4);
    }
});

it('throws exception when auto-assigning with no callers', function () {
    $voters = Voter::factory()->count(5)->create();
    $campaign = Campaign::factory()->create();
    $assignedBy = User::factory()->create();

    $service = new CallAssignmentService;
    $service->autoAssignVoters($campaign, $voters, collect(), $assignedBy);
})->throws(\InvalidArgumentException::class, 'No callers available');

it('can get caller workload statistics', function () {
    $caller = User::factory()->create();
    $campaign = Campaign::factory()->create();

    CallAssignment::factory()->pending()->count(3)->create([
        'campaign_id' => $campaign->id,
        'assigned_to' => $caller->id,
    ]);
    CallAssignment::factory()->inProgress()->count(2)->create([
        'campaign_id' => $campaign->id,
        'assigned_to' => $caller->id,
    ]);
    CallAssignment::factory()->completed()->count(5)->create([
        'campaign_id' => $campaign->id,
        'assigned_to' => $caller->id,
    ]);

    $service = new CallAssignmentService;
    $workload = $service->getCallerWorkload($campaign, collect([$caller]));

    expect($workload)->toHaveCount(1);
    $stats = $workload->first();
    expect($stats['pending_count'])->toBe(3)
        ->and($stats['in_progress_count'])->toBe(2)
        ->and($stats['completed_count'])->toBe(5)
        ->and($stats['total_count'])->toBe(10);
});

it('can reassign pending assignments', function () {
    $fromCaller = User::factory()->create();
    $toCaller = User::factory()->create();
    $campaign = Campaign::factory()->create();

    CallAssignment::factory()->pending()->count(5)->create([
        'campaign_id' => $campaign->id,
        'assigned_to' => $fromCaller->id,
    ]);

    $service = new CallAssignmentService;
    $count = $service->reassignPending($fromCaller, $toCaller, $campaign);

    expect($count)->toBe(5);
    expect(CallAssignment::forCaller($toCaller->id)->count())->toBe(5);
    expect(CallAssignment::forCaller($fromCaller->id)->pending()->count())->toBe(0);
});

it('can get next assignment for caller', function () {
    $caller = User::factory()->create();
    $campaign = Campaign::factory()->create();

    CallAssignment::factory()->urgent()->pending()->create([
        'campaign_id' => $campaign->id,
        'assigned_to' => $caller->id,
    ]);
    CallAssignment::factory()->mediumPriority()->pending()->create([
        'campaign_id' => $campaign->id,
        'assigned_to' => $caller->id,
    ]);

    $service = new CallAssignmentService;
    $next = $service->getNextAssignment($caller, $campaign);

    expect($next)->not->toBeNull()
        ->and($next->priority)->toBe('urgent');
});

it('can get caller queue with priority ordering', function () {
    $caller = User::factory()->create();
    $campaign = Campaign::factory()->create();

    CallAssignment::factory()->lowPriority()->pending()->create([
        'campaign_id' => $campaign->id,
        'assigned_to' => $caller->id,
    ]);
    CallAssignment::factory()->urgent()->pending()->create([
        'campaign_id' => $campaign->id,
        'assigned_to' => $caller->id,
    ]);
    CallAssignment::factory()->highPriority()->pending()->create([
        'campaign_id' => $campaign->id,
        'assigned_to' => $caller->id,
    ]);

    $service = new CallAssignmentService;
    $queue = $service->getCallerQueue($caller, $campaign);

    expect($queue)->toHaveCount(3)
        ->and($queue->first()->priority)->toBe('urgent')
        ->and($queue->last()->priority)->toBe('low');
});

it('can get campaign statistics', function () {
    $campaign = Campaign::factory()->create();

    CallAssignment::factory()->pending()->count(5)->create(['campaign_id' => $campaign->id]);
    CallAssignment::factory()->inProgress()->count(3)->create(['campaign_id' => $campaign->id]);
    CallAssignment::factory()->completed()->count(2)->create(['campaign_id' => $campaign->id]);

    $service = new CallAssignmentService;
    $stats = $service->getCampaignStatistics($campaign);

    expect($stats['total'])->toBe(10)
        ->and($stats['pending'])->toBe(5)
        ->and($stats['in_progress'])->toBe(3)
        ->and($stats['completed'])->toBe(2)
        ->and($stats['completion_rate'])->toBe(20.0);
});

it('can bulk update priority', function () {
    $assignments = CallAssignment::factory()->mediumPriority()->count(5)->create();

    $service = new CallAssignmentService;
    $count = $service->updatePriority($assignments, 'high');

    expect($count)->toBe(5);
    expect(CallAssignment::byPriority('high')->count())->toBe(5);
});

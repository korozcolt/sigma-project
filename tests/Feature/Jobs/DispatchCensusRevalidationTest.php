<?php

use App\Enums\VoterStatus;
use App\Jobs\DispatchCensusRevalidation;
use App\Models\RevalidationRun;
use App\Models\User;
use App\Models\ValidationHistory;
use App\Models\Voter;
use App\Services\PollingPlaceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

beforeEach(function () {
    // No live adapters — keeps these tests deterministic/offline (no real HTTP calls),
    // matching the established pattern in ReconcileFallbackPollingPlacesTest. Full
    // RevalidationRun counter/coverage assertions live in RevalidationCoverageTest.php;
    // this file focuses on the job's own mechanics (leader/campaign scoping, scheduling).
    app()->bind(PollingPlaceResolver::class, fn () => new PollingPlaceResolver([]));
});

test('scoping by leaderId only processes that leader\'s matching voters', function () {
    $leader = User::factory()->create();
    $otherLeader = User::factory()->create();

    $leaderVoter = Voter::factory()->create(['status' => VoterStatus::PENDING_REVIEW, 'registered_by' => $leader->id]);
    $otherLeaderVoter = Voter::factory()->create(['status' => VoterStatus::PENDING_REVIEW, 'registered_by' => $otherLeader->id]);

    (new DispatchCensusRevalidation(leaderId: $leader->id))->handle();

    $run = RevalidationRun::latest()->first();

    expect($run->leader_id)->toBe($leader->id)
        ->and($run->total)->toBe(1);

    expect(ValidationHistory::where('voter_id', $leaderVoter->id)->exists())->toBeTrue()
        ->and(ValidationHistory::where('voter_id', $otherLeaderVoter->id)->exists())->toBeFalse();
});

test('scoping by campaignId writes it onto the RevalidationRun', function () {
    $voter = Voter::factory()->create(['status' => VoterStatus::PENDING_REVIEW]);

    (new DispatchCensusRevalidation(campaignId: $voter->campaign_id))->handle();

    $run = RevalidationRun::latest()->first();

    expect($run->campaign_id)->toBe($voter->campaign_id);
});

test('census:reconcile-validation is scheduled hourly with a 10-minute withoutOverlapping lock', function () {
    $consoleRoutes = file_get_contents(base_path('routes/console.php'));

    expect($consoleRoutes)->toContain("Schedule::command('census:reconcile-validation')->hourly()->withoutOverlapping(10)");
});

test('census:reconcile-validation command dispatches the job', function () {
    Bus::fake();

    Artisan::call('census:reconcile-validation');

    Bus::assertDispatched(DispatchCensusRevalidation::class);
});

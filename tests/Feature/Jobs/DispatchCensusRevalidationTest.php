<?php

use App\Enums\VoterStatus;
use App\Jobs\DispatchCensusRevalidation;
use App\Models\NationalIdentityRecord;
use App\Models\RegistraduriaLiveSession;
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

// Cap + exhaustion tracking (2captcha spend reduction)

test('never processes more than 50 voters in a single run', function () {
    $voters = Voter::factory()->count(51)->create(['status' => VoterStatus::PENDING_REVIEW]);

    (new DispatchCensusRevalidation)->handle();

    $touched = Voter::whereIn('id', $voters->pluck('id'))->where('reconciliation_attempts', '>', 0)->count();

    expect($touched)->toBe(50);
});

test('a failed resolution increments reconciliation_attempts by 1 and leaves reconciliation_exhausted_at null before the 5th failure', function () {
    $voter = Voter::factory()->create([
        'status' => VoterStatus::PENDING_REVIEW,
        'reconciliation_attempts' => 2,
    ]);

    (new DispatchCensusRevalidation)->handle();

    $fresh = $voter->fresh();

    expect($fresh->reconciliation_attempts)->toBe(3)
        ->and($fresh->reconciliation_exhausted_at)->toBeNull();
});

test('the 5th consecutive failed resolution sets reconciliation_exhausted_at to a non-null timestamp', function () {
    $voter = Voter::factory()->create([
        'status' => VoterStatus::PENDING_REVIEW,
        'reconciliation_attempts' => 4,
    ]);

    (new DispatchCensusRevalidation)->handle();

    $fresh = $voter->fresh();

    expect($fresh->reconciliation_attempts)->toBe(5)
        ->and($fresh->reconciliation_exhausted_at)->not->toBeNull();
});

test('a found resolution resets reconciliation_attempts to 0 and reconciliation_exhausted_at to null even after prior failed attempts', function () {
    $voter = Voter::factory()->create([
        'status' => VoterStatus::PENDING_REVIEW,
        'reconciliation_attempts' => 3,
    ]);

    NationalIdentityRecord::factory()->create(['cedula' => $voter->document_number]);

    (new DispatchCensusRevalidation)->handle();

    $fresh = $voter->fresh();

    expect($fresh->reconciliation_attempts)->toBe(0)
        ->and($fresh->reconciliation_exhausted_at)->toBeNull();
});

// 2captcha-duplicate-spend: mirrors ReconcileFallbackPollingPlacesTest's identical
// coverage for this job's sibling bump logic — see
// .planning/debug/resolved/2captcha-duplicate-spend.md
test('does not bump reconciliation_attempts when a RegistraduriaLiveSession claim exists for the voter (pending background collection)', function () {
    $voter = Voter::factory()->create([
        'document_number' => '6000000002',
        'status' => VoterStatus::PENDING_REVIEW,
        'reconciliation_attempts' => 2,
    ]);

    RegistraduriaLiveSession::factory()->create([
        'document_number' => '6000000002',
        'expires_at' => now()->addMinutes(5),
    ]);

    (new DispatchCensusRevalidation)->handle();

    $fresh = $voter->fresh();

    expect($fresh->reconciliation_attempts)->toBe(2)
        ->and($fresh->reconciliation_exhausted_at)->toBeNull();
});

test('a voter with reconciliation_exhausted_at already set is excluded from the run', function () {
    $voter = Voter::factory()->create([
        'status' => VoterStatus::PENDING_REVIEW,
        'reconciliation_attempts' => 5,
        'reconciliation_exhausted_at' => now(),
    ]);

    (new DispatchCensusRevalidation)->handle();

    $fresh = $voter->fresh();

    expect($fresh->reconciliation_attempts)->toBe(5)
        ->and($fresh->status)->toBe(VoterStatus::PENDING_REVIEW);
});

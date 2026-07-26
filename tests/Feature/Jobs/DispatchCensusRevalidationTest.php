<?php

use App\Enums\VoterStatus;
use App\Jobs\DispatchCensusRevalidation;
use App\Jobs\ValidateVoterAgainstCensus;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

test('dispatches ValidateVoterAgainstCensus for every pending or census-not-found voter, and skips other statuses', function () {
    Bus::fake();

    $pendingVoter = Voter::factory()->create(['status' => VoterStatus::PENDING_REVIEW]);
    $notFoundVoter = Voter::factory()->create(['status' => VoterStatus::CENSUS_NOT_FOUND]);
    $verifiedVoter = Voter::factory()->create(['status' => VoterStatus::VERIFIED_CENSUS]);

    (new DispatchCensusRevalidation)->handle();

    Bus::assertDispatched(ValidateVoterAgainstCensus::class, fn ($job) => $job->voter->is($pendingVoter));
    Bus::assertDispatched(ValidateVoterAgainstCensus::class, fn ($job) => $job->voter->is($notFoundVoter));
    Bus::assertNotDispatched(ValidateVoterAgainstCensus::class, fn ($job) => $job->voter->is($verifiedVoter));
});

test('scoping by leaderId only dispatches for that leader\'s matching voters', function () {
    Bus::fake();

    $leader = User::factory()->create();
    $otherLeader = User::factory()->create();

    $leaderVoter = Voter::factory()->create(['status' => VoterStatus::PENDING_REVIEW, 'registered_by' => $leader->id]);
    $otherLeaderVoter = Voter::factory()->create(['status' => VoterStatus::PENDING_REVIEW, 'registered_by' => $otherLeader->id]);

    (new DispatchCensusRevalidation(leaderId: $leader->id))->handle();

    Bus::assertDispatched(ValidateVoterAgainstCensus::class, fn ($job) => $job->voter->is($leaderVoter));
    Bus::assertNotDispatched(ValidateVoterAgainstCensus::class, fn ($job) => $job->voter->is($otherLeaderVoter));
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

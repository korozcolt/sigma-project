<?php

declare(strict_types=1);

use App\Filament\Widgets\DiaDLiveVotingChart;
use App\Models\Campaign;
use App\Models\ElectionEvent;
use App\Models\User;
use App\Models\Voter;
use App\Models\VoteRecord;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

function callDiaDLiveVotingChartData(DiaDLiveVotingChart $widget): array
{
    $method = new ReflectionMethod(DiaDLiveVotingChart::class, 'getData');
    $method->setAccessible(true);

    return $method->invoke($widget);
}

it('returns no_campaign empty state when no campaign is active', function () {
    $data = callDiaDLiveVotingChartData(new DiaDLiveVotingChart);

    expect($data)->toBe(['labels' => [], 'datasets' => [], 'emptyReason' => 'no_campaign']);
});

it('returns no_active_event empty state when campaign has no active election event', function () {
    $campaign = Campaign::factory()->create(['status' => 'active']);
    $user = User::factory()->create();
    $user->campaigns()->attach($campaign->id);
    $this->actingAs($user);

    $data = callDiaDLiveVotingChartData(new DiaDLiveVotingChart);

    expect($data)->toBe(['labels' => [], 'datasets' => [], 'emptyReason' => 'no_active_event']);
});

it('returns no_votes_yet empty state when active event has zero vote records', function () {
    $campaign = Campaign::factory()->create(['status' => 'active']);
    $user = User::factory()->create();
    $user->campaigns()->attach($campaign->id);
    $this->actingAs($user);
    ElectionEvent::factory()->create(['campaign_id' => $campaign->id, 'is_active' => true]);

    $data = callDiaDLiveVotingChartData(new DiaDLiveVotingChart);

    expect($data)->toBe(['labels' => [], 'datasets' => [], 'emptyReason' => 'no_votes_yet']);
});

it('backfills zero-vote hours as a flat continuation of the cumulative running total', function () {
    $campaign = Campaign::factory()->create(['status' => 'active']);
    $user = User::factory()->create();
    $user->campaigns()->attach($campaign->id);
    $this->actingAs($user);
    $event = ElectionEvent::factory()->create(['campaign_id' => $campaign->id, 'is_active' => true]);

    // vote_records has a DB-level unique constraint on (voter_id, election_event_id) -
    // each vote in this event must belong to a distinct voter.
    $voters = Voter::factory()->count(3)->create(['campaign_id' => $campaign->id]);

    VoteRecord::factory()->create([
        'voter_id' => $voters[0]->id, 'campaign_id' => $campaign->id, 'election_event_id' => $event->id,
        'voted_at' => now()->subHours(2)->startOfHour()->addMinutes(5),
    ]);
    VoteRecord::factory()->create([
        'voter_id' => $voters[1]->id, 'campaign_id' => $campaign->id, 'election_event_id' => $event->id,
        'voted_at' => now()->subHours(2)->startOfHour()->addMinutes(20),
    ]);
    // Deliberately skip the "now() - 1 hour" bucket - it must still appear, flat at 2.
    VoteRecord::factory()->create([
        'voter_id' => $voters[2]->id, 'campaign_id' => $campaign->id, 'election_event_id' => $event->id,
        'voted_at' => now(),
    ]);

    $data = callDiaDLiveVotingChartData(new DiaDLiveVotingChart);

    expect($data['labels'])->toHaveCount(3);
    expect($data['datasets'][0]['data'])->toBe([2, 2, 3]);
});

it('caches the aggregation for the TTL window, absorbing a second call without re-querying', function () {
    $campaign = Campaign::factory()->create(['status' => 'active']);
    $user = User::factory()->create();
    $user->campaigns()->attach($campaign->id);
    $this->actingAs($user);
    $event = ElectionEvent::factory()->create(['campaign_id' => $campaign->id, 'is_active' => true]);

    // vote_records has a DB-level unique constraint on (voter_id, election_event_id) -
    // each vote in this event must belong to a distinct voter.
    $voters = Voter::factory()->count(3)->create(['campaign_id' => $campaign->id]);

    VoteRecord::factory()->create([
        'voter_id' => $voters[0]->id, 'campaign_id' => $campaign->id, 'election_event_id' => $event->id, 'voted_at' => now(),
    ]);

    $first = callDiaDLiveVotingChartData(new DiaDLiveVotingChart);
    expect($first['datasets'][0]['data'])->toBe([1]);

    // Seeded AFTER the first getData() call, within the same 30s TTL window - a second
    // call must still return the pre-seed total, proving Cache::remember() absorbed it.
    VoteRecord::factory()->create([
        'voter_id' => $voters[1]->id, 'campaign_id' => $campaign->id, 'election_event_id' => $event->id, 'voted_at' => now(),
    ]);
    VoteRecord::factory()->create([
        'voter_id' => $voters[2]->id, 'campaign_id' => $campaign->id, 'election_event_id' => $event->id, 'voted_at' => now(),
    ]);

    $second = callDiaDLiveVotingChartData(new DiaDLiveVotingChart);
    expect($second['datasets'][0]['data'])->toBe([1]);
});

<?php

use App\Enums\UserRole;
use App\Enums\VoterStatus;
use App\Filament\Widgets\RejectionsCountersOverview;
use App\Models\Campaign;
use App\Models\User;
use App\Models\VerificationCall;
use App\Models\Voter;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses()->group('dashboard-widgets');

beforeEach(function () {
    Role::firstOrCreate(['name' => UserRole::SUPER_ADMIN->value, 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole(UserRole::SUPER_ADMIN->value);
    $this->actingAs($user);
});

test('rejections counters overview reports correct non-overlapping counts for the active campaign', function () {
    $campaign = Campaign::factory()->create(['status' => 'active']);

    Session::put('campaign_context.campaign_id', $campaign->id);
    Session::put('campaign_context.mode', 'single');

    Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => VoterStatus::REJECTED_CENSUS,
    ]);

    Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => VoterStatus::CENSUS_NOT_FOUND,
    ]);

    Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => VoterStatus::CORRECTION_REQUIRED,
    ]);

    $callRejectedVoter = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => VoterStatus::PENDING_REVIEW,
    ]);
    VerificationCall::factory()->rejected()->create(['voter_id' => $callRejectedVoter->id]);

    Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => VoterStatus::DUPLICATE,
    ]);

    Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => VoterStatus::REJECTED_OUT_OF_SCOPE,
    ]);

    Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => VoterStatus::CONFIRMED,
    ]);

    $instance = Livewire::test(RejectionsCountersOverview::class)->instance();
    $stats = (new ReflectionMethod($instance, 'getStats'))->invoke($instance);

    expect($stats[0]->getValue())->toBe(number_format(4))
        ->and($stats[1]->getValue())->toBe(number_format(1))
        ->and($stats[2]->getValue())->toBe(number_format(1));
});

test('rejections counters overview renders zeros with no active campaign', function () {
    Livewire::test(RejectionsCountersOverview::class)->assertOk();

    $instance = Livewire::test(RejectionsCountersOverview::class)->instance();
    $stats = (new ReflectionMethod($instance, 'getStats'))->invoke($instance);

    expect($stats[0]->getValue())->toBe(0)
        ->and($stats[1]->getValue())->toBe(0)
        ->and($stats[2]->getValue())->toBe(0);
});

test('rejections counters overview never leaks another campaign\'s voters into the active campaign\'s counts', function () {
    $activeCampaign = Campaign::factory()->create(['status' => 'active']);
    $otherCampaign = Campaign::factory()->create(['status' => 'active']);

    // Session is set to the OTHER campaign while creating its voters so
    // CampaignContext::enforceCampaignId() (which force-sets campaign_id from the
    // active session context on every Voter creation) doesn't silently reassign
    // them to a different campaign. The session is switched to the real active
    // campaign afterward, before the widget assertions below.
    Session::put('campaign_context.campaign_id', $otherCampaign->id);
    Session::put('campaign_context.mode', 'single');

    Voter::factory()->create([
        'campaign_id' => $otherCampaign->id,
        'status' => VoterStatus::REJECTED_CENSUS,
    ]);

    Voter::factory()->create([
        'campaign_id' => $otherCampaign->id,
        'status' => VoterStatus::DUPLICATE,
    ]);

    Voter::factory()->create([
        'campaign_id' => $otherCampaign->id,
        'status' => VoterStatus::REJECTED_OUT_OF_SCOPE,
    ]);

    Session::put('campaign_context.campaign_id', $activeCampaign->id);
    Session::put('campaign_context.mode', 'single');

    $instance = Livewire::test(RejectionsCountersOverview::class)->instance();
    $stats = (new ReflectionMethod($instance, 'getStats'))->invoke($instance);

    expect($stats[0]->getValue())->toBe(number_format(0))
        ->and($stats[1]->getValue())->toBe(number_format(0))
        ->and($stats[2]->getValue())->toBe(number_format(0));
});

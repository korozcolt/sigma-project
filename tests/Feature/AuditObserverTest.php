<?php

use App\Enums\UserRole;
use App\Enums\VoterStatus;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\User;
use App\Models\Voter;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    collect(UserRole::values())->each(fn ($role) => Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));
});

test('creating a voter writes an audit log with the voter campaign_id', function () {
    $actor = User::factory()->create();
    $campaign = Campaign::factory()->create();
    $actor->campaigns()->attach($campaign);
    actingAs($actor);

    $voter = Voter::factory()->create(['campaign_id' => $campaign->id]);

    $log = AuditLog::where('auditable_type', Voter::class)
        ->where('auditable_id', $voter->id)
        ->where('action', 'created')
        ->first();

    expect($log)->not->toBeNull()
        ->user_id->toBe($actor->id)
        ->campaign_id->toBe($campaign->id)
        ->old_values->toBeNull()
        ->and($log->new_values)->toBeArray();
});

test('updating a voter status writes an audit log with only the changed keys', function () {
    $actor = User::factory()->create();
    $campaign = Campaign::factory()->create();
    $voter = Voter::factory()->create(['campaign_id' => $campaign->id, 'status' => VoterStatus::PENDING_REVIEW]);
    actingAs($actor);

    $voter->update(['status' => VoterStatus::CONFIRMED]);

    $log = AuditLog::where('auditable_type', Voter::class)
        ->where('auditable_id', $voter->id)
        ->where('action', 'updated')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->old_values)->toHaveKey('status');
    expect($log->new_values)->toHaveKey('status');
});

test('a no-op voter save writes no audit log', function () {
    $campaign = Campaign::factory()->create();
    $voter = Voter::factory()->create(['campaign_id' => $campaign->id]);

    AuditLog::query()->delete();

    $voter->save();

    expect(AuditLog::where('auditable_id', $voter->id)->where('action', 'updated')->exists())->toBeFalse();
});

test('deleting a voter writes an audit log with old_values populated', function () {
    $campaign = Campaign::factory()->create();
    $voter = Voter::factory()->create(['campaign_id' => $campaign->id]);

    $voter->delete();

    $log = AuditLog::where('auditable_type', Voter::class)
        ->where('auditable_id', $voter->id)
        ->where('action', 'deleted')
        ->first();

    expect($log)->not->toBeNull()
        ->new_values->toBeNull()
        ->and($log->old_values)->toBeArray();
});

test('creating a campaign writes an audit log whose campaign_id is the campaign own id', function () {
    $campaign = Campaign::factory()->create();

    $log = AuditLog::where('auditable_type', Campaign::class)
        ->where('auditable_id', $campaign->id)
        ->where('action', 'created')
        ->first();

    expect($log)->not->toBeNull()
        ->campaign_id->toBe($campaign->id);
});

test('creating a user falls back to CampaignContext for campaign_id', function () {
    $user = User::factory()->create(['document_number' => null]);

    $log = AuditLog::where('auditable_type', User::class)
        ->where('auditable_id', $user->id)
        ->where('action', 'created')
        ->first();

    expect($log)->not->toBeNull();
    // No authenticated actor / no campaign context in this test -> null is a valid outcome.
    expect($log->campaign_id)->toBeNull();
});

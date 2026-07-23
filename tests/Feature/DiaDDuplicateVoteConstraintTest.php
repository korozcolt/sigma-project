<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Enums\VoterStatus;
use App\Filament\Pages\DiaD;
use App\Models\Campaign;
use App\Models\ElectionEvent;
use App\Models\User;
use App\Models\Voter;
use App\Models\VoteRecord;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

test('database rejects a second vote record for the same voter and election event', function () {
    $campaign = Campaign::factory()->create();
    $voter = Voter::factory()->create(['campaign_id' => $campaign->id]);
    $event = ElectionEvent::factory()->create(['campaign_id' => $campaign->id]);

    VoteRecord::factory()->create([
        'voter_id' => $voter->id,
        'campaign_id' => $campaign->id,
        'election_event_id' => $event->id,
    ]);

    expect(fn () => VoteRecord::factory()->create([
        'voter_id' => $voter->id,
        'campaign_id' => $campaign->id,
        'election_event_id' => $event->id,
    ]))->toThrow(QueryException::class);
});

test('migration dedupes pre-existing duplicate vote records before adding the unique constraint', function () {
    Schema::table('vote_records', function ($table) {
        $table->dropUnique('vote_records_voter_event_unique');
    });

    $campaign = Campaign::factory()->create();
    $voter = Voter::factory()->create(['campaign_id' => $campaign->id]);
    $event = ElectionEvent::factory()->create(['campaign_id' => $campaign->id]);

    $now = now();

    DB::table('vote_records')->insert([
        [
            'voter_id' => $voter->id,
            'campaign_id' => $campaign->id,
            'election_event_id' => $event->id,
            'voted_at' => $now,
            'created_at' => $now->copy()->subMinute(),
            'updated_at' => $now,
        ],
        [
            'voter_id' => $voter->id,
            'campaign_id' => $campaign->id,
            'election_event_id' => $event->id,
            'voted_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    expect(
        DB::table('vote_records')
            ->where('voter_id', $voter->id)
            ->where('election_event_id', $event->id)
            ->count()
    )->toBe(2);

    $migration = require database_path('migrations/2026_07_23_160000_add_unique_constraint_to_vote_records_table.php');
    $migration->up();

    expect(
        DB::table('vote_records')
            ->where('voter_id', $voter->id)
            ->where('election_event_id', $event->id)
            ->count()
    )->toBe(1);

    expect(fn () => DB::table('vote_records')->insert([
        'voter_id' => $voter->id,
        'campaign_id' => $campaign->id,
        'election_event_id' => $event->id,
        'voted_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]))->toThrow(QueryException::class);
});

test('marking did not vote is blocked when a vote record already exists for the active event', function () {
    Role::firstOrCreate(['name' => UserRole::LEADER->value, 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole(UserRole::LEADER->value);

    $campaign = Campaign::factory()->create(['status' => 'active']);
    $voter = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => VoterStatus::VOTED,
    ]);
    $event = ElectionEvent::factory()->create([
        'campaign_id' => $campaign->id,
        'is_active' => true,
    ]);

    VoteRecord::factory()->create([
        'voter_id' => $voter->id,
        'campaign_id' => $campaign->id,
        'election_event_id' => $event->id,
    ]);

    $this->actingAs($user);
    Session::put('campaign_context.campaign_id', $campaign->id);
    Session::put('campaign_context.mode', 'single');

    Livewire::test(DiaD::class)
        ->set('voterId', $voter->id)
        ->call('markDidNotVote');

    expect($voter->fresh()->status)->toBe(VoterStatus::VOTED);
});

test('duplicate insert throws code 23000 and DiaD reverts status with a warning notification on the race branch', function () {
    $campaign = Campaign::factory()->create();
    $voter = Voter::factory()->create(['campaign_id' => $campaign->id]);
    $event = ElectionEvent::factory()->create(['campaign_id' => $campaign->id]);

    VoteRecord::factory()->create([
        'voter_id' => $voter->id,
        'campaign_id' => $campaign->id,
        'election_event_id' => $event->id,
    ]);

    try {
        VoteRecord::factory()->create([
            'voter_id' => $voter->id,
            'campaign_id' => $campaign->id,
            'election_event_id' => $event->id,
        ]);

        $this->fail('Expected a QueryException with SQLSTATE 23000 to be thrown.');
    } catch (QueryException $e) {
        expect((int) $e->getCode())->toBe(23000);
    }

    $source = file_get_contents(app_path('Filament/Pages/DiaD.php'));

    expect($source)
        ->toContain('catch (\Illuminate\Database\QueryException $e)')
        ->toContain('(int) $e->getCode() === 23000')
        ->toContain("\$voter->update(['status' => \$previous]);");
});

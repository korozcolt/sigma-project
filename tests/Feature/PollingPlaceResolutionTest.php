<?php

use App\Enums\PollingPlaceSource;
use App\Models\PollingPlaceResolution;
use App\Models\User;
use App\Models\Voter;

use function Pest\Laravel\assertDatabaseHas;

it('can create a polling place resolution', function () {
    $voter = Voter::factory()->create();
    $user = User::factory()->create();

    $resolution = PollingPlaceResolution::factory()->create([
        'voter_id' => $voter->id,
        'new_source' => PollingPlaceSource::LIVE,
        'resolved_by' => $user->id,
        'resolved_via' => 'interactive',
    ]);

    expect($resolution)->toBeInstanceOf(PollingPlaceResolution::class);
    expect($resolution->new_source)->toBe(PollingPlaceSource::LIVE);
    expect($resolution->resolved_via)->toBe('interactive');

    assertDatabaseHas('polling_place_resolutions', [
        'voter_id' => $voter->id,
        'resolved_by' => $user->id,
        'resolved_via' => 'interactive',
    ]);
});

it('requires voter_id, new_source and resolved_via', function () {
    expect(fn () => PollingPlaceResolution::create([]))->toThrow(Exception::class);
});

it('casts previous_source and new_source to PollingPlaceSource enum', function () {
    $resolution = PollingPlaceResolution::factory()->create();

    expect($resolution->previous_source)->toBeNull();
    expect($resolution->new_source)->toBeInstanceOf(PollingPlaceSource::class);

    $resolutionWithPrevious = PollingPlaceResolution::factory()->create([
        'previous_source' => PollingPlaceSource::SNAPSHOT,
    ]);

    expect($resolutionWithPrevious->previous_source)->toBeInstanceOf(PollingPlaceSource::class);
});

it('has voter relationship', function () {
    $resolution = PollingPlaceResolution::factory()->create();

    expect($resolution->voter())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

it('can retrieve voter', function () {
    $voter = Voter::factory()->create(['first_name' => 'Juan']);
    $resolution = PollingPlaceResolution::factory()->create(['voter_id' => $voter->id]);

    $resolution->load('voter');

    expect($resolution->voter->id)->toBe($voter->id);
    expect($resolution->voter->first_name)->toBe('Juan');
});

it('has resolver relationship', function () {
    $resolution = PollingPlaceResolution::factory()->create();

    expect($resolution->resolver())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

it('can retrieve resolver user', function () {
    $user = User::factory()->create(['name' => 'Resolver User']);
    $resolution = PollingPlaceResolution::factory()->create(['resolved_by' => $user->id]);

    $resolution->load('resolver');

    expect($resolution->resolver->id)->toBe($user->id);
    expect($resolution->resolver->name)->toBe('Resolver User');
});

it('has pollingPlace relationship', function () {
    $resolution = PollingPlaceResolution::factory()->create();

    expect($resolution->pollingPlace())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

it('allows resolved_by to be null for a headless reconciliation actor', function () {
    $voter = Voter::factory()->create();

    $resolution = PollingPlaceResolution::factory()->reconciliation()->create([
        'voter_id' => $voter->id,
    ]);

    expect($resolution->resolved_by)->toBeNull();
    expect($resolution->resolved_via)->toBe('reconciliation');
});

it('scope forVoter returns only resolutions for specific voter', function () {
    $voter1 = Voter::factory()->create();
    $voter2 = Voter::factory()->create();

    PollingPlaceResolution::factory()->create(['voter_id' => $voter1->id]);
    PollingPlaceResolution::factory()->create(['voter_id' => $voter1->id]);
    PollingPlaceResolution::factory()->create(['voter_id' => $voter2->id]);

    $voter1Resolutions = PollingPlaceResolution::forVoter($voter1->id)->get();

    expect($voter1Resolutions)->toHaveCount(2);
    expect($voter1Resolutions->every(fn ($r) => $r->voter_id === $voter1->id))->toBeTrue();
});

it('scope byVia filters by resolved_via', function () {
    PollingPlaceResolution::factory()->interactive()->create();
    PollingPlaceResolution::factory()->interactive()->create();
    PollingPlaceResolution::factory()->reconciliation()->create();

    $interactiveResolutions = PollingPlaceResolution::byVia('interactive')->get();

    expect($interactiveResolutions)->toHaveCount(2);
    expect($interactiveResolutions->every(fn ($r) => $r->resolved_via === 'interactive'))->toBeTrue();
});

it('scope recent orders by most recent first', function () {
    $resolution1 = PollingPlaceResolution::factory()->create(['created_at' => now()->subDays(2)]);
    $resolution2 = PollingPlaceResolution::factory()->create(['created_at' => now()->subDays(1)]);
    $resolution3 = PollingPlaceResolution::factory()->create(['created_at' => now()]);

    $resolutions = PollingPlaceResolution::recent()->get();

    expect($resolutions->first()->id)->toBe($resolution3->id);
    expect($resolutions->last()->id)->toBe($resolution1->id);
});

it('deleting voter cascades delete polling place resolutions', function () {
    $voter = Voter::factory()->create();
    $resolution = PollingPlaceResolution::factory()->create(['voter_id' => $voter->id]);

    $voter->forceDelete();

    expect(PollingPlaceResolution::find($resolution->id))->toBeNull();
});

it('deleting resolver user nulls resolved_by instead of deleting the resolution', function () {
    $user = User::factory()->create();
    $resolution = PollingPlaceResolution::factory()->create(['resolved_by' => $user->id]);

    $user->forceDelete();

    expect(PollingPlaceResolution::find($resolution->id))->not->toBeNull();
    expect($resolution->fresh()->resolved_by)->toBeNull();
});

it('voter can have multiple polling place resolutions', function () {
    $voter = Voter::factory()->create();

    PollingPlaceResolution::factory()->count(3)->create(['voter_id' => $voter->id]);

    $voter->load('pollingPlaceResolutions');

    expect($voter->pollingPlaceResolutions)->toHaveCount(3);
});

it('factory interactive state works correctly', function () {
    $resolution = PollingPlaceResolution::factory()->interactive()->create();

    expect($resolution->resolved_via)->toBe('interactive');
    expect($resolution->resolved_by)->not->toBeNull();
});

it('factory reconciliation state works correctly', function () {
    $resolution = PollingPlaceResolution::factory()->reconciliation()->create();

    expect($resolution->resolved_by)->toBeNull();
    expect($resolution->resolved_via)->toBe('reconciliation');
});

it('voter casts polling_place_source to PollingPlaceSource and resolved_at to datetime', function () {
    $voter = Voter::factory()->create([
        'polling_place_source' => PollingPlaceSource::SNAPSHOT,
        'polling_place_resolved_at' => now(),
    ]);

    expect($voter->polling_place_source)->toBeInstanceOf(PollingPlaceSource::class);
    expect($voter->polling_place_source)->toBe(PollingPlaceSource::SNAPSHOT);
    expect($voter->polling_place_resolved_at)->toBeInstanceOf(Illuminate\Support\Carbon::class);
});

it('voter polling_place_source has no default and is nullable', function () {
    $voter = Voter::factory()->create();

    expect($voter->polling_place_source)->toBeNull();
});

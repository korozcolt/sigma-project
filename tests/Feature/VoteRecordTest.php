<?php

declare(strict_types=1);

use App\Models\Campaign;
use App\Models\User;
use App\Models\VoteRecord;
use App\Models\Voter;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    $this->campaign = Campaign::factory()->create();
    $this->user = User::factory()->create();
    $this->voter = Voter::factory()->for($this->campaign)->create();
});

it('can create a vote record', function () {
    $voteRecord = VoteRecord::factory()->create([
        'voter_id' => $this->voter->id,
        'campaign_id' => $this->campaign->id,
        'recorded_by' => $this->user->id,
    ]);

    expect($voteRecord)->toBeInstanceOf(VoteRecord::class)
        ->and($voteRecord->voter_id)->toBe($this->voter->id)
        ->and($voteRecord->campaign_id)->toBe($this->campaign->id)
        ->and($voteRecord->recorded_by)->toBe($this->user->id);

    assertDatabaseHas('vote_records', [
        'id' => $voteRecord->id,
        'voter_id' => $this->voter->id,
    ]);
});

it('has fillable attributes', function () {
    $fillable = [
        'voter_id',
        'campaign_id',
        'recorded_by',
        'voted_at',
        'photo_path',
        'latitude',
        'longitude',
        'polling_station',
        'notes',
        'ip_address',
        'user_agent',
    ];

    $voteRecord = new VoteRecord;

    expect($voteRecord->getFillable())->toBe($fillable);
});

it('casts voted_at to datetime', function () {
    $voteRecord = VoteRecord::factory()->create();

    expect($voteRecord->voted_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

it('casts latitude and longitude to decimal', function () {
    $voteRecord = VoteRecord::factory()->create([
        'latitude' => 4.6097100,
        'longitude' => -74.0817500,
    ]);

    expect($voteRecord->latitude)->toBe('4.6097100')
        ->and($voteRecord->longitude)->toBe('-74.0817500');
});

it('belongs to a voter', function () {
    $voteRecord = VoteRecord::factory()->create();

    expect($voteRecord->voter())->toBeInstanceOf(BelongsTo::class)
        ->and($voteRecord->voter)->toBeInstanceOf(Voter::class);
});

it('belongs to a campaign', function () {
    $voteRecord = VoteRecord::factory()->create();

    expect($voteRecord->campaign())->toBeInstanceOf(BelongsTo::class)
        ->and($voteRecord->campaign)->toBeInstanceOf(Campaign::class);
});

it('belongs to a user who recorded it', function () {
    $voteRecord = VoteRecord::factory()->create();

    expect($voteRecord->recordedBy())->toBeInstanceOf(BelongsTo::class)
        ->and($voteRecord->recordedBy)->toBeInstanceOf(User::class);
});

it('voter has many vote records', function () {
    expect($this->voter->voteRecords())->toBeInstanceOf(HasMany::class);

    VoteRecord::factory()->count(3)->create(['voter_id' => $this->voter->id]);

    expect($this->voter->voteRecords)->toHaveCount(3);
});

it('campaign has many vote records', function () {
    expect($this->campaign->voteRecords())->toBeInstanceOf(HasMany::class);

    VoteRecord::factory()->count(5)->create(['campaign_id' => $this->campaign->id]);

    expect($this->campaign->voteRecords)->toHaveCount(5);
});

it('can have a photo path', function () {
    $voteRecord = VoteRecord::factory()->withPhoto()->create();

    expect($voteRecord->photo_path)->not->toBeNull()
        ->and($voteRecord->hasPhoto())->toBeTrue();
});

it('can check if has photo', function () {
    $withPhoto = VoteRecord::factory()->withPhoto()->create();
    $withoutPhoto = VoteRecord::factory()->create(['photo_path' => null]);

    expect($withPhoto->hasPhoto())->toBeTrue()
        ->and($withoutPhoto->hasPhoto())->toBeFalse();
});

it('can have GPS location', function () {
    $voteRecord = VoteRecord::factory()->withLocation()->create();

    expect($voteRecord->latitude)->not->toBeNull()
        ->and($voteRecord->longitude)->not->toBeNull()
        ->and($voteRecord->hasLocation())->toBeTrue();
});

it('can check if has location', function () {
    $withLocation = VoteRecord::factory()->withLocation()->create();
    $withoutLocation = VoteRecord::factory()->create([
        'latitude' => null,
        'longitude' => null,
    ]);

    expect($withLocation->hasLocation())->toBeTrue()
        ->and($withoutLocation->hasLocation())->toBeFalse();
});

it('returns formatted location string', function () {
    $voteRecord = VoteRecord::factory()->create([
        'latitude' => 4.6097100,
        'longitude' => -74.0817500,
    ]);

    expect($voteRecord->location)->toBe('4.6097100, -74.0817500');
});

it('returns null for location when coordinates are missing', function () {
    $voteRecord = VoteRecord::factory()->create([
        'latitude' => null,
        'longitude' => null,
    ]);

    expect($voteRecord->location)->toBeNull();
});

it('can store polling station information', function () {
    $pollingStation = 'Mesa 15 - Puesto 3';
    $voteRecord = VoteRecord::factory()->create([
        'polling_station' => $pollingStation,
    ]);

    expect($voteRecord->polling_station)->toBe($pollingStation);
});

it('can store IP address and user agent', function () {
    $voteRecord = VoteRecord::factory()->create([
        'ip_address' => '192.168.1.1',
        'user_agent' => 'Mozilla/5.0',
    ]);

    expect($voteRecord->ip_address)->toBe('192.168.1.1')
        ->and($voteRecord->user_agent)->toBe('Mozilla/5.0');
});

it('can create complete vote record with factory', function () {
    $voteRecord = VoteRecord::factory()->complete()->create();

    expect($voteRecord->hasPhoto())->toBeTrue()
        ->and($voteRecord->hasLocation())->toBeTrue()
        ->and($voteRecord->polling_station)->not->toBeNull()
        ->and($voteRecord->notes)->not->toBeNull();
});

it('stores notes about the vote', function () {
    $notes = 'Votante llegó temprano y sin contratiempos';
    $voteRecord = VoteRecord::factory()->create(['notes' => $notes]);

    expect($voteRecord->notes)->toBe($notes);
});

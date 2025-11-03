<?php

use App\Enums\VoterStatus;
use App\Models\Campaign;
use App\Models\Municipality;
use App\Models\Neighborhood;
use App\Models\User;
use App\Models\Voter;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertSoftDeleted;

it('can create a voter', function () {
    $campaign = Campaign::factory()->create();
    $municipality = Municipality::factory()->create();
    $user = User::factory()->create();

    $voter = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'document_number' => '1234567890',
        'first_name' => 'Juan',
        'last_name' => 'Pérez',
        'phone' => '300 123 4567',
        'municipality_id' => $municipality->id,
        'registered_by' => $user->id,
    ]);

    expect($voter)->toBeInstanceOf(Voter::class);
    expect($voter->document_number)->toBe('1234567890');
    expect($voter->first_name)->toBe('Juan');
    expect($voter->last_name)->toBe('Pérez');
    expect($voter->phone)->toBe('300 123 4567');

    assertDatabaseHas('voters', [
        'document_number' => '1234567890',
        'first_name' => 'Juan',
        'last_name' => 'Pérez',
    ]);
});

it('requires campaign_id, document_number, first_name, last_name, phone, municipality_id and registered_by', function () {
    expect(fn () => Voter::create([]))->toThrow(Exception::class);
});

it('has default status pending_review', function () {
    $voter = Voter::factory()->create();

    expect($voter->status)->toBe(VoterStatus::PENDING_REVIEW);
});

it('can have different statuses', function () {
    $pending = Voter::factory()->create(['status' => VoterStatus::PENDING_REVIEW]);
    $verifiedCensus = Voter::factory()->verifiedCensus()->create();
    $verifiedCall = Voter::factory()->verifiedCall()->create();
    $confirmed = Voter::factory()->confirmed()->create();
    $voted = Voter::factory()->voted()->create();
    $didNotVote = Voter::factory()->didNotVote()->create();
    $rejected = Voter::factory()->rejectedCensus()->create();

    expect($pending->status)->toBe(VoterStatus::PENDING_REVIEW);
    expect($verifiedCensus->status)->toBe(VoterStatus::VERIFIED_CENSUS);
    expect($verifiedCall->status)->toBe(VoterStatus::VERIFIED_CALL);
    expect($confirmed->status)->toBe(VoterStatus::CONFIRMED);
    expect($voted->status)->toBe(VoterStatus::VOTED);
    expect($didNotVote->status)->toBe(VoterStatus::DID_NOT_VOTE);
    expect($rejected->status)->toBe(VoterStatus::REJECTED_CENSUS);
});

it('casts status to VoterStatus enum', function () {
    $voter = Voter::factory()->create();

    expect($voter->status)->toBeInstanceOf(VoterStatus::class);
});

it('casts dates correctly', function () {
    $voter = Voter::factory()->create([
        'birth_date' => '1990-05-15',
        'census_validated_at' => '2025-01-15 10:00:00',
    ]);

    expect($voter->birth_date)->toBeInstanceOf(Carbon\Carbon::class);
    expect($voter->census_validated_at)->toBeInstanceOf(Carbon\Carbon::class);
});

it('document_number is unique per campaign', function () {
    $campaign = Campaign::factory()->create();

    Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'document_number' => '1234567890',
    ]);

    expect(fn () => Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'document_number' => '1234567890',
    ]))->toThrow(Exception::class);
});

it('document_number can be duplicated across different campaigns', function () {
    $campaign1 = Campaign::factory()->create();
    $campaign2 = Campaign::factory()->create();

    $voter1 = Voter::factory()->create([
        'campaign_id' => $campaign1->id,
        'document_number' => '1234567890',
    ]);

    $voter2 = Voter::factory()->create([
        'campaign_id' => $campaign2->id,
        'document_number' => '1234567890',
    ]);

    expect($voter1->document_number)->toBe($voter2->document_number);
    expect($voter1->campaign_id)->not->toBe($voter2->campaign_id);
});

it('has campaign relationship', function () {
    $voter = Voter::factory()->create();

    expect($voter->campaign())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

it('can retrieve campaign', function () {
    $campaign = Campaign::factory()->create(['name' => 'Campaña 2025']);
    $voter = Voter::factory()->create(['campaign_id' => $campaign->id]);

    $voter->load('campaign');

    expect($voter->campaign->id)->toBe($campaign->id);
    expect($voter->campaign->name)->toBe('Campaña 2025');
});

it('has municipality relationship', function () {
    $voter = Voter::factory()->create();

    expect($voter->municipality())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

it('can retrieve municipality', function () {
    $municipality = Municipality::factory()->create(['name' => 'Medellín']);
    $voter = Voter::factory()->create(['municipality_id' => $municipality->id]);

    $voter->load('municipality');

    expect($voter->municipality->id)->toBe($municipality->id);
    expect($voter->municipality->name)->toBe('Medellín');
});

it('has neighborhood relationship', function () {
    $voter = Voter::factory()->create();

    expect($voter->neighborhood())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

it('can retrieve neighborhood', function () {
    $neighborhood = Neighborhood::factory()->create(['name' => 'El Poblado']);
    $voter = Voter::factory()->create(['neighborhood_id' => $neighborhood->id]);

    $voter->load('neighborhood');

    expect($voter->neighborhood->id)->toBe($neighborhood->id);
    expect($voter->neighborhood->name)->toBe('El Poblado');
});

it('has registeredBy relationship', function () {
    $voter = Voter::factory()->create();

    expect($voter->registeredBy())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

it('can retrieve registeredBy user', function () {
    $user = User::factory()->create(['name' => 'Coordinator User']);
    $voter = Voter::factory()->create(['registered_by' => $user->id]);

    $voter->load('registeredBy');

    expect($voter->registeredBy->id)->toBe($user->id);
    expect($voter->registeredBy->name)->toBe('Coordinator User');
});

it('scope pendingReview returns only pending review voters', function () {
    Voter::factory()->create(['status' => VoterStatus::PENDING_REVIEW]);
    Voter::factory()->create(['status' => VoterStatus::PENDING_REVIEW]);
    Voter::factory()->verifiedCensus()->create();
    Voter::factory()->voted()->create();

    $pendingVoters = Voter::pendingReview()->get();

    expect($pendingVoters)->toHaveCount(2);
    expect($pendingVoters->every(fn ($v) => $v->status === VoterStatus::PENDING_REVIEW))->toBeTrue();
});

it('scope verifiedCensus returns only census verified voters', function () {
    Voter::factory()->create(['status' => VoterStatus::PENDING_REVIEW]);
    Voter::factory()->verifiedCensus()->create();
    Voter::factory()->verifiedCensus()->create();

    $verifiedVoters = Voter::verifiedCensus()->get();

    expect($verifiedVoters)->toHaveCount(2);
    expect($verifiedVoters->every(fn ($v) => $v->status === VoterStatus::VERIFIED_CENSUS))->toBeTrue();
});

it('scope confirmed returns only confirmed voters', function () {
    Voter::factory()->create(['status' => VoterStatus::PENDING_REVIEW]);
    Voter::factory()->confirmed()->create();
    Voter::factory()->confirmed()->create();

    $confirmedVoters = Voter::confirmed()->get();

    expect($confirmedVoters)->toHaveCount(2);
    expect($confirmedVoters->every(fn ($v) => $v->status === VoterStatus::CONFIRMED))->toBeTrue();
});

it('scope voted returns only voters who voted', function () {
    Voter::factory()->confirmed()->create();
    Voter::factory()->voted()->create();
    Voter::factory()->voted()->create();
    Voter::factory()->didNotVote()->create();

    $votedVoters = Voter::voted()->get();

    expect($votedVoters)->toHaveCount(2);
    expect($votedVoters->every(fn ($v) => $v->status === VoterStatus::VOTED))->toBeTrue();
});

it('scope didNotVote returns only voters who did not vote', function () {
    Voter::factory()->voted()->create();
    Voter::factory()->didNotVote()->create();
    Voter::factory()->didNotVote()->create();

    $didNotVoteVoters = Voter::didNotVote()->get();

    expect($didNotVoteVoters)->toHaveCount(2);
    expect($didNotVoteVoters->every(fn ($v) => $v->status === VoterStatus::DID_NOT_VOTE))->toBeTrue();
});

it('scope forCampaign returns only voters for specific campaign', function () {
    $campaign1 = Campaign::factory()->create();
    $campaign2 = Campaign::factory()->create();

    Voter::factory()->create(['campaign_id' => $campaign1->id]);
    Voter::factory()->create(['campaign_id' => $campaign1->id]);
    Voter::factory()->create(['campaign_id' => $campaign2->id]);

    $campaign1Voters = Voter::forCampaign($campaign1->id)->get();

    expect($campaign1Voters)->toHaveCount(2);
    expect($campaign1Voters->every(fn ($v) => $v->campaign_id === $campaign1->id))->toBeTrue();
});

it('getFullNameAttribute works correctly', function () {
    $voter = Voter::factory()->create([
        'first_name' => 'Juan',
        'last_name' => 'Pérez',
    ]);

    expect($voter->full_name)->toBe('Juan Pérez');
});

it('can update a voter', function () {
    $voter = Voter::factory()->create([
        'first_name' => 'Original Name',
        'phone' => '300 111 1111',
    ]);

    $voter->update([
        'first_name' => 'Updated Name',
        'phone' => '300 222 2222',
    ]);

    expect($voter->fresh()->first_name)->toBe('Updated Name');
    expect($voter->fresh()->phone)->toBe('300 222 2222');
});

it('can soft delete a voter', function () {
    $voter = Voter::factory()->create();
    $id = $voter->id;

    $voter->delete();

    expect(Voter::find($id))->toBeNull();
    expect(Voter::withTrashed()->find($id))->not->toBeNull();
    assertSoftDeleted('voters', ['id' => $id]);
});

it('can restore a soft deleted voter', function () {
    $voter = Voter::factory()->create();
    $voter->delete();

    $voter->restore();

    expect(Voter::find($voter->id))->not->toBeNull();
    expect($voter->deleted_at)->toBeNull();
});

it('deleting campaign cascades delete voters', function () {
    $campaign = Campaign::factory()->create();
    $voter = Voter::factory()->create(['campaign_id' => $campaign->id]);

    $campaign->forceDelete();

    expect(Voter::withTrashed()->find($voter->id))->toBeNull();
});

it('deleting municipality cascades delete voters', function () {
    $municipality = Municipality::factory()->create();
    $voter = Voter::factory()->create(['municipality_id' => $municipality->id]);

    $municipality->delete();

    expect(Voter::withTrashed()->find($voter->id))->toBeNull();
});

it('deleting neighborhood sets voter neighborhood_id to null', function () {
    $neighborhood = Neighborhood::factory()->create();
    $voter = Voter::factory()->create(['neighborhood_id' => $neighborhood->id]);

    $neighborhood->delete();

    expect($voter->fresh()->neighborhood_id)->toBeNull();
});

it('enum has correct labels in Spanish', function () {
    expect(VoterStatus::PENDING_REVIEW->getLabel())->toBe('Pendiente de Revisión');
    expect(VoterStatus::REJECTED_CENSUS->getLabel())->toBe('Rechazado en Censo');
    expect(VoterStatus::VERIFIED_CENSUS->getLabel())->toBe('Verificado en Censo');
    expect(VoterStatus::CORRECTION_REQUIRED->getLabel())->toBe('Requiere Corrección');
    expect(VoterStatus::VERIFIED_CALL->getLabel())->toBe('Verificado por Llamada');
    expect(VoterStatus::CONFIRMED->getLabel())->toBe('Confirmado');
    expect(VoterStatus::VOTED->getLabel())->toBe('Votó');
    expect(VoterStatus::DID_NOT_VOTE->getLabel())->toBe('No Votó');
});

it('enum has correct colors', function () {
    expect(VoterStatus::PENDING_REVIEW->getColor())->toBe('gray');
    expect(VoterStatus::REJECTED_CENSUS->getColor())->toBe('danger');
    expect(VoterStatus::VERIFIED_CENSUS->getColor())->toBe('info');
    expect(VoterStatus::CORRECTION_REQUIRED->getColor())->toBe('warning');
    expect(VoterStatus::VERIFIED_CALL->getColor())->toBe('success');
    expect(VoterStatus::CONFIRMED->getColor())->toBe('success');
    expect(VoterStatus::VOTED->getColor())->toBe('success');
    expect(VoterStatus::DID_NOT_VOTE->getColor())->toBe('danger');
});

it('enum has correct icons', function () {
    expect(VoterStatus::PENDING_REVIEW->getIcon())->toBe('heroicon-m-clock');
    expect(VoterStatus::REJECTED_CENSUS->getIcon())->toBe('heroicon-m-x-circle');
    expect(VoterStatus::VERIFIED_CENSUS->getIcon())->toBe('heroicon-m-check-badge');
    expect(VoterStatus::CORRECTION_REQUIRED->getIcon())->toBe('heroicon-m-exclamation-triangle');
    expect(VoterStatus::VERIFIED_CALL->getIcon())->toBe('heroicon-m-phone');
    expect(VoterStatus::CONFIRMED->getIcon())->toBe('heroicon-m-check-circle');
    expect(VoterStatus::VOTED->getIcon())->toBe('heroicon-m-hand-thumb-up');
    expect(VoterStatus::DID_NOT_VOTE->getIcon())->toBe('heroicon-m-hand-thumb-down');
});

it('enum has descriptions for each status', function () {
    expect(VoterStatus::PENDING_REVIEW->getDescription())->toContain('pendiente');
    expect(VoterStatus::REJECTED_CENSUS->getDescription())->toContain('rechazado');
    expect(VoterStatus::VERIFIED_CENSUS->getDescription())->toContain('verificado');
    expect(VoterStatus::CORRECTION_REQUIRED->getDescription())->toContain('corrección');
    expect(VoterStatus::VERIFIED_CALL->getDescription())->toContain('llamada');
    expect(VoterStatus::CONFIRMED->getDescription())->toContain('confirmó');
    expect(VoterStatus::VOTED->getDescription())->toContain('voto');
    expect(VoterStatus::DID_NOT_VOTE->getDescription())->toContain('no asistió');
});

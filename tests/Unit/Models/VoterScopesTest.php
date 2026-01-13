<?php

use App\Enums\VoterStatus;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

it('scope pendingReview uses scalar status value', function () {
    $query = Voter::query();
    $query->pendingReview();

    $where = collect($query->toBase()->wheres)->first(fn ($w) => ($w['column'] ?? null) === 'status');

    expect($where)->not->toBeNull();
    expect($where['value'])->toBe(VoterStatus::PENDING_REVIEW->value);
});

it('scope confirmed uses scalar status value', function () {
    $query = Voter::query();
    $query->confirmed();

    $where = collect($query->toBase()->wheres)->first(fn ($w) => ($w['column'] ?? null) === 'status');

    expect($where)->not->toBeNull();
    expect($where['value'])->toBe(VoterStatus::CONFIRMED->value);
});

it('scope voted uses scalar status value', function () {
    $query = Voter::query();
    $query->voted();

    $where = collect($query->toBase()->wheres)->first(fn ($w) => ($w['column'] ?? null) === 'status');

    expect($where)->not->toBeNull();
    expect($where['value'])->toBe(VoterStatus::VOTED->value);
});

it('scope didNotVote uses scalar status value', function () {
    $query = Voter::query();
    $query->didNotVote();

    $where = collect($query->toBase()->wheres)->first(fn ($w) => ($w['column'] ?? null) === 'status');

    expect($where)->not->toBeNull();
    expect($where['value'])->toBe(VoterStatus::DID_NOT_VOTE->value);
});

it('scope verifiedCensus uses scalar status value', function () {
    $query = Voter::query();
    $query->verifiedCensus();

    $where = collect($query->toBase()->wheres)->first(fn ($w) => ($w['column'] ?? null) === 'status');

    expect($where)->not->toBeNull();
    expect($where['value'])->toBe(VoterStatus::VERIFIED_CENSUS->value);
});

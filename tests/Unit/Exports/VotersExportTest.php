<?php

use App\Enums\VoterStatus;
use App\Exports\VotersExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

it('normalizes VoterStatus enums to scalar values in the constructor', function () {
    $export = new VotersExport(status: [VoterStatus::VOTED]);

    $builder = $export->query()->toBase();

    $whereIn = collect($builder->wheres)->first(fn ($w) => isset($w['type']) && strtolower($w['type']) === 'in' && ($w['column'] ?? null) === 'status');

    expect($whereIn)->not->toBeNull();
    expect($whereIn['values'])->toContain(VoterStatus::VOTED->value);
});

it('accepts string statuses without modification', function () {
    $export = new VotersExport(status: ['confirmed', 'voted']);

    $builder = $export->query()->toBase();

    $whereIn = collect($builder->wheres)->first(fn ($w) => isset($w['type']) && strtolower($w['type']) === 'in' && ($w['column'] ?? null) === 'status');

    expect($whereIn)->not->toBeNull();
    expect($whereIn['values'])->toContain('confirmed');
    expect($whereIn['values'])->toContain('voted');
});

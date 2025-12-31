<?php

declare(strict_types=1);

use App\Filament\Widgets\DiaDStatsOverview;
use App\Models\Campaign;
use App\Models\Voter;
use App\Enums\VoterStatus;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

it('returns stats array without error', function () {
    $campaign = Campaign::factory()->create(['status' => 'active']);

    Voter::factory()->for($campaign)->create(['status' => VoterStatus::CONFIRMED]);
    Voter::factory()->for($campaign)->create(['status' => VoterStatus::VOTED]);

    $widget = new DiaDStatsOverview();

    $method = new ReflectionMethod(DiaDStatsOverview::class, 'getStats');
    $method->setAccessible(true);

    $stats = $method->invoke($widget);

    expect(is_array($stats))->toBeTrue();
    expect(count($stats))->toBeGreaterThan(0);
});

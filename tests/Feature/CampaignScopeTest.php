<?php

use App\Enums\CampaignScope;
use App\Models\Campaign;

test('campaign can be created with scope', function () {
    $campaign = Campaign::factory()->create([
        'scope' => CampaignScope::Municipal,
    ]);

    expect($campaign->scope)->toBe(CampaignScope::Municipal);
});

test('campaign scope defaults to municipal', function () {
    $campaign = Campaign::factory()->create();

    expect($campaign->scope)->toBeInstanceOf(CampaignScope::class);
});

test('campaign can filter by scope using scopes', function () {
    Campaign::factory()->municipal()->create();
    Campaign::factory()->departamental()->create();
    Campaign::factory()->regional()->create();

    expect(Campaign::municipal()->count())->toBe(1)
        ->and(Campaign::departamental()->count())->toBe(1)
        ->and(Campaign::regional()->count())->toBe(1);
});

test('campaign scope label returns correct translation', function () {
    expect(CampaignScope::Municipal->label())->toBe('Municipal')
        ->and(CampaignScope::Departamental->label())->toBe('Departamental')
        ->and(CampaignScope::Regional->label())->toBe('Regional');
});

test('campaign scope options returns array', function () {
    $options = CampaignScope::options();

    expect($options)->toBeArray()
        ->and($options)->toHaveKeys(['municipal', 'departamental', 'regional']);
});

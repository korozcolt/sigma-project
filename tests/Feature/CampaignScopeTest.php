<?php

use App\Enums\CampaignScope;
use App\Enums\ElectionType;
use App\Models\Campaign;

test('campaign scope is derived from election type', function () {
    $campaign = Campaign::factory()->create([
        'election_type' => ElectionType::MAYOR,
    ]);

    expect($campaign->scope)->toBe(CampaignScope::Municipal);
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

test('election type maps to scope correctly', function () {
    expect(ElectionType::MAYOR->scope())->toBe(CampaignScope::Municipal)
        ->and(ElectionType::GOVERNOR->scope())->toBe(CampaignScope::Departamental)
        ->and(ElectionType::HOUSE->scope())->toBe(CampaignScope::Departamental)
        ->and(ElectionType::SENATE->scope())->toBe(CampaignScope::Nacional)
        ->and(ElectionType::PRESIDENT->scope())->toBe(CampaignScope::Nacional)
        ->and(ElectionType::OTHER->scope())->toBe(CampaignScope::Regional);
});

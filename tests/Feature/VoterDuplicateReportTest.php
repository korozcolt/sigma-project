<?php

declare(strict_types=1);

use App\Models\Campaign;
use App\Models\User;
use App\Models\Voter;
use App\Services\VoterDuplicateReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('it parses document numbers from csv', function () {
    Storage::fake('local');

    $csv = "cedula\n123\n 123 \nABC\n456-789\n\n";
    Storage::disk('local')->put('reports/cedulas.csv', $csv);

    $service = new VoterDuplicateReport();
    $documents = $service->parseDocumentNumbers('reports/cedulas.csv', 'local');

    expect($documents)->toEqualCanonicalizing(['123', '456789']);
});

test('it builds duplicate rows for active campaign', function () {
    $campaign = Campaign::factory()->create();
    $leader = User::factory()->create(['name' => 'Lider Test']);

    Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'document_number' => '123',
        'registered_by' => $leader->id,
    ]);

    Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'document_number' => '999',
    ]);

    $service = new VoterDuplicateReport();
    $rows = $service->buildRows(['123', '456789'], $campaign->id);

    expect($rows)->toHaveCount(1)
        ->and($rows[0][0])->toBe('123')
        ->and($rows[0][1])->toBe('Lider Test')
        ->and($rows[0][2])->toBe($campaign->name);
});

test('it includes missing rows when requested', function () {
    $campaign = Campaign::factory()->create();

    Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'document_number' => '123',
    ]);

    $service = new VoterDuplicateReport();
    $rows = $service->buildRows(['123', '456789'], $campaign->id, includeFound: true, includeMissing: true);

    expect($rows)->toHaveCount(2)
        ->and($rows[1][0])->toBe('456789')
        ->and($rows[1][1])->toBe('NO ENCONTRADO')
        ->and($rows[1][2])->toBe('NO ENCONTRADO');
});

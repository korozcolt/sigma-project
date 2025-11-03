<?php

use App\Models\Campaign;
use App\Models\CensusRecord;
use App\Services\CensusImporter;

use function Pest\Laravel\assertDatabaseHas;

it('can create a census record', function () {
    $campaign = Campaign::factory()->create();

    $record = CensusRecord::factory()->create([
        'campaign_id' => $campaign->id,
        'document_number' => '1234567890',
        'full_name' => 'Juan Pérez',
        'municipality_code' => '05001',
    ]);

    expect($record)->toBeInstanceOf(CensusRecord::class);
    expect($record->document_number)->toBe('1234567890');
    expect($record->full_name)->toBe('Juan Pérez');
    expect($record->municipality_code)->toBe('05001');

    assertDatabaseHas('census_records', [
        'document_number' => '1234567890',
        'full_name' => 'Juan Pérez',
        'municipality_code' => '05001',
    ]);
});

it('requires campaign_id, document_number, full_name and municipality_code', function () {
    expect(fn () => CensusRecord::create([]))->toThrow(Exception::class);
});

it('casts imported_at to datetime', function () {
    $record = CensusRecord::factory()->create([
        'imported_at' => '2025-01-15 10:00:00',
    ]);

    expect($record->imported_at)->toBeInstanceOf(Carbon\Carbon::class);
});

it('document_number is unique per campaign', function () {
    $campaign = Campaign::factory()->create();

    CensusRecord::factory()->create([
        'campaign_id' => $campaign->id,
        'document_number' => '1234567890',
    ]);

    expect(fn () => CensusRecord::factory()->create([
        'campaign_id' => $campaign->id,
        'document_number' => '1234567890',
    ]))->toThrow(Exception::class);
});

it('document_number can be duplicated across different campaigns', function () {
    $campaign1 = Campaign::factory()->create();
    $campaign2 = Campaign::factory()->create();

    $record1 = CensusRecord::factory()->create([
        'campaign_id' => $campaign1->id,
        'document_number' => '1234567890',
    ]);

    $record2 = CensusRecord::factory()->create([
        'campaign_id' => $campaign2->id,
        'document_number' => '1234567890',
    ]);

    expect($record1->document_number)->toBe($record2->document_number);
    expect($record1->campaign_id)->not->toBe($record2->campaign_id);
});

it('has campaign relationship', function () {
    $record = CensusRecord::factory()->create();

    expect($record->campaign())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

it('can retrieve campaign', function () {
    $campaign = Campaign::factory()->create(['name' => 'Campaña 2025']);
    $record = CensusRecord::factory()->create(['campaign_id' => $campaign->id]);

    $record->load('campaign');

    expect($record->campaign->id)->toBe($campaign->id);
    expect($record->campaign->name)->toBe('Campaña 2025');
});

it('scope forCampaign returns only records for specific campaign', function () {
    $campaign1 = Campaign::factory()->create();
    $campaign2 = Campaign::factory()->create();

    CensusRecord::factory()->create(['campaign_id' => $campaign1->id]);
    CensusRecord::factory()->create(['campaign_id' => $campaign1->id]);
    CensusRecord::factory()->create(['campaign_id' => $campaign2->id]);

    $campaign1Records = CensusRecord::forCampaign($campaign1->id)->get();

    expect($campaign1Records)->toHaveCount(2);
    expect($campaign1Records->every(fn ($r) => $r->campaign_id === $campaign1->id))->toBeTrue();
});

it('scope byDocument finds record by document number', function () {
    CensusRecord::factory()->create(['document_number' => '1234567890']);
    CensusRecord::factory()->create(['document_number' => '0987654321']);

    $record = CensusRecord::byDocument('1234567890')->first();

    expect($record)->not->toBeNull();
    expect($record->document_number)->toBe('1234567890');
});

it('scope byMunicipality filters by municipality code', function () {
    CensusRecord::factory()->create(['municipality_code' => '05001']);
    CensusRecord::factory()->create(['municipality_code' => '05001']);
    CensusRecord::factory()->create(['municipality_code' => '11001']);

    $records = CensusRecord::byMunicipality('05001')->get();

    expect($records)->toHaveCount(2);
    expect($records->every(fn ($r) => $r->municipality_code === '05001'))->toBeTrue();
});

it('can update a census record', function () {
    $record = CensusRecord::factory()->create([
        'full_name' => 'Original Name',
    ]);

    $record->update([
        'full_name' => 'Updated Name',
    ]);

    expect($record->fresh()->full_name)->toBe('Updated Name');
});

it('can delete a census record', function () {
    $record = CensusRecord::factory()->create();
    $id = $record->id;

    $record->delete();

    expect(CensusRecord::find($id))->toBeNull();
});

it('deleting campaign cascades delete census records', function () {
    $campaign = Campaign::factory()->create();
    $record = CensusRecord::factory()->create(['campaign_id' => $campaign->id]);

    $campaign->forceDelete();

    expect(CensusRecord::find($record->id))->toBeNull();
});

// CensusImporter Service Tests

it('can import census records from array', function () {
    $campaign = Campaign::factory()->create();
    $importer = new CensusImporter;

    $data = [
        [
            'document_number' => '1111111111',
            'full_name' => 'Person One',
            'municipality_code' => '05001',
            'polling_station' => 'Mesa 001',
            'table_number' => '01',
        ],
        [
            'document_number' => '2222222222',
            'full_name' => 'Person Two',
            'municipality_code' => '05001',
            'polling_station' => 'Mesa 002',
            'table_number' => '02',
        ],
    ];

    $result = $importer->import($campaign->id, $data);

    expect($result['imported'])->toBe(2);
    expect($result['failed'])->toBe(0);
    expect($result['errors'])->toBeEmpty();

    assertDatabaseHas('census_records', [
        'campaign_id' => $campaign->id,
        'document_number' => '1111111111',
        'full_name' => 'Person One',
    ]);

    assertDatabaseHas('census_records', [
        'campaign_id' => $campaign->id,
        'document_number' => '2222222222',
        'full_name' => 'Person Two',
    ]);
});

it('handles validation errors during import', function () {
    $campaign = Campaign::factory()->create();
    $importer = new CensusImporter;

    $data = [
        [
            'document_number' => '1111111111',
            'full_name' => 'Person One',
            'municipality_code' => '05001',
        ],
        [
            'document_number' => '', // Invalid - empty
            'full_name' => 'Person Two',
            'municipality_code' => '05001',
        ],
        [
            'document_number' => '3333333333',
            'full_name' => 'Person Three',
            'municipality_code' => '05001',
        ],
    ];

    $result = $importer->import($campaign->id, $data);

    expect($result['imported'])->toBe(2);
    expect($result['failed'])->toBe(1);
    expect($result['errors'])->toHaveCount(1);
});

it('can import census records in batches', function () {
    $campaign = Campaign::factory()->create();
    $importer = new CensusImporter;

    $data = [];
    for ($i = 1; $i <= 10; $i++) {
        $data[] = [
            'document_number' => str_pad((string) $i, 10, '0', STR_PAD_LEFT),
            'full_name' => "Person {$i}",
            'municipality_code' => '05001',
        ];
    }

    $result = $importer->importInBatches($campaign->id, $data, 3);

    expect($result['imported'])->toBe(10);
    expect($result['failed'])->toBe(0);
    expect(CensusRecord::where('campaign_id', $campaign->id)->count())->toBe(10);
});

it('can clear census for a campaign', function () {
    $campaign = Campaign::factory()->create();
    CensusRecord::factory()->count(5)->create(['campaign_id' => $campaign->id]);

    expect(CensusRecord::where('campaign_id', $campaign->id)->count())->toBe(5);

    $importer = new CensusImporter;
    $deleted = $importer->clearCensus($campaign->id);

    expect($deleted)->toBe(5);
    expect(CensusRecord::where('campaign_id', $campaign->id)->count())->toBe(0);
});

it('clearing census for one campaign does not affect others', function () {
    $campaign1 = Campaign::factory()->create();
    $campaign2 = Campaign::factory()->create();

    CensusRecord::factory()->count(3)->create(['campaign_id' => $campaign1->id]);
    CensusRecord::factory()->count(5)->create(['campaign_id' => $campaign2->id]);

    $importer = new CensusImporter;
    $importer->clearCensus($campaign1->id);

    expect(CensusRecord::where('campaign_id', $campaign1->id)->count())->toBe(0);
    expect(CensusRecord::where('campaign_id', $campaign2->id)->count())->toBe(5);
});

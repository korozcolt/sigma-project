<?php

use App\Filament\Schemas\MetadataTableColumns;
use App\Filament\Schemas\MetadataTableFilter;
use App\Models\MetadataKey;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('builds one toggleable sortable TextColumn per active metadata key', function () {
    $activeKey = MetadataKey::factory()->create(['is_active' => true, 'label' => 'Biaticos']);
    MetadataKey::factory()->create(['is_active' => false]);

    $columns = MetadataTableColumns::make();

    expect($columns)->toHaveCount(1);

    /** @var TextColumn $column */
    $column = $columns[0];

    expect($column)->toBeInstanceOf(TextColumn::class)
        ->and($column->getName())->toBe("metadata_{$activeKey->id}")
        ->and($column->getLabel())->toBe('Biaticos')
        ->and($column->isSortable())->toBeTrue()
        ->and($column->isToggledHiddenByDefault())->toBeTrue();
});

it('builds a metadata Filter named metadata', function () {
    $filter = MetadataTableFilter::make();

    expect($filter->getName())->toBe('metadata');
});

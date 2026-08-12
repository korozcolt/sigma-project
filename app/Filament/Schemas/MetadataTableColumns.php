<?php

namespace App\Filament\Schemas;

use App\Models\MetadataKey;
use App\Services\MetadataAssignmentService;
use Filament\Tables\Columns\TextColumn;

class MetadataTableColumns
{
    /**
     * @return array<int, TextColumn>
     */
    public static function make(): array
    {
        return app(MetadataAssignmentService::class)->activeKeys()
            ->map(fn (MetadataKey $key): TextColumn => TextColumn::make("metadata_{$key->id}")
                ->label($key->label)
                ->toggleable(isToggledHiddenByDefault: true)
                ->sortable())
            ->all();
    }
}

<?php

namespace App\Filament\Schemas;

use App\Services\MetadataAssignmentService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class MetadataTableFilter
{
    public static function make(): Filter
    {
        return Filter::make('metadata')
            ->label('Metadata')
            ->schema([
                Select::make('metadata_key_id')
                    ->label('Llave')
                    ->options(fn (): array => app(MetadataAssignmentService::class)->activeKeyOptions())
                    ->live()
                    ->afterStateUpdated(fn (callable $set) => $set('value', null)),

                TextInput::make('value')
                    ->label('Valor')
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => static::typeOf($get('metadata_key_id')) === 'text'),

                TextInput::make('value')
                    ->label('Valor')
                    ->numeric()
                    ->step(0.01)
                    ->visible(fn (Get $get): bool => static::typeOf($get('metadata_key_id')) === 'numeric'),

                DatePicker::make('value')
                    ->label('Valor')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->format('Y-m-d')
                    ->visible(fn (Get $get): bool => static::typeOf($get('metadata_key_id')) === 'date'),

                Select::make('value')
                    ->label('Valor')
                    ->options(fn (Get $get): array => static::optionsOf($get('metadata_key_id')))
                    ->visible(fn (Get $get): bool => static::typeOf($get('metadata_key_id')) === 'select'),
            ])
            ->query(function (Builder $query, array $data): Builder {
                if (blank($data['metadata_key_id'] ?? null) || blank($data['value'] ?? null)) {
                    return $query;
                }

                $key = app(MetadataAssignmentService::class)->findActiveKey($data['metadata_key_id']);

                if (! $key) {
                    return $query;
                }

                return app(MetadataAssignmentService::class)->applyMetadataFilter($query, $key, (string) $data['value']);
            });
    }

    protected static function typeOf(int|string|null $keyId): ?string
    {
        return app(MetadataAssignmentService::class)->findActiveKey($keyId)?->type;
    }

    /** @return array<string, string> */
    protected static function optionsOf(int|string|null $keyId): array
    {
        $options = app(MetadataAssignmentService::class)->findActiveKey($keyId)?->options ?? [];

        return array_combine($options, $options);
    }
}

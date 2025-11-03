<?php

namespace App\Filament\Resources\Neighborhoods\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class NeighborhoodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('municipality_id')
                    ->label('Municipio')
                    ->relationship('municipality', 'name')
                    ->searchable()
                    ->required(),
                TextInput::make('name')
                    ->label('Nombre del Barrio')
                    ->required()
                    ->maxLength(255),
                Toggle::make('is_global')
                    ->label('Barrio Global')
                    ->helperText('Los barrios globales están disponibles para todas las campañas')
                    ->default(true)
                    ->reactive(),
                Select::make('campaign_id')
                    ->label('Campaña')
                    ->helperText('Solo necesario si el barrio es específico de una campaña')
                    ->relationship('campaign', 'name')
                    ->searchable()
                    ->visible(fn (callable $get) => ! $get('is_global')),
            ]);
    }
}

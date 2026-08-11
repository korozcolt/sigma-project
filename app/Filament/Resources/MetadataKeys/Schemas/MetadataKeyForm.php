<?php

namespace App\Filament\Resources\MetadataKeys\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class MetadataKeyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')
                    ->label('Llave')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->helperText('Identificador técnico en minúsculas, sin espacios (ej. biaticos).'),

                TextInput::make('label')
                    ->label('Nombre visible')
                    ->required()
                    ->maxLength(255),

                Select::make('type')
                    ->label('Tipo')
                    ->options([
                        'numeric' => 'Numérico',
                        'text' => 'Texto',
                        'date' => 'Fecha',
                        'select' => 'Selección',
                    ])
                    ->required()
                    ->live(),

                Repeater::make('options')
                    ->label('Opciones de selección')
                    ->simple(
                        TextInput::make('option')
                            ->label('Opción')
                            ->required()
                            ->maxLength(255)
                    )
                    ->visible(fn (Get $get): bool => $get('type') === 'select')
                    ->minItems(1)
                    ->defaultItems(2)
                    ->addActionLabel('+ Agregar opción')
                    ->reorderable()
                    ->columnSpanFull(),

                Toggle::make('is_active')
                    ->label('Activa')
                    ->default(true)
                    ->helperText('Desactivar oculta la llave de los formularios de asignación sin borrar el historial.'),
            ]);
    }
}

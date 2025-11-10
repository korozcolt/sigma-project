<?php

namespace App\Filament\Resources\Surveys\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SurveyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información de la Encuesta')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('campaign_id')
                                    ->label('Campaña')
                                    ->relationship('campaign', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Toggle::make('is_active')
                                    ->label('Activa')
                                    ->default(true)
                                    ->helperText('Solo las encuestas activas pueden ser utilizadas en llamadas'),
                            ]),

                        TextInput::make('title')
                            ->label('Título')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Textarea::make('description')
                            ->label('Descripción')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ]),

                Section::make('Versionamiento')
                    ->description('Gestión de versiones de la encuesta')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('version')
                                    ->label('Versión')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->disabled(fn ($record) => $record !== null)
                                    ->helperText('La versión se genera automáticamente'),

                                Select::make('parent_survey_id')
                                    ->label('Encuesta Padre')
                                    ->relationship('parentSurvey', 'title')
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Seleccione si esta es una nueva versión de una encuesta existente'),
                            ]),
                    ])
                    ->collapsed(),
            ]);
    }
}

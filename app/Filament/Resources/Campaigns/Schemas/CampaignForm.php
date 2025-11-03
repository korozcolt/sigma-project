<?php

namespace App\Filament\Resources\Campaigns\Schemas;

use App\Enums\CampaignStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CampaignForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información General')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre de la Campaña')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label('Descripción')
                            ->rows(3)
                            ->columnSpanFull(),
                        TextInput::make('candidate_name')
                            ->label('Nombre del Candidato')
                            ->required()
                            ->maxLength(255),
                        Select::make('status')
                            ->label('Estado')
                            ->options(CampaignStatus::class)
                            ->default(CampaignStatus::DRAFT)
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Fechas')
                    ->schema([
                        DatePicker::make('start_date')
                            ->label('Fecha de Inicio')
                            ->required()
                            ->default(now()),
                        DatePicker::make('end_date')
                            ->label('Fecha de Fin')
                            ->after('start_date'),
                        DatePicker::make('election_date')
                            ->label('Fecha de Elección')
                            ->required()
                            ->after('start_date'),
                    ])
                    ->columns(3),

                Section::make('Configuración')
                    ->schema([
                        KeyValue::make('settings')
                            ->label('Configuraciones Personalizadas')
                            ->keyLabel('Clave')
                            ->valueLabel('Valor')
                            ->addActionLabel('Agregar configuración')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}

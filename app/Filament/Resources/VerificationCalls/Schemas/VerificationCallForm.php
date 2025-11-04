<?php

namespace App\Filament\Resources\VerificationCalls\Schemas;

use App\Enums\CallResult;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VerificationCallForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información de la Llamada')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('voter_id')
                                    ->label('Votante')
                                    ->relationship('voter', 'full_name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Select::make('caller_id')
                                    ->label('Agente')
                                    ->relationship('caller', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Select::make('assignment_id')
                                    ->label('Asignación')
                                    ->relationship('assignment', 'id')
                                    ->searchable()
                                    ->preload(),

                                TextInput::make('attempt_number')
                                    ->label('Número de Intento')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->required(),
                            ]),

                        Grid::make(3)
                            ->schema([
                                DateTimePicker::make('call_date')
                                    ->label('Fecha y Hora')
                                    ->default(now())
                                    ->required(),

                                TextInput::make('call_duration')
                                    ->label('Duración (segundos)')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->suffix('seg')
                                    ->required(),

                                Select::make('call_result')
                                    ->label('Resultado')
                                    ->options(CallResult::class)
                                    ->required(),
                            ]),

                        Textarea::make('notes')
                            ->label('Notas y Observaciones')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('Seguimiento')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('survey_id')
                                    ->label('Encuesta Aplicada')
                                    ->relationship('survey', 'title')
                                    ->searchable()
                                    ->preload(),

                                Toggle::make('survey_completed')
                                    ->label('Encuesta Completada')
                                    ->default(false),

                                DateTimePicker::make('next_attempt_at')
                                    ->label('Próximo Intento Programado')
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}

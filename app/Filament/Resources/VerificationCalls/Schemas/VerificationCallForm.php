<?php

namespace App\Filament\Resources\VerificationCalls\Schemas;

use App\Enums\CallResult;
use App\Models\Voter;
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
                                Select::make('caller_id')
                                    ->label('Líder')
                                    ->relationship('caller', 'name')
                                    ->searchable(['name', 'email', 'document_number'])
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn (callable $set) => $set('voter_id', null))
                                    ->helperText('Seleccione primero el líder para filtrar sus votantes'),

                                Select::make('voter_id')
                                    ->label('Votante')
                                    ->searchable()
                                    ->disabled(fn (callable $get) => ! $get('caller_id'))
                                    ->options(function (callable $get) {
                                        $callerId = $get('caller_id');

                                        if (! $callerId) {
                                            return [];
                                        }

                                        return Voter::query()
                                            ->where('registered_by', $callerId)
                                            ->orderBy('first_name')
                                            ->orderBy('last_name')
                                            ->get()
                                            ->mapWithKeys(fn ($voter) => [
                                                $voter->id => sprintf('%s - %s', $voter->full_name, $voter->document_number),
                                            ]);
                                    })
                                    ->getSearchResultsUsing(function (string $search, callable $get) {
                                        $callerId = $get('caller_id');

                                        if (! $callerId) {
                                            return [];
                                        }

                                        return Voter::query()
                                            ->where('registered_by', $callerId)
                                            ->where(function ($q) use ($search) {
                                                $q->whereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ["%{$search}%"])
                                                    ->orWhere('first_name', 'like', "%{$search}%")
                                                    ->orWhere('last_name', 'like', "%{$search}%")
                                                    ->orWhere('document_number', 'like', "%{$search}%");
                                            })
                                            ->limit(50)
                                            ->get()
                                            ->mapWithKeys(fn ($voter) => [
                                                $voter->id => sprintf('%s - %s', $voter->full_name, $voter->document_number),
                                            ]);
                                    })
                                    ->getOptionLabelUsing(fn ($value): ?string => (
                                        ($v = Voter::find($value)) ? sprintf('%s - %s', $v->full_name, $v->document_number) : null
                                    ))
                                    ->helperText(fn (callable $get) => ! $get('caller_id')
                                        ? 'Primero seleccione un líder'
                                        : 'Seleccione o busque un votante del líder')
                                    ->required(),

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

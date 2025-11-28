<?php

declare(strict_types=1);

namespace App\Filament\Resources\ElectionEvents\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ElectionEventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información General')
                    ->schema([
                        Select::make('campaign_id')
                            ->label('Campaña')
                            ->relationship('campaign', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        TextInput::make('name')
                            ->label('Nombre del Evento')
                            ->placeholder('Ej: Simulacro #1, Día D Real')
                            ->required()
                            ->maxLength(255),

                        Select::make('type')
                            ->label('Tipo de Evento')
                            ->options([
                                'simulation' => 'Simulacro',
                                'real' => 'Día D Real',
                            ])
                            ->default('simulation')
                            ->required()
                            ->live()
                            ->helperText('Los simulacros permiten probar el sistema, el Día D Real es el evento oficial.'),

                        TextInput::make('simulation_number')
                            ->label('Número de Simulacro')
                            ->numeric()
                            ->minValue(1)
                            ->visible(fn (Get $get): bool => $get('type') === 'simulation')
                            ->helperText('Número secuencial del simulacro (opcional)'),
                    ])
                    ->columns(2),

                Section::make('Fecha y Horario')
                    ->schema([
                        DatePicker::make('date')
                            ->label('Fecha del Evento')
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->minDate(now()->subDays(7))
                            ->helperText('Solo se pueden activar eventos para la fecha de hoy.'),

                        TimePicker::make('start_time')
                            ->label('Hora de Inicio')
                            ->seconds(false)
                            ->helperText('Opcional: restringir acceso desde esta hora.'),

                        TimePicker::make('end_time')
                            ->label('Hora de Fin')
                            ->seconds(false)
                            ->helperText('Opcional: restringir acceso hasta esta hora.'),
                    ])
                    ->columns(3),

                Section::make('Estado y Configuración')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Evento Activo')
                            ->helperText('Solo un evento puede estar activo a la vez por campaña.')
                            ->default(false),

                        Textarea::make('notes')
                            ->label('Notas')
                            ->rows(3)
                            ->columnSpanFull()
                            ->placeholder('Notas adicionales sobre este evento electoral...'),
                    ])
                    ->columns(1),
            ]);
    }
}

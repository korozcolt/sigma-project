<?php

declare(strict_types=1);

namespace App\Filament\Resources\Messages\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MessageTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Información de la Plantilla')
                ->schema([
                    TextInput::make('name')
                        ->label('Nombre de la Plantilla')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),

                    Select::make('campaign_id')
                        ->label('Campaña')
                        ->relationship('campaign', 'name')
                        ->required()
                        ->searchable()
                        ->preload(),

                    Select::make('type')
                        ->label('Tipo')
                        ->options([
                            'birthday' => 'Cumpleaños',
                            'reminder' => 'Recordatorio',
                            'custom' => 'Personalizado',
                            'campaign' => 'Campaña',
                        ])
                        ->required(),

                    Select::make('channel')
                        ->label('Canal')
                        ->options([
                            'whatsapp' => 'WhatsApp',
                            'sms' => 'SMS',
                            'email' => 'Email',
                        ])
                        ->required()
                        ->live(),

                    Toggle::make('is_active')
                        ->label('Activa')
                        ->default(true)
                        ->inline(false),
                ])->columns(2),

            Section::make('Contenido del Mensaje')
                ->schema([
                    TextInput::make('subject')
                        ->label('Asunto')
                        ->maxLength(255)
                        ->visible(fn ($get) => $get('channel') === 'email')
                        ->helperText('Variables: {{nombre}}, {{candidato}}'),

                    Textarea::make('content')
                        ->label('Contenido del Mensaje')
                        ->required()
                        ->rows(6)
                        ->helperText('Variables disponibles: {{nombre}}, {{edad}}, {{candidato}}, {{fecha}}, {{barrio}}, {{municipio}}'),

                    Placeholder::make('preview_help')
                        ->label('Vista Previa')
                        ->content('Las variables se reemplazarán automáticamente al enviar el mensaje.'),
                ]),

            Section::make('Control Anti-Spam')
                ->schema([
                    TextInput::make('max_per_voter_per_day')
                        ->label('Máximo por Votante al Día')
                        ->numeric()
                        ->default(3)
                        ->minValue(1)
                        ->maxValue(50)
                        ->helperText('Límite de mensajes que un votante puede recibir en un día'),

                    TextInput::make('max_per_campaign_per_hour')
                        ->label('Máximo por Campaña por Hora')
                        ->numeric()
                        ->default(100)
                        ->minValue(1)
                        ->maxValue(1000)
                        ->helperText('Límite de mensajes que la campaña puede enviar en una hora'),
                ])->columns(2),

            Section::make('Horarios Permitidos')
                ->schema([
                    TimePicker::make('allowed_start_time')
                        ->label('Hora de Inicio')
                        ->default('08:00')
                        ->seconds(false),

                    TimePicker::make('allowed_end_time')
                        ->label('Hora de Fin')
                        ->default('20:00')
                        ->seconds(false),

                    CheckboxList::make('allowed_days')
                        ->label('Días Permitidos')
                        ->options([
                            'monday' => 'Lunes',
                            'tuesday' => 'Martes',
                            'wednesday' => 'Miércoles',
                            'thursday' => 'Jueves',
                            'friday' => 'Viernes',
                            'saturday' => 'Sábado',
                            'sunday' => 'Domingo',
                        ])
                        ->default(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
                        ->columns(4)
                        ->gridDirection('row'),
                ])->columns(2),
        ]);
    }
}

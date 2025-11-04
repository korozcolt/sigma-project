<?php

declare(strict_types=1);

namespace App\Filament\Resources\Messages\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class MessageTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Section::make('Información de la Plantilla')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nombre de la Plantilla')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),

                    Forms\Components\Select::make('campaign_id')
                        ->label('Campaña')
                        ->relationship('campaign', 'name')
                        ->required()
                        ->searchable()
                        ->preload(),

                    Forms\Components\Select::make('type')
                        ->label('Tipo')
                        ->options([
                            'birthday' => 'Cumpleaños',
                            'reminder' => 'Recordatorio',
                            'custom' => 'Personalizado',
                            'campaign' => 'Campaña',
                        ])
                        ->required(),

                    Forms\Components\Select::make('channel')
                        ->label('Canal')
                        ->options([
                            'whatsapp' => 'WhatsApp',
                            'sms' => 'SMS',
                            'email' => 'Email',
                        ])
                        ->required()
                        ->reactive(),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Activa')
                        ->default(true)
                        ->inline(false),
                ])->columns(2),

            Forms\Components\Section::make('Contenido del Mensaje')
                ->schema([
                    Forms\Components\TextInput::make('subject')
                        ->label('Asunto')
                        ->maxLength(255)
                        ->visible(fn (Forms\Get $get) => $get('channel') === 'email')
                        ->helperText('Variables: {{nombre}}, {{candidato}}'),

                    Forms\Components\Textarea::make('content')
                        ->label('Contenido del Mensaje')
                        ->required()
                        ->rows(6)
                        ->helperText('Variables disponibles: {{nombre}}, {{edad}}, {{candidato}}, {{fecha}}, {{barrio}}, {{municipio}}'),

                    Forms\Components\Placeholder::make('preview_help')
                        ->label('Vista Previa')
                        ->content('Las variables se reemplazarán automáticamente al enviar el mensaje.'),
                ]),

            Forms\Components\Section::make('Control Anti-Spam')
                ->schema([
                    Forms\Components\TextInput::make('max_per_voter_per_day')
                        ->label('Máximo por Votante al Día')
                        ->numeric()
                        ->default(3)
                        ->minValue(1)
                        ->maxValue(50)
                        ->helperText('Límite de mensajes que un votante puede recibir en un día'),

                    Forms\Components\TextInput::make('max_per_campaign_per_hour')
                        ->label('Máximo por Campaña por Hora')
                        ->numeric()
                        ->default(100)
                        ->minValue(1)
                        ->maxValue(1000)
                        ->helperText('Límite de mensajes que la campaña puede enviar en una hora'),
                ])->columns(2),

            Forms\Components\Section::make('Horarios Permitidos')
                ->schema([
                    Forms\Components\TimePicker::make('allowed_start_time')
                        ->label('Hora de Inicio')
                        ->default('08:00')
                        ->seconds(false),

                    Forms\Components\TimePicker::make('allowed_end_time')
                        ->label('Hora de Fin')
                        ->default('20:00')
                        ->seconds(false),

                    Forms\Components\CheckboxList::make('allowed_days')
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

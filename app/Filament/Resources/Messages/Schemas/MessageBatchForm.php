<?php

declare(strict_types=1);

namespace App\Filament\Resources\Messages\Schemas;

use App\Models\MessageTemplate;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MessageBatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Información del Envío')
                ->schema([
                    TextInput::make('name')
                        ->label('Nombre del Envío')
                        ->required()
                        ->maxLength(255),

                    Select::make('campaign_id')
                        ->label('Campaña')
                        ->relationship('campaign', 'name')
                        ->required()
                        ->searchable()
                        ->preload(),

                    Select::make('template_id')
                        ->label('Plantilla')
                        ->relationship('template', 'name')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set) {
                            if ($state) {
                                $template = MessageTemplate::find($state);
                                if ($template) {
                                    $set('type', $template->type);
                                    $set('channel', $template->channel);
                                }
                            }
                        }),

                    Select::make('type')
                        ->label('Tipo')
                        ->options([
                            'birthday' => 'Cumpleaños',
                            'reminder' => 'Recordatorio',
                            'custom' => 'Personalizado',
                            'campaign' => 'Campaña',
                        ])
                        ->required()
                        ->disabled(),

                    Select::make('channel')
                        ->label('Canal')
                        ->options([
                            'whatsapp' => 'WhatsApp',
                            'sms' => 'SMS',
                            'email' => 'Email',
                        ])
                        ->required()
                        ->disabled(),

                    Select::make('status')
                        ->label('Estado')
                        ->options([
                            'pending' => 'Pendiente',
                            'processing' => 'Procesando',
                            'completed' => 'Completado',
                            'failed' => 'Fallido',
                        ])
                        ->default('pending')
                        ->required()
                        ->disabled(fn ($record) => $record !== null),
                ])->columns(2),

            Section::make('Destinatarios')
                ->schema([
                    TextInput::make('total_recipients')
                        ->label('Total de Destinatarios')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->helperText('Número total de votantes que recibirán el mensaje'),

                    KeyValue::make('filters')
                        ->label('Filtros Aplicados')
                        ->keyLabel('Campo')
                        ->valueLabel('Valor')
                        ->helperText('Filtros usados para seleccionar destinatarios (ej: municipality_id, neighborhood_id, etc.)'),
                ])->columns(2),

            Section::make('Programación')
                ->schema([
                    DateTimePicker::make('scheduled_for')
                        ->label('Programar Para')
                        ->seconds(false)
                        ->helperText('Dejar vacío para envío inmediato'),
                ]),

            Section::make('Estadísticas')
                ->schema([
                    TextInput::make('sent_count')
                        ->label('Enviados')
                        ->numeric()
                        ->default(0)
                        ->disabled(),

                    TextInput::make('failed_count')
                        ->label('Fallidos')
                        ->numeric()
                        ->default(0)
                        ->disabled(),

                    TextInput::make('delivered_count')
                        ->label('Entregados')
                        ->numeric()
                        ->default(0)
                        ->disabled(),

                    DateTimePicker::make('started_at')
                        ->label('Iniciado')
                        ->disabled(),

                    DateTimePicker::make('completed_at')
                        ->label('Completado')
                        ->disabled(),
                ])
                ->columns(3)
                ->visible(fn ($record) => $record !== null),

            Section::make('Metadatos (Opcional)')
                ->schema([
                    KeyValue::make('metadata')
                        ->label('Metadata')
                        ->keyLabel('Clave')
                        ->valueLabel('Valor'),
                ])
                ->collapsed()
                ->collapsible(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Resources\Messages\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class MessageBatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Section::make('Información del Envío')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nombre del Envío')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\Select::make('campaign_id')
                        ->label('Campaña')
                        ->relationship('campaign', 'name')
                        ->required()
                        ->searchable()
                        ->preload(),

                    Forms\Components\Select::make('template_id')
                        ->label('Plantilla')
                        ->relationship('template', 'name')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set) {
                            if ($state) {
                                $template = \App\Models\MessageTemplate::find($state);
                                if ($template) {
                                    $set('type', $template->type);
                                    $set('channel', $template->channel);
                                }
                            }
                        }),

                    Forms\Components\Select::make('type')
                        ->label('Tipo')
                        ->options([
                            'birthday' => 'Cumpleaños',
                            'reminder' => 'Recordatorio',
                            'custom' => 'Personalizado',
                            'campaign' => 'Campaña',
                        ])
                        ->required()
                        ->disabled(),

                    Forms\Components\Select::make('channel')
                        ->label('Canal')
                        ->options([
                            'whatsapp' => 'WhatsApp',
                            'sms' => 'SMS',
                            'email' => 'Email',
                        ])
                        ->required()
                        ->disabled(),

                    Forms\Components\Select::make('status')
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

            Forms\Components\Section::make('Destinatarios')
                ->schema([
                    Forms\Components\TextInput::make('total_recipients')
                        ->label('Total de Destinatarios')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->helperText('Número total de votantes que recibirán el mensaje'),

                    Forms\Components\KeyValue::make('filters')
                        ->label('Filtros Aplicados')
                        ->keyLabel('Campo')
                        ->valueLabel('Valor')
                        ->helperText('Filtros usados para seleccionar destinatarios (ej: municipality_id, neighborhood_id, etc.)'),
                ])->columns(2),

            Forms\Components\Section::make('Programación')
                ->schema([
                    Forms\Components\DateTimePicker::make('scheduled_for')
                        ->label('Programar Para')
                        ->seconds(false)
                        ->helperText('Dejar vacío para envío inmediato'),
                ]),

            Forms\Components\Section::make('Estadísticas')
                ->schema([
                    Forms\Components\TextInput::make('sent_count')
                        ->label('Enviados')
                        ->numeric()
                        ->default(0)
                        ->disabled(),

                    Forms\Components\TextInput::make('failed_count')
                        ->label('Fallidos')
                        ->numeric()
                        ->default(0)
                        ->disabled(),

                    Forms\Components\TextInput::make('delivered_count')
                        ->label('Entregados')
                        ->numeric()
                        ->default(0)
                        ->disabled(),

                    Forms\Components\DateTimePicker::make('started_at')
                        ->label('Iniciado')
                        ->disabled(),

                    Forms\Components\DateTimePicker::make('completed_at')
                        ->label('Completado')
                        ->disabled(),
                ])
                ->columns(3)
                ->visible(fn ($record) => $record !== null),

            Forms\Components\Section::make('Metadatos (Opcional)')
                ->schema([
                    Forms\Components\KeyValue::make('metadata')
                        ->label('Metadata')
                        ->keyLabel('Clave')
                        ->valueLabel('Valor'),
                ])
                ->collapsed()
                ->collapsible(),
        ]);
    }
}

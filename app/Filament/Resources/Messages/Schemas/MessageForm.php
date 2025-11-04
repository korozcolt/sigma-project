<?php

declare(strict_types=1);

namespace App\Filament\Resources\Messages\Schemas;

use App\Models\MessageTemplate;
use App\Models\Voter;
use Filament\Forms;
use Filament\Schemas\Schema;

class MessageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Section::make('Información del Mensaje')
                ->schema([
                    Forms\Components\Select::make('campaign_id')
                        ->label('Campaña')
                        ->relationship('campaign', 'name')
                        ->required()
                        ->searchable()
                        ->preload(),

                    Forms\Components\Select::make('voter_id')
                        ->label('Votante')
                        ->relationship('voter', 'full_name')
                        ->required()
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search) => Voter::where('full_name', 'like', "%{$search}%")
                            ->orWhere('document_number', 'like', "%{$search}%")
                            ->limit(50)
                            ->pluck('full_name', 'id'))
                        ->getOptionLabelUsing(fn ($value): ?string => Voter::find($value)?->full_name),

                    Forms\Components\Select::make('template_id')
                        ->label('Plantilla')
                        ->relationship('template', 'name')
                        ->searchable()
                        ->preload()
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set) {
                            if ($state) {
                                $template = MessageTemplate::find($state);
                                if ($template) {
                                    $set('type', $template->type);
                                    $set('channel', $template->channel);
                                    $set('subject', $template->subject);
                                    $set('content', $template->content);
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
                        ->required(),

                    Forms\Components\Select::make('channel')
                        ->label('Canal')
                        ->options([
                            'whatsapp' => 'WhatsApp',
                            'sms' => 'SMS',
                            'email' => 'Email',
                        ])
                        ->required(),

                    Forms\Components\Select::make('status')
                        ->label('Estado')
                        ->options([
                            'pending' => 'Pendiente',
                            'scheduled' => 'Programado',
                            'sent' => 'Enviado',
                            'failed' => 'Fallido',
                            'delivered' => 'Entregado',
                            'read' => 'Leído',
                        ])
                        ->default('pending')
                        ->required(),
                ])->columns(2),

            Forms\Components\Section::make('Contenido del Mensaje')
                ->schema([
                    Forms\Components\TextInput::make('subject')
                        ->label('Asunto')
                        ->maxLength(255)
                        ->visible(fn (Forms\Get $get) => $get('channel') === 'email'),

                    Forms\Components\Textarea::make('content')
                        ->label('Contenido')
                        ->required()
                        ->rows(5)
                        ->helperText('Variables disponibles: {{nombre}}, {{fecha}}, {{candidato}}, etc.'),
                ]),

            Forms\Components\Section::make('Programación')
                ->schema([
                    Forms\Components\DateTimePicker::make('scheduled_for')
                        ->label('Programar para')
                        ->seconds(false)
                        ->visible(fn (Forms\Get $get) => $get('status') === 'scheduled'),
                ])->collapsed(),

            Forms\Components\Section::make('Metadatos (Opcional)')
                ->schema([
                    Forms\Components\TextInput::make('external_id')
                        ->label('ID Externo')
                        ->maxLength(255)
                        ->disabled(),

                    Forms\Components\Textarea::make('error_message')
                        ->label('Mensaje de Error')
                        ->rows(2)
                        ->disabled(),

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

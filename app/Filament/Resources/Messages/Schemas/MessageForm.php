<?php

declare(strict_types=1);

namespace App\Filament\Resources\Messages\Schemas;

use App\Models\MessageTemplate;
use App\Models\Voter;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MessageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Información del Mensaje')
                ->schema([
                    Select::make('campaign_id')
                        ->label('Campaña')
                        ->relationship('campaign', 'name')
                        ->required()
                        ->searchable()
                        ->preload(),

                    Select::make('voter_id')
                        ->label('Votante')
                        // Usamos una columna real para evitar errores SQL y calculamos la etiqueta
                        ->relationship('voter', 'first_name')
                        ->required()
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search) => Voter::query()
                            ->where(function ($q) use ($search) {
                                $q->whereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ["%{$search}%"])
                                    ->orWhere('first_name', 'like', "%{$search}%")
                                    ->orWhere('last_name', 'like', "%{$search}%")
                                    ->orWhere('document_number', 'like', "%{$search}%");
                            })
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn (Voter $voter) => [
                                $voter->id => sprintf('%s - %s', $voter->full_name, $voter->document_number),
                            ]))
                        ->getOptionLabelUsing(fn ($value): ?string => (
                            ($v = Voter::find($value)) ? sprintf('%s - %s', $v->full_name, $v->document_number) : null
                        )),

                    Select::make('template_id')
                        ->label('Plantilla')
                        ->relationship('template', 'name')
                        ->searchable()
                        ->preload()
                        ->live()
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
                        ->required(),

                    Select::make('status')
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

            Section::make('Contenido del Mensaje')
                ->schema([
                    TextInput::make('subject')
                        ->label('Asunto')
                        ->maxLength(255)
                        ->visible(fn ($get) => $get('channel') === 'email'),

                    Textarea::make('content')
                        ->label('Contenido')
                        ->required()
                        ->rows(5)
                        ->helperText('Variables disponibles: {{nombre}}, {{fecha}}, {{candidato}}, etc.'),
                ]),

            Section::make('Programación')
                ->schema([
                    DateTimePicker::make('scheduled_for')
                        ->label('Programar para')
                        ->seconds(false)
                        ->visible(fn ($get) => $get('status') === 'scheduled'),
                ])->collapsed(),

            Section::make('Metadatos (Opcional)')
                ->schema([
                    TextInput::make('external_id')
                        ->label('ID Externo')
                        ->maxLength(255)
                        ->disabled(),

                    Textarea::make('error_message')
                        ->label('Mensaje de Error')
                        ->rows(2)
                        ->disabled(),

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

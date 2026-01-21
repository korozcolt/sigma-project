<?php

namespace App\Filament\Resources\Voters\Schemas;

use App\Enums\VoterStatus;
use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class VoterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Asignación')
                    ->schema([
                        Select::make('coordinator_user_id')
                            ->label('Coordinador')
                            ->relationship(
                                name: 'registeredBy.coordinator',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query) => $query->role(UserRole::COORDINATOR->value)->orderBy('name'),
                            )
                            ->dehydrated(false)
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($state, callable $set) => $set('registered_by', null))
                            ->default(fn ($record) => $record?->registeredBy?->coordinator_user_id),

                        Select::make('registered_by')
                            ->label('Líder')
                            ->relationship(
                                name: 'registeredBy',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query, Get $get) => $query
                                    ->role(UserRole::LEADER->value)
                                    ->when(
                                        $get('coordinator_user_id'),
                                        fn (Builder $q, $coordinatorId) => $q->where('coordinator_user_id', $coordinatorId),
                                    )
                                    ->orderBy('name'),
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn (Get $get): bool => ! $get('coordinator_user_id'))
                            ->helperText('El votante siempre debe pertenecer a un líder.'),
                    ])
                    ->columns(2),

                Section::make('Información Personal')
                    ->schema([
                        TextInput::make('first_name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255)
                            ->autocomplete('given-name'),

                        TextInput::make('last_name')
                            ->label('Apellido')
                            ->required()
                            ->maxLength(255)
                            ->autocomplete('family-name'),

                        TextInput::make('document_number')
                            ->label('Número de Documento')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Debe ser único en todo el sistema')
                            ->rule('unique:voters,document_number,NULL,id,deleted_at,NULL'),

                        DatePicker::make('birth_date')
                            ->label('Fecha de Nacimiento')
                            ->maxDate(now())
                            ->displayFormat('d/m/Y')
                            ->native(false),
                    ])
                    ->columns(2),

                Section::make('Información de Contacto')
                    ->schema([
                        TextInput::make('phone')
                            ->label('Teléfono Principal')
                            ->tel()
                            ->required()
                            ->maxLength(255)
                            ->autocomplete('tel'),

                        TextInput::make('secondary_phone')
                            ->label('Teléfono Secundario')
                            ->tel()
                            ->maxLength(255)
                            ->autocomplete('tel'),

                        TextInput::make('email')
                            ->label('Correo Electrónico')
                            ->email()
                            ->maxLength(255)
                            ->autocomplete('email'),
                    ])
                    ->columns(3),

                Section::make('Ubicación')
                    ->schema([
                        Select::make('municipality_id')
                            ->label('Municipio')
                            ->relationship('municipality', 'name', fn ($query) => $query->orderBy('name'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (callable $set) => $set('neighborhood_id', null)),

                        Select::make('neighborhood_id')
                            ->label('Barrio')
                            ->relationship(
                                name: 'neighborhood',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query, $get) => $query
                                    ->when(
                                        $get('municipality_id'),
                                        fn ($query, $municipalityId) => $query->where('municipality_id', $municipalityId)
                                    )
                                    ->orderBy('name')
                            )
                            ->searchable()
                            ->preload()
                            ->disabled(fn ($get): bool => ! $get('municipality_id'))
                            ->helperText('Seleccione primero un municipio'),

                        TextInput::make('address')
                            ->label('Dirección')
                            ->maxLength(500)
                            ->columnSpanFull(),

                        Textarea::make('detailed_address')
                            ->label('Dirección Detallada')
                            ->rows(2)
                            ->columnSpanFull()
                            ->helperText('Referencias, puntos de interés cercanos, etc.'),
                    ])
                    ->columns(2),

                Section::make('Campaña y Estado')
                    ->schema([
                        Select::make('campaign_id')
                            ->label('Campaña')
                            ->relationship('campaign', 'name', fn ($query) => $query->orderBy('name'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default(fn () => Campaign::query()->where('status', 'active')->first()?->id ?? Campaign::query()->first()?->id)
                            ->disabled(fn (): bool => Campaign::count() <= 1)
                            ->dehydrated()
                            ->helperText('Campaña a la que pertenece el votante'),

                        Select::make('status')
                            ->label('Estado')
                            ->options(VoterStatus::class)
                            ->default(VoterStatus::PENDING_REVIEW)
                            ->required()
                            ->helperText('Estado actual del votante en el proceso'),
                    ])
                    ->columns(2),

                Section::make('Notas')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Observaciones')
                            ->rows(4)
                            ->columnSpanFull()
                            ->helperText('Información adicional relevante sobre el votante'),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}

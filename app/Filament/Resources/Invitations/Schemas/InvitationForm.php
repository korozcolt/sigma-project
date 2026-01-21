<?php

namespace App\Filament\Resources\Invitations\Schemas;

use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class InvitationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Asignación del registro')
                    ->schema([
                        Select::make('coordinator_user_id')
                            ->label('Coordinador')
                            ->relationship(
                                name: 'coordinator',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query) => $query->role('coordinator')->orderBy('name'),
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($state, callable $set) => $set('leader_user_id', null))
                            ->helperText('Primero selecciona el coordinador. Luego verás sus líderes.'),

                        Select::make('leader_user_id')
                            ->label('Líder')
                            ->relationship(
                                name: 'leader',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query, $get) => $query
                                    ->role('leader')
                                    ->when(
                                        $get('coordinator_user_id'),
                                        fn (Builder $q, $coordinatorId) => $q->where('coordinator_user_id', $coordinatorId),
                                    )
                                    ->orderBy('name'),
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->disabled(fn ($get): bool => ! $get('coordinator_user_id'))
                            ->helperText('Los votantes se asignarán a este líder.'),
                    ])
                    ->columns(2),
                
                Section::make('Alcance')
                    ->schema([
                        Select::make('campaign_id')
                            ->label('Campaña')
                            ->relationship('campaign', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        
                        Select::make('municipality_id')
                            ->label('Municipio')
                            ->relationship('municipality', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                    ])
                    ->columns(2),
                
                Section::make('Configuración')
                    ->schema([
                        DateTimePicker::make('expires_at')
                            ->label('Fecha de expiración')
                            ->nullable()
                            ->default(now()->addDays(7)),
                        
                        Textarea::make('notes')
                            ->label('Notas')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}

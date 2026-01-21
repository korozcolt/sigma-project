<?php

namespace App\Filament\Resources\Leaders\Schemas;

use App\Enums\UserRole;
use App\Models\Neighborhood;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class LeaderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Coordinador')
                ->description('Primero selecciona el coordinador. El municipio y campañas del líder se sincronizan con el coordinador.')
                ->schema([
                    Select::make('coordinator_user_id')
                        ->label('Coordinador')
                        ->relationship(
                            name: 'coordinator',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn (Builder $query) => $query->role(UserRole::COORDINATOR->value)->orderBy('name'),
                        )
                        ->required()
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set): void {
                            $municipalityId = User::query()->whereKey($state)->value('municipality_id');
                            $set('municipality_id', $municipalityId);
                            $set('neighborhood_id', null);
                        }),

                    TextInput::make('municipality_name')
                        ->label('Municipio')
                        ->disabled()
                        ->dehydrated(false)
                        ->formatStateUsing(function (Get $get): string {
                            $coordinatorId = $get('coordinator_user_id');

                            if (! $coordinatorId) {
                                return '—';
                            }

                            return (string) (User::query()
                                ->whereKey($coordinatorId)
                                ->with('municipality')
                                ->first()
                                ?->municipality
                                ?->name ?? '—');
                        }),

                    TextInput::make('municipality_id')
                        ->dehydrated()
                        ->hidden(),
                ])
                ->columns(2),

            Section::make('Información personal')
                ->schema([
                    TextInput::make('name')
                        ->label('Nombre completo')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('email')
                        ->label('Correo electrónico')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),

                    TextInput::make('document_number')
                        ->label('Número de documento')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(50),

                    DatePicker::make('birth_date')
                        ->label('Fecha de nacimiento')
                        ->maxDate(now()->subYears(18))
                        ->displayFormat('d/m/Y')
                        ->native(false),
                ])
                ->columns(2),

            Section::make('Contacto')
                ->schema([
                    TextInput::make('phone')
                        ->label('Teléfono principal')
                        ->tel()
                        ->required()
                        ->maxLength(20),

                    TextInput::make('secondary_phone')
                        ->label('Teléfono secundario')
                        ->tel()
                        ->maxLength(20),

                    Textarea::make('address')
                        ->label('Dirección')
                        ->rows(2)
                        ->maxLength(500)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('Ubicación')
                ->schema([
                    Select::make('neighborhood_id')
                        ->label('Barrio')
                        ->relationship(
                            name: 'neighborhood',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn (Builder $query, Get $get) => $query
                                ->when(
                                    $get('municipality_id'),
                                    fn (Builder $q, $municipalityId) => $q->where('municipality_id', $municipalityId),
                                )
                                ->orderBy('name'),
                        )
                        ->searchable()
                        ->preload()
                        ->disabled(fn (Get $get): bool => ! $get('municipality_id'))
                        ->helperText('Selecciona primero el coordinador para filtrar los barrios.'),
                ])
                ->columns(2),

            Section::make('Acceso')
                ->schema([
                    TextInput::make('password')
                        ->label('Contraseña')
                        ->password()
                        ->revealable()
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Hash::make($state) : null)
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->minLength(8),
                ])
                ->columns(2),
        ]);
    }
}

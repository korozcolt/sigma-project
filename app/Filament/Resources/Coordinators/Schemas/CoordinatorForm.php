<?php

namespace App\Filament\Resources\Coordinators\Schemas;

use App\Services\CampaignContext;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class CoordinatorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
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
                    Select::make('municipality_id')
                        ->label('Municipio')
                        ->relationship('municipality', 'name', function (Builder $query) {
                            $campaign = CampaignContext::currentCampaign();

                            // Campaña municipal: solo el municipio de la campaña
                            if ($campaign?->municipality_id) {
                                return $query->where('id', $campaign->municipality_id)->orderBy('name');
                            }

                            // Campaña departamental: solo municipios del departamento
                            if ($campaign?->department_id) {
                                return $query->where('department_id', $campaign->department_id)->orderBy('name');
                            }

                            // Sin contexto de campaña (super admin modo global): todos
                            return $query->orderBy('name');
                        })
                        ->default(fn () => CampaignContext::currentCampaign()?->municipality_id)
                        ->helperText(function (): ?string {
                            $campaign = CampaignContext::currentCampaign();
                            if ($campaign?->municipality_id) {
                                return 'Fijado por la campaña activa.';
                            }
                            if ($campaign?->department_id) {
                                return 'Filtrado al departamento de la campaña activa.';
                            }

                            return null;
                        })
                        ->disabled(fn (): bool => CampaignContext::currentCampaign()?->municipality_id !== null)
                        ->dehydrated()
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(fn (callable $set) => $set('neighborhood_id', null))
                        ->required(),

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
                        ->disabled(fn (Get $get): bool => ! $get('municipality_id')),
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

                    Toggle::make('also_leader')
                        ->label('También será líder')
                        ->helperText('Permite que el coordinador aparezca como líder en su propio listado y tenga apoyos.')
                        ->dehydrated(false)
                        ->default(false),
                ])
                ->columns(2),
        ]);
    }
}

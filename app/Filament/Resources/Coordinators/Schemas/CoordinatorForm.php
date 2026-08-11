<?php

namespace App\Filament\Resources\Coordinators\Schemas;

use App\Enums\UserRole;
use App\Filament\Schemas\MetadataAssignment;
use App\Services\CampaignContext;
use App\Services\IdentityLookupService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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
                    Hidden::make('name_locked')->default(false)->dehydrated(false),

                    TextInput::make('name')
                        ->label('Nombre completo')
                        ->required()
                        ->maxLength(255)
                        ->disabled(fn (Get $get): bool => (bool) $get('name_locked'))
                        ->dehydrated()
                        ->suffixAction(
                            Action::make('unlock_name')
                                ->icon('heroicon-o-lock-open')
                                ->label('¿Nombre incorrecto? Editar manualmente')
                                ->tooltip('¿Nombre incorrecto? Editar manualmente')
                                ->visible(fn (Get $get): bool => (bool) $get('name_locked'))
                                ->action(fn (Set $set) => $set('name_locked', false))
                        ),

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
                        ->maxLength(50)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, Set $set): void {
                            if (blank($state)) {
                                return;
                            }

                            $identity = app(IdentityLookupService::class)->findByDocumentNumber($state);

                            if (! $identity) {
                                return;
                            }

                            $set('name', preg_replace('/\s+/', ' ', trim("{$identity->nombre1} {$identity->nombre2} {$identity->apellido1} {$identity->apellido2}")));
                            $set('name_locked', true);
                        }),

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

                    Select::make('area_coordinator_user_id')
                        ->label('Articulador')
                        ->relationship(
                            name: 'areaCoordinator',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn (Builder $query) => $query->role(UserRole::AREA_COORDINATOR->value)->orderBy('name'),
                        )
                        ->searchable()
                        ->preload()
                        ->helperText('Opcional. Filtrado automáticamente a los articuladores de la campaña activa.'),
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
            MetadataAssignment::section(),
        ]);
    }
}

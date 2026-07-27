<?php

namespace App\Filament\Resources\Voters\Schemas;

use App\Enums\CampaignScope;
use App\Enums\UserRole;
use App\Enums\VoterStatus;
use App\Models\Campaign;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\User;
use App\Models\Voter;
use App\Rules\DocumentNotBelongsToLeaderOrCoordinator;
use App\Rules\MaxTablesForPollingPlace;
use App\Services\CampaignContext;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema as DbSchema;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;

class VoterForm
{
    private static function campaignSelectColumns(): array
    {
        $columns = ['id', 'scope'];

        if (DbSchema::hasColumn('campaigns', 'department_id')) {
            $columns[] = 'department_id';
        }

        if (DbSchema::hasColumn('campaigns', 'municipality_id')) {
            $columns[] = 'municipality_id';
        }

        return $columns;
    }

    /**
     * Re-derives the campaign-fixed department/municipality for a create-form default.
     *
     * department_id and municipality_id have no ->default() of their own, so on create forms
     * Filament's schema hydration would otherwise null them out AFTER campaign_id's
     * afterStateHydrated/afterStateUpdated hooks have $set() them (they live later in the schema
     * and are processed afterwards). Giving them their own ->default() prevents that reset.
     *
     * @return array{department_id: ?int, municipality_id: ?int}
     */
    private static function resolveCampaignLocationDefaults(): array
    {
        $campaignId = CampaignContext::currentCampaignId();

        $campaign = $campaignId
            ? Campaign::query()->select(self::campaignSelectColumns())->find($campaignId)
            : null;

        if ($campaign?->scope?->value === CampaignScope::Municipal->value && filled($campaign->municipality_id)) {
            return [
                'department_id' => $campaign->department_id ?? Municipality::query()->whereKey($campaign->municipality_id)->value('department_id'),
                'municipality_id' => $campaign->municipality_id,
            ];
        }

        if ($campaign?->scope?->value === CampaignScope::Departamental->value && filled($campaign->department_id)) {
            return [
                'department_id' => $campaign->department_id,
                'municipality_id' => null,
            ];
        }

        return [
            'department_id' => null,
            'municipality_id' => null,
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Placeholder::make('_registraduria_modal')
                    ->hiddenLabel()
                    ->dehydrated(false)
                    ->columnSpanFull()
                    ->content(fn ($livewire): HtmlString => new HtmlString(
                        view('filament.registraduria-browser', [
                            'sessionId' => $livewire->registraduriaSessionId ?: '',
                            'isOpen' => $livewire->registraduriaOpen,
                        ])->render()
                    )),

                Hidden::make('campaign_scope_state')->dehydrated(false),
                Hidden::make('campaign_department_id_state')->dehydrated(false),
                Hidden::make('campaign_municipality_id_state')->dehydrated(false),

                Section::make('Campaña y Estado')
                    ->schema([
                        Select::make('campaign_id')
                            ->label('Campaña')
                            ->relationship('campaign', 'name', fn ($query) => $query->orderBy('name'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default(fn () => CampaignContext::currentCampaignId())
                            ->visible(fn (): bool => CampaignContext::isSuperAdmin())
                            ->disabled(fn (): bool => Campaign::count() <= 1)
                            ->dehydrated()
                            ->live()
                            ->afterStateHydrated(function ($state, callable $set, $record): void {
                                if (! $state) {
                                    return;
                                }

                                $campaign = Campaign::query()->select(self::campaignSelectColumns())->find($state);

                                $set('campaign_scope_state', $campaign?->scope?->value);
                                $set('campaign_department_id_state', $campaign?->department_id);
                                $set('campaign_municipality_id_state', $campaign?->municipality_id);

                                if ($record && filled($record->municipality_id) && ! filled($campaign?->department_id)) {
                                    $set('department_id', Municipality::query()->whereKey($record->municipality_id)->value('department_id'));
                                }

                                if ($campaign?->scope?->value === CampaignScope::Municipal->value && filled($campaign->municipality_id)) {
                                    $set('department_id', $campaign->department_id ?? Municipality::query()->whereKey($campaign->municipality_id)->value('department_id'));
                                    $set('municipality_id', $campaign->municipality_id);
                                } elseif ($campaign?->scope?->value === CampaignScope::Departamental->value && filled($campaign->department_id)) {
                                    $set('department_id', $campaign->department_id);
                                }
                            })
                            ->afterStateUpdated(function ($state, callable $set): void {
                                if (! $state) {
                                    $set('campaign_scope_state', null);
                                    $set('campaign_department_id_state', null);
                                    $set('campaign_municipality_id_state', null);

                                    return;
                                }

                                $campaign = Campaign::query()->select(self::campaignSelectColumns())->find($state);

                                $set('campaign_scope_state', $campaign?->scope?->value);
                                $set('campaign_department_id_state', $campaign?->department_id);
                                $set('campaign_municipality_id_state', $campaign?->municipality_id);

                                if ($campaign?->scope?->value === CampaignScope::Municipal->value && filled($campaign->municipality_id)) {
                                    $set('department_id', $campaign->department_id ?? Municipality::query()->whereKey($campaign->municipality_id)->value('department_id'));
                                    $set('municipality_id', $campaign->municipality_id);
                                } elseif ($campaign?->scope?->value === CampaignScope::Departamental->value && filled($campaign->department_id)) {
                                    $set('department_id', $campaign->department_id);
                                }

                                $set('polling_place_id', null);
                                $set('polling_table_number', null);
                            })
                            ->helperText('Campaña a la que pertenece el apoyo'),

                        Select::make('status')
                            ->label('Estado')
                            ->options(VoterStatus::class)
                            ->default(VoterStatus::PENDING_REVIEW)
                            ->required()
                            ->helperText('Estado actual del apoyo en el proceso'),
                    ])
                    ->columns(2),

                Section::make('Asignación')
                    ->schema([
                        Select::make('coordinator_user_id')
                            ->label('Coordinador')
                            ->options(fn () => User::query()
                                ->role(UserRole::COORDINATOR->value)
                                ->orderBy('name')
                                ->pluck('name', 'id'))
                            ->dehydrated(false)
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn ($state, callable $set) => $set('registered_by', null))
                            ->default(fn () => auth()->user()?->hasRole(UserRole::COORDINATOR->value) ? auth()->id() : null)
                            ->afterStateHydrated(function ($state, callable $set, $record): void {
                                if (! $record || filled($state)) {
                                    return;
                                }

                                $set('coordinator_user_id', $record->registeredBy?->coordinator_user_id);
                            }),

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
                            ->helperText('El apoyo siempre debe pertenecer a un líder.'),
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
                            ->helperText('Puede repetirse; se marcará como duplicado en disputa hasta que un admin lo resuelva')
                            ->live(onBlur: true)
                            ->suffixAction(
                                Action::make('consultar_registraduria')
                                    ->icon('heroicon-o-magnifying-glass')
                                    ->label('Consultar Registraduría')
                                    ->tooltip('Buscar puesto de votación en la Registraduría')
                                    ->action(function ($state, $livewire): void {
                                        $livewire->openRegistraduriaBrowser($state);
                                    })
                            )
                            ->suffixAction(
                                Action::make('actualizar_registraduria')
                                    ->icon('heroicon-o-arrow-path')
                                    ->label('Actualizar datos desde Registraduría')
                                    ->tooltip('Forzar nueva consulta a la Registraduría (ignora la caché)')
                                    ->color('gray')
                                    ->visible(fn (Get $get): bool => filled($get('polling_place_id'))
                                        && (auth()->user()?->hasAnyRole([
                                            UserRole::ADMIN_CAMPAIGN->value,
                                            UserRole::COORDINATOR->value,
                                            UserRole::SUPER_ADMIN->value,
                                        ]) ?? false))
                                    ->requiresConfirmation()
                                    ->modalHeading('Actualizar datos desde Registraduría')
                                    ->modalDescription('Esto hace una nueva consulta pagada a la Registraduría e ignora los datos ya guardados en caché/base de datos. Úsalo solo si necesitas corregir un puesto de votación desactualizado.')
                                    ->modalSubmitActionLabel('Sí, actualizar')
                                    ->action(function ($state, $livewire): void {
                                        $livewire->forceRefreshFromRegistraduria($state);
                                    })
                            )
                            ->rule(function (Get $get, $record) {
                                return [
                                    new DocumentNotBelongsToLeaderOrCoordinator($record?->id),
                                    function (string $attribute, $value, $fail) use ($get, $record) {
                                        $existing = Voter::withTrashed()
                                            ->where('document_number', $value)
                                            ->when($record?->id, fn ($q) => $q->where('id', '!=', $record->id))
                                            ->exists();

                                        if ($existing && ! $get('confirm_duplicate')) {
                                            $fail('Esta cédula ya está registrada. Marca "Confirmar registro como duplicado" para continuar.');
                                        }
                                    },
                                ];
                            }),

                        Toggle::make('confirm_duplicate')
                            ->label('Confirmar registro como duplicado')
                            ->dehydrated(false)
                            ->live()
                            ->visible(fn (Get $get, $record): bool => filled($get('document_number'))
                                && Voter::withTrashed()
                                    ->where('document_number', $get('document_number'))
                                    ->when($record?->id, fn ($q) => $q->where('id', '!=', $record->id))
                                    ->exists())
                            ->helperText('Esta cédula ya existe en el sistema. Al confirmar, este registro se creará como un Apoyo duplicado en disputa (D-02).'),

                        Select::make('gremio_id')
                            ->label('Gremio')
                            ->relationship(name: 'gremio', titleAttribute: 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (callable $set) => $set('subcategoria_id', null))
                            ->nullable(),

                        Select::make('subcategoria_id')
                            ->label('Subcategoría')
                            ->relationship(
                                name: 'subcategoria',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query, Get $get) => $query
                                    ->when($get('gremio_id'), fn (Builder $q, $gremioId) => $q->where('gremio_id', $gremioId))
                                    ->orderBy('name'),
                            )
                            ->searchable()
                            ->preload()
                            ->disabled(fn (Get $get): bool => ! filled($get('gremio_id')))
                            ->nullable(),

                        TextInput::make('lugar_expedicion_cedula')
                            ->label('Lugar de Expedición de Cédula')
                            ->maxLength(255),

                        TextInput::make('placa')
                            ->label('Placa')
                            ->maxLength(20)
                            ->helperText('Aplica principalmente a mototaxistas u oficios similares'),

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
                        Select::make('department_id')
                            ->label('Departamento')
                            ->options(fn () => Department::query()->orderBy('name')->pluck('name', 'id'))
                            ->dehydrated(false)
                            ->searchable()
                            ->preload()
                            ->default(fn (): ?int => self::resolveCampaignLocationDefaults()['department_id'])
                            ->disabled(fn (Get $get): bool => filled($get('campaign_department_id_state')) || filled($get('campaign_municipality_id_state')))
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set): void {
                                $set('municipality_id', null);
                                $set('polling_place_id', null);
                                $set('polling_table_number', null);
                            }),

                        Select::make('municipality_id')
                            ->label('Municipio')
                            ->relationship(
                                name: 'municipality',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query, Get $get) => $query
                                    ->when(
                                        filled($get('department_id')),
                                        fn (Builder $q) => $q->where('department_id', $get('department_id')),
                                    )
                                    ->orderBy('name'),
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->default(fn (): ?int => self::resolveCampaignLocationDefaults()['municipality_id'])
                            ->afterStateUpdated(function (callable $set): void {
                                $set('neighborhood_id', null);
                                $set('polling_place_id', null);
                                $set('polling_table_number', null);
                            })
                            ->disabled(fn (Get $get): bool => filled($get('campaign_municipality_id_state')))
                            ->dehydrated()
                            ->helperText('Si la campaña tiene un municipio definido, quedará fijo.'),

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
                            ->disabled(fn (Get $get): bool => ! filled($get('municipality_id')))
                            ->helperText(fn (Get $get): ?string => filled($get('municipality_id')) ? null : 'Seleccione primero un municipio'),

                        Select::make('polling_place_id')
                            ->label('Puesto de votación')
                            ->relationship(
                                name: 'pollingPlace',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query, Get $get) => $query
                                    ->when(
                                        filled($get('municipality_id')),
                                        fn (Builder $q) => $q->where('municipality_id', $get('municipality_id')),
                                    )
                                    ->orderBy('name'),
                            )
                            ->searchable()
                            ->preload()
                            ->disabled(fn (Get $get): bool => ! filled($get('municipality_id')))
                            ->rule(fn (Get $get) => filled($get('polling_place_id'))
                                ? Rule::exists('polling_places', 'id')
                                    ->where(fn ($query) => $query->where('municipality_id', $get('municipality_id')))
                                : null)
                            ->helperText('Opcional. Se usa para asignar el puesto de votación y validar el número de mesa.'),

                        TextInput::make('polling_table_number')
                            ->label('Número de mesa')
                            ->numeric()
                            ->minValue(1)
                            ->disabled(fn (Get $get): bool => ! filled($get('polling_place_id')))
                            ->rule(fn (Get $get) => filled($get('polling_place_id'))
                                ? new MaxTablesForPollingPlace((int) $get('polling_place_id'))
                                : null),

                        Placeholder::make('polling_place_source_display')
                            ->label('Fuente del Puesto de Votación')
                            ->columnSpanFull()
                            ->content(function (?Voter $record): HtmlString {
                                if (! $record || ! $record->polling_place_source) {
                                    return new HtmlString('<span class="text-sm text-gray-500">Sin resolver</span>');
                                }

                                $source = $record->polling_place_source;
                                $resolvedAt = $record->polling_place_resolved_at?->format('d/m/Y H:i') ?? 'N/D';

                                return new HtmlString(sprintf(
                                    '<x-filament::badge color="%s" icon="%s">%s</x-filament::badge> <span class="text-sm text-gray-500 ml-2">Actualizado: %s</span>',
                                    e($source->getColor()),
                                    e($source->getIcon()),
                                    e($source->getLabel()),
                                    e($resolvedAt)
                                ));
                            }),

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

                Section::make('Notas')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Observaciones')
                            ->rows(4)
                            ->columnSpanFull()
                            ->helperText('Información adicional relevante sobre el apoyo'),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}

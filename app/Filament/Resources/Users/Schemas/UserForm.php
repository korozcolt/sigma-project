<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Campaign;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información Personal')
                    ->schema([
                        FileUpload::make('profile_photo_path')
                            ->label('Foto de Perfil')
                            ->image()
                            ->avatar()
                            ->directory('profile-photos')
                            ->visibility('private')
                            ->columnSpanFull(),

                        TextInput::make('name')
                            ->label('Nombre Completo')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('Correo Electrónico')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        TextInput::make('document_number')
                            ->label('Número de Documento')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        DatePicker::make('birth_date')
                            ->label('Fecha de Nacimiento')
                            ->maxDate(now()->subYears(18))
                            ->displayFormat('d/m/Y')
                            ->native(false),
                    ])
                    ->columns(2),

                Section::make('Contraseña')
                    ->schema([
                        TextInput::make('password')
                            ->label('Contraseña')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Hash::make($state) : null)
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->minLength(8)
                            ->same('passwordConfirmation')
                            ->validationAttribute('contraseña'),

                        TextInput::make('passwordConfirmation')
                            ->label('Confirmar Contraseña')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(false)
                            ->minLength(8)
                            ->validationAttribute('confirmación de contraseña'),
                    ])
                    ->columns(2)
                    ->visible(fn (string $operation): bool => in_array($operation, ['create', 'edit'])),

                Section::make('Información de Contacto')
                    ->schema([
                        TextInput::make('phone')
                            ->label('Teléfono Principal')
                            ->tel()
                            ->required()
                            ->maxLength(255),

                        TextInput::make('secondary_phone')
                            ->label('Teléfono Secundario')
                            ->tel()
                            ->maxLength(255),

                        TextInput::make('address')
                            ->label('Dirección')
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Ubicación Territorial')
                    ->schema([
                        Select::make('municipality_id')
                            ->label('Municipio')
                            ->relationship('municipality', 'name', fn ($query) => $query->orderBy('name'))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (callable $set) => $set('neighborhood_id', null)),

                        Select::make('neighborhood_id')
                            ->label('Barrio')
                            ->relationship(
                                name: 'neighborhood',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query, Get $get) => $query
                                    ->when(
                                        $get('municipality_id'),
                                        fn ($query, $municipalityId) => $query->where('municipality_id', $municipalityId)
                                    )
                                    ->orderBy('name')
                            )
                            ->searchable()
                            ->preload()
                            ->disabled(fn (Get $get): bool => ! $get('municipality_id'))
                            ->helperText('Seleccione primero un municipio'),
                    ])
                    ->columns(2),

                Section::make('Roles y Permisos')
                    ->schema([
                        Select::make('roles')
                            ->label('Rol Principal')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->helperText('Seleccione los roles del usuario en el sistema')
                            ->columnSpanFull(),
                    ]),

                Section::make('Registro como Votante')
                    ->description('Los coordinadores y líderes deben estar incluidos en la base de datos electoral para las estadísticas')
                    ->schema([
                        Toggle::make('register_as_voter')
                            ->label('Registrar también como votante')
                            ->helperText('Crear perfil de votante con los datos de este usuario')
                            ->live()
                            ->default(false)
                            ->dehydrated(false)
                            ->visible(fn (string $operation) => $operation === 'create'),

                        Placeholder::make('voter_registered_info')
                            ->label('Estado')
                            ->content('Este usuario ya está registrado como votante')
                            ->visible(fn (string $operation, $record) => $operation === 'edit' && $record?->voter_id),

                        Select::make('voter_campaign_id')
                            ->label('Campaña del Votante')
                            ->options(Campaign::query()->pluck('name', 'id'))
                            ->required(fn (Get $get) => $get('register_as_voter'))
                            ->live()
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get, string $operation) => $operation === 'create' && $get('register_as_voter'))
                            ->helperText('Campaña a la que pertenecerá como votante')
                            ->dehydrated(false),

                        Textarea::make('voter_notes')
                            ->label('Notas del Votante')
                            ->rows(2)
                            ->visible(fn (Get $get, string $operation) => $operation === 'create' && $get('register_as_voter'))
                            ->helperText('Observaciones adicionales para el perfil de votante')
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Section::make('Asignaciones a Campañas')
                    ->schema([
                        Repeater::make('campaignAssignments')
                            ->label('Campañas Asignadas')
                            ->relationship('campaigns')
                            ->schema([
                                Select::make('id')
                                    ->label('Campaña')
                                    ->options(Campaign::query()->pluck('name', 'id'))
                                    ->required()
                                    ->distinct()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->searchable(),

                                Select::make('role_id')
                                    ->label('Rol en la Campaña')
                                    ->options(Role::query()->pluck('name', 'id'))
                                    ->required()
                                    ->helperText('Rol específico para esta campaña'),
                            ])
                            ->columns(2)
                            ->addActionLabel('Agregar Campaña')
                            ->reorderable(false)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => Campaign::find($state['id'])?->name ?? null)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}

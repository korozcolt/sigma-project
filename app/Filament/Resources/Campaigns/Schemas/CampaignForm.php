<?php

namespace App\Filament\Resources\Campaigns\Schemas;

use App\Enums\CampaignScope;
use App\Enums\CampaignStatus;
use App\Models\Department;
use App\Models\Municipality;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Schema as DbSchema;

class CampaignForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información General')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre de la Campaña')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        FileUpload::make('logo_path')
                            ->label('Logo de la Campaña')
                            ->image()
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                '1:1',
                                '16:9',
                            ])
                            ->maxSize(2048)
                            ->disk('public')
                            ->directory('campaign-logos')
                            ->visibility('public')
                            ->getUploadedFileUsing(static function (BaseFileUpload $component, string $file, string|array|null $storedFileNames): ?array {
                                /** @var \Illuminate\Filesystem\FilesystemAdapter $storage */
                                $storage = $component->getDisk();

                                $shouldFetchFileInformation = $component->shouldFetchFileInformation();

                                if ($shouldFetchFileInformation && ! $storage->exists($file)) {
                                    return null;
                                }

                                return [
                                    'name' => ($component->isMultiple() ? ($storedFileNames[$file] ?? null) : $storedFileNames) ?? basename($file),
                                    'size' => $shouldFetchFileInformation ? $storage->size($file) : 0,
                                    'type' => $shouldFetchFileInformation ? $storage->mimeType($file) : null,
                                    'url' => route('public.campaign-logo', ['filename' => basename($file)]),
                                ];
                            })
                            ->columnSpanFull()
                            ->helperText('Sube el logo de la campaña (máx. 2MB). Se mostrará en los reportes y en la aplicación.'),
                        Textarea::make('description')
                            ->label('Descripción')
                            ->rows(3)
                            ->columnSpanFull(),
                        TextInput::make('candidate_name')
                            ->label('Nombre del Candidato')
                            ->required()
                            ->maxLength(255),
                        Select::make('status')
                            ->label('Estado')
                            ->options(CampaignStatus::class)
                            ->default(CampaignStatus::DRAFT)
                            ->required(),
                        Select::make('scope')
                            ->label('Alcance de la Campaña')
                            ->options(CampaignScope::options())
                            ->default(CampaignScope::Municipal->value)
                            ->required()
                            ->helperText('Define el nivel territorial de la campaña')
                            ->live()
                            ->afterStateUpdated(function (string $state, callable $set): void {
                                if ($state === CampaignScope::Departamental->value) {
                                    $set('municipality_id', null);
                                }

                                if ($state === CampaignScope::Nacional->value) {
                                    $set('department_id', null);
                                    $set('municipality_id', null);
                                }
                            }),
                    ])
                    ->columns(2),

                Section::make('Ubicación')
                    ->schema([
                        Select::make('department_id')
                            ->label('Departamento')
                            ->options(fn () => Department::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required(fn (Get $get): bool => in_array($get('scope'), [
                                CampaignScope::Municipal->value,
                                CampaignScope::Departamental->value,
                            ], true))
                            ->visible(fn (Get $get): bool => in_array($get('scope'), [
                                CampaignScope::Municipal->value,
                                CampaignScope::Departamental->value,
                            ], true))
                            ->hidden(fn (): bool => ! DbSchema::hasColumn('campaigns', 'department_id'))
                            ->live()
                            ->afterStateUpdated(fn ($state, callable $set) => $set('municipality_id', null))
                            ->helperText('Define el departamento objetivo de la campaña.'),

                        Select::make('municipality_id')
                            ->label('Municipio')
                            ->options(fn (Get $get) => filled($get('department_id'))
                                ? Municipality::query()
                                    ->where('department_id', $get('department_id'))
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                : [])
                            ->searchable()
                            ->preload()
                            ->required(fn (Get $get): bool => $get('scope') === CampaignScope::Municipal->value)
                            ->visible(fn (Get $get): bool => $get('scope') === CampaignScope::Municipal->value)
                            ->hidden(fn (): bool => ! DbSchema::hasColumn('campaigns', 'municipality_id'))
                            ->disabled(fn (Get $get): bool => ! filled($get('department_id')))
                            ->helperText('Define el municipio objetivo (solo para campañas municipales).'),
                    ])
                    ->columns(2),

                Section::make('Fechas')
                    ->schema([
                        DatePicker::make('start_date')
                            ->label('Fecha de Inicio')
                            ->required()
                            ->default(now()),
                        DatePicker::make('end_date')
                            ->label('Fecha de Fin')
                            ->after('start_date'),
                        DatePicker::make('election_date')
                            ->label('Fecha de Elección')
                            ->required()
                            ->after('start_date'),
                    ])
                    ->columns(3),

                Section::make('Configuración')
                    ->schema([
                        KeyValue::make('settings')
                            ->label('Configuraciones Personalizadas')
                            ->keyLabel('Clave')
                            ->valueLabel('Valor')
                            ->addActionLabel('Agregar configuración')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\ImageEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\TextEntry;
use Filament\Schemas\Schema;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información Personal')
                    ->schema([
                        ImageEntry::make('profile_photo_path')
                            ->label('Foto de Perfil')
                            ->circular()
                            ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name='.urlencode($record->name).'&color=7F9CF5&background=EBF4FF')
                            ->columnSpanFull(),

                        TextEntry::make('name')
                            ->label('Nombre Completo'),

                        TextEntry::make('email')
                            ->label('Correo Electrónico')
                            ->copyable()
                            ->icon('heroicon-m-envelope'),

                        TextEntry::make('document_number')
                            ->label('Número de Documento'),

                        TextEntry::make('birth_date')
                            ->label('Fecha de Nacimiento')
                            ->date('d/m/Y'),
                    ])
                    ->columns(2),

                Section::make('Información de Contacto')
                    ->schema([
                        TextEntry::make('phone')
                            ->label('Teléfono Principal')
                            ->icon('heroicon-m-phone'),

                        TextEntry::make('secondary_phone')
                            ->label('Teléfono Secundario')
                            ->icon('heroicon-m-phone')
                            ->placeholder('No especificado'),

                        TextEntry::make('address')
                            ->label('Dirección')
                            ->columnSpanFull()
                            ->placeholder('No especificada'),
                    ])
                    ->columns(2),

                Section::make('Ubicación Territorial')
                    ->schema([
                        TextEntry::make('municipality.name')
                            ->label('Municipio')
                            ->placeholder('No asignado'),

                        TextEntry::make('neighborhood.name')
                            ->label('Barrio')
                            ->placeholder('No asignado'),
                    ])
                    ->columns(2),

                Section::make('Roles y Permisos')
                    ->schema([
                        TextEntry::make('roles.name')
                            ->label('Roles')
                            ->badge()
                            ->separator(',')
                            ->placeholder('Sin roles asignados'),
                    ]),

                Section::make('Campañas Asignadas')
                    ->schema([
                        TextEntry::make('campaigns.name')
                            ->label('Campañas')
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->placeholder('Sin campañas asignadas'),
                    ]),

                Section::make('Estadísticas')
                    ->schema([
                        TextEntry::make('registeredVoters_count')
                            ->label('Votantes Registrados')
                            ->state(fn ($record) => $record->registeredVoters()->count()),

                        TextEntry::make('campaigns_count')
                            ->label('Campañas Activas')
                            ->state(fn ($record) => $record->campaigns()->count()),

                        TextEntry::make('created_at')
                            ->label('Fecha de Registro')
                            ->dateTime('d/m/Y H:i'),
                    ])
                    ->columns(3),
            ]);
    }
}

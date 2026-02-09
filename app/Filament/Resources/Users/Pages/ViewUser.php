<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\EditAction;
use Filament\Infolists\Components;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema as SchemaType;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    public function infolist(SchemaType $schema): SchemaType
    {
        return $schema->schema([
            Components\TextEntry::make('name')
                ->label('Nombre'),
            Components\TextEntry::make('email')
                ->label('Correo'),
            Components\TextEntry::make('document_number')
                ->label('Documento'),
            Components\TextEntry::make('phone')
                ->label('Teléfono'),
            Components\TextEntry::make('municipality.name')
                ->label('Municipio'),
            Components\TextEntry::make('neighborhood.name')
                ->label('Barrio'),
        ]);
    }
}

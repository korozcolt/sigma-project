<?php

namespace App\Filament\Resources\Voters\Pages;

use App\Filament\Resources\Voters\VoterResource;
use Filament\Actions\EditAction;
use Filament\Infolists\Components;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema as SchemaType;

class ViewVoter extends ViewRecord
{
    protected static string $resource = VoterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    public function infolist(SchemaType $schema): SchemaType
    {
        return $schema->schema([
            Components\TextEntry::make('first_name')
                ->label('Nombres'),
            Components\TextEntry::make('last_name')
                ->label('Apellidos'),
            Components\TextEntry::make('document_number')
                ->label('Documento'),
            Components\TextEntry::make('phone')
                ->label('Teléfono'),
            Components\TextEntry::make('status')
                ->label('Estado')
                ->badge(),
            Components\TextEntry::make('municipality.name')
                ->label('Municipio'),
            Components\TextEntry::make('neighborhood.name')
                ->label('Barrio'),
            Components\TextEntry::make('campaign.name')
                ->label('Campaña'),
        ]);
    }
}

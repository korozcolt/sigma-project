<?php

namespace App\Filament\Resources\Gremios\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class GremioForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
            ]);
    }
}

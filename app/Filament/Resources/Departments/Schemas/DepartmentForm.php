<?php

namespace App\Filament\Resources\Departments\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DepartmentForm
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
                TextInput::make('code')
                    ->label('Código DANE')
                    ->required()
                    ->maxLength(10)
                    ->unique(ignoreRecord: true)
                    ->alphaNum(),
            ]);
    }
}

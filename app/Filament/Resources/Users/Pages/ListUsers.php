<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('export-coordinators')
                ->label('Exportar Coordinadores')
                ->url(route('campaign-admin.users.export.coordinators'))
                ->extraAttributes(['data-testid' => 'admin:export-coordinators'])
                ->openUrlInNewTab(),
            Action::make('export-witnesses')
                ->label('Exportar Testigos')
                ->url(route('campaign-admin.users.export.witnesses'))
                ->extraAttributes(['data-testid' => 'admin:export-witnesses'])
                ->openUrlInNewTab(),
            Action::make('export-annotators')
                ->label('Exportar Anotadores')
                ->url(route('campaign-admin.users.export.annotators'))
                ->extraAttributes(['data-testid' => 'admin:export-annotators'])
                ->openUrlInNewTab(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Resources\Messages\MessageBatchResource\Pages;

use App\Filament\Resources\Messages\MessageBatchResource;
use App\Filament\Widgets\MessageDeliveryFunnelChart;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMessageBatches extends ListRecords
{
    protected static string $resource = MessageBatchResource::class;

    protected static ?string $title = 'Envíos Masivos';

    protected function getHeaderWidgets(): array
    {
        return [
            MessageDeliveryFunnelChart::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear Envío Masivo'),
        ];
    }
}

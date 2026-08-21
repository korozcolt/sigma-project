<?php

namespace App\Filament\Resources\VerificationCalls\Pages;

use App\Filament\Resources\VerificationCalls\VerificationCallResource;
use App\Filament\Widgets\CallCenterCallsSparklineWidget;
use App\Filament\Widgets\CallCenterStatsWidget;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVerificationCalls extends ListRecords
{
    protected static string $resource = VerificationCallResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            CallCenterStatsWidget::class,
            CallCenterCallsSparklineWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

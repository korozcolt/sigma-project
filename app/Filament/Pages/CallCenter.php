<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\CallCenterStatsOverview;
use App\Filament\Widgets\CallHistoryTable;
use App\Filament\Widgets\CallQueueTable;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class CallCenter extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhone;

    protected static ?string $navigationLabel = 'Centro de Llamadas';

    protected static ?string $title = 'Centro de Llamadas';

    protected static string|UnitEnum|null $navigationGroup = 'Call Center';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.call-center';

    protected function getHeaderWidgets(): array
    {
        return [
            CallCenterStatsOverview::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            CallQueueTable::class,
            CallHistoryTable::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 2;
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(['reviewer', 'admin_campaign', 'super_admin']) ?? false;
    }
}

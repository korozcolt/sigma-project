<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Campaign;
use App\Services\CallAssignmentService;
use Filament\Actions\Action;
use App\Filament\Widgets\CallCenterStatsOverview;
use App\Filament\Widgets\CallHistoryTable;
use App\Filament\Widgets\CallQueueTable;
use BackedEnum;
use Filament\Notifications\Notification;
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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('load_queue')
                ->label('Cargar 5')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('primary')
                ->action(function (): void {
                    $user = Auth::user();

                    if (! $user) {
                        return;
                    }

                    $campaign = Campaign::query()->where('status', 'active')->first() ?? Campaign::query()->first();

                    if (! $campaign) {
                        Notification::make()
                            ->title('No hay campaña configurada')
                            ->warning()
                            ->send();

                        return;
                    }

                    $service = app(CallAssignmentService::class);

                    $created = $service->loadBatchForCaller(
                        campaign: $campaign,
                        caller: $user,
                        assignedBy: $user,
                        targetQueueSize: 5,
                    );

                    if ($created === 0) {
                        Notification::make()
                            ->title('Ya tienes tu cola completa o no hay votantes disponibles')
                            ->info()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title("Se asignaron {$created} votantes a tu cola")
                        ->success()
                        ->send();
                }),
        ];
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(['reviewer', 'admin_campaign', 'super_admin']) ?? false;
    }
}

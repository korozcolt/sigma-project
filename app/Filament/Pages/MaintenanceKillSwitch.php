<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\UserRole;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class MaintenanceKillSwitch extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPower;

    protected static ?string $navigationLabel = 'Modo Mantenimiento';

    protected static ?string $title = 'Modo Mantenimiento';

    protected static string|\UnitEnum|null $navigationGroup = 'Configuración';

    protected string $view = 'filament.pages.maintenance-kill-switch';

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(UserRole::SUPER_ADMIN->value) ?? false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('toggleMaintenance')
                ->label(fn (): string => app()->isDownForMaintenance() ? 'Desactivar Mantenimiento' : 'Activar Mantenimiento')
                ->color(fn (): string => app()->isDownForMaintenance() ? 'success' : 'danger')
                ->icon(fn (): string => app()->isDownForMaintenance() ? 'heroicon-o-play' : 'heroicon-o-power')
                ->requiresConfirmation()
                ->modalDescription(fn (): string => app()->isDownForMaintenance()
                    ? '¿Reactivar la aplicación para todos los usuarios?'
                    : '¿Poner la aplicación en mantenimiento? Todos los usuarios excepto Super Admin verán una página de mantenimiento.')
                ->action(function (): void {
                    if (app()->isDownForMaintenance()) {
                        Artisan::call('up');

                        Notification::make()->title('Mantenimiento desactivado')->success()->send();
                    } else {
                        $secret = Str::random(40);

                        Artisan::call('down', ['--retry' => 60, '--secret' => $secret]);

                        Notification::make()
                            ->title('Modo mantenimiento activado')
                            ->body('Enlace de acceso para Super Admin (guárdelo, no se mostrará de nuevo): '.url('/'.$secret))
                            ->warning()
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }

    public function getMaintenanceStatus(): bool
    {
        return app()->isDownForMaintenance();
    }
}

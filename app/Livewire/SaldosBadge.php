<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\TwoCaptchaBalanceSnapshot;
use App\Services\CampaignContext;
use App\Services\HablameSmsService;
use App\Services\TwoCaptchaDailyCostService;
use App\Services\TwoCaptchaService;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class SaldosBadge extends Component
{
    /**
     * Consulta 2captcha en vivo (consume cuota de la API) y persiste el
     * resultado como un TwoCaptchaBalanceSnapshot — mismo mecanismo que el
     * snapshot horario (App\Console\Commands\SnapshotTwoCaptchaBalance), para
     * que el promedio diario/historial se mantengan consistentes.
     */
    public function refreshTwoCaptchaBalance(TwoCaptchaService $service): void
    {
        if (! CampaignContext::isSuperAdmin()) {
            return;
        }

        $balance = $service->getBalance();

        if ($balance === null) {
            Notification::make()
                ->warning()
                ->title('Saldo 2captcha no disponible')
                ->body('No se pudo consultar la API de 2captcha. Intenta de nuevo más tarde.')
                ->send();

            return;
        }

        TwoCaptchaBalanceSnapshot::create([
            'balance' => $balance,
            'checked_at' => now(),
        ]);

        Notification::make()
            ->success()
            ->title('Saldo 2captcha actualizado')
            ->send();
    }

    public function render(): View
    {
        $isSuperAdmin = CampaignContext::isSuperAdmin();

        $captchaBalance = null;
        $hablameBalance = null;
        $dailyCosts = collect();

        if ($isSuperAdmin) {
            $captchaSnapshot = TwoCaptchaBalanceSnapshot::query()->orderByDesc('checked_at')->first();
            $captchaBalance = $captchaSnapshot?->balance !== null ? (float) $captchaSnapshot->balance : null;

            $hablameInfo = Cache::remember('saldo_hablame', now()->addHour(), fn () => app(HablameSmsService::class)->getAccountInfo());
            $hablameBalance = ($hablameInfo['success'] ?? false) ? $hablameInfo['balance'] : null;

            $dailyCosts = app(TwoCaptchaDailyCostService::class)->lastDays(7);
        }

        return view('livewire.saldos-badge', [
            'isSuperAdmin' => $isSuperAdmin,
            'captchaBalance' => $captchaBalance,
            'hablameBalance' => $hablameBalance,
            'dailyCosts' => $dailyCosts,
        ]);
    }
}

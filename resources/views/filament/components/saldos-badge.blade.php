@php
    use App\Enums\DailyCaptchaCostStatus;
    use App\Models\TwoCaptchaBalanceSnapshot;
    use App\Services\CampaignContext;
    use App\Services\HablameSmsService;
    use App\Services\SaldoColorResolver;
    use App\Services\TwoCaptchaDailyCostService;
    use Illuminate\Support\Facades\Cache;

    if (! CampaignContext::isSuperAdmin()) {
        return;
    }

    // Lee la última fila de snapshot — NUNCA llama a la API de 2captcha aquí.
    $captchaSnapshot = TwoCaptchaBalanceSnapshot::query()->orderByDesc('checked_at')->first();
    $captchaBalance = $captchaSnapshot?->balance !== null ? (float) $captchaSnapshot->balance : null;

    // Hablame se cachea 1h — nunca se llama de forma síncrona en cada carga de la topbar.
    $hablameInfo = Cache::remember('saldo_hablame', now()->addHour(), fn () => app(HablameSmsService::class)->getAccountInfo());
    $hablameBalance = ($hablameInfo['success'] ?? false) ? $hablameInfo['balance'] : null;

    $dailyCosts = app(TwoCaptchaDailyCostService::class)->lastDays(7);
@endphp

<div
    id="saldos-badge"
    x-data="{ open: false }"
    @click.outside="open = false"
    class="relative"
>
    <button
        type="button"
        @click="open = ! open"
        class="flex items-center gap-1 rounded-lg px-2 py-1 text-sm text-gray-500 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-white/5"
    >
        <x-heroicon-o-banknotes class="h-5 w-5" />
        <span class="hidden sm:inline">Saldos</span>
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition
        class="absolute right-0 z-50 mt-2 w-80 rounded-xl bg-white p-4 shadow-lg ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
    >
        <div class="mb-3 flex items-center justify-between">
            <span class="text-xs font-medium text-gray-500">Saldo Hablame</span>
            <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ SaldoColorResolver::hablame($hablameBalance) }}">
                @if ($hablameBalance !== null)
                    {{ number_format($hablameBalance, 0, ',', '.') }} COP
                @else
                    N/D
                @endif
            </span>
        </div>

        <div class="mb-4 flex items-center justify-between">
            <span class="text-xs font-medium text-gray-500">Saldo 2captcha</span>
            <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ SaldoColorResolver::twoCaptcha($captchaBalance) }}">
                @if ($captchaBalance !== null)
                    ${{ number_format($captchaBalance, 5) }}
                @else
                    N/D
                @endif
            </span>
        </div>

        <div class="text-xs font-medium text-gray-500">Costo promedio 2captcha (últimos 7 días)</div>
        <ul class="mt-1 space-y-1">
            @foreach ($dailyCosts as $dia)
                <li class="flex items-center justify-between text-xs">
                    <span class="text-gray-500">{{ $dia->day->format('d/m') }}</span>
                    <span class="font-medium text-gray-700 dark:text-gray-300">
                        @if ($dia->status === DailyCaptchaCostStatus::Computed)
                            ${{ number_format($dia->averageUsd, 5) }}
                        @elseif ($dia->status === DailyCaptchaCostStatus::RechargeDetected)
                            Recarga detectada
                        @else
                            —
                        @endif
                    </span>
                </li>
            @endforeach
        </ul>
    </div>
</div>

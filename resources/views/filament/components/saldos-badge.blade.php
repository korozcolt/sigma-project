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

<x-filament::dropdown id="saldos-badge" style="margin-inline-start: 1rem" width="xs" placement="bottom-end" teleport="true">
    <x-slot name="trigger">
        <x-filament::icon-button
            icon="heroicon-o-banknotes"
            label="Saldos"
        />
    </x-slot>

    <div class="fi-dropdown-list p-3">
        <div class="mb-3 flex items-center justify-between gap-2">
            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Saldo Hablame</span>
            <x-filament::badge :color="SaldoColorResolver::hablame($hablameBalance)">
                @if ($hablameBalance !== null)
                    {{ number_format($hablameBalance, 0, ',', '.') }} COP
                @else
                    N/D
                @endif
            </x-filament::badge>
        </div>

        <div class="mb-4 flex items-center justify-between gap-2">
            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Saldo 2captcha</span>
            <x-filament::badge :color="SaldoColorResolver::twoCaptcha($captchaBalance)">
                @if ($captchaBalance !== null)
                    {{ number_format($captchaBalance, 2) }} USD
                @else
                    N/D
                @endif
            </x-filament::badge>
        </div>

        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Costo promedio 2captcha (últimos 7 días)</div>
        <ul class="mt-1 space-y-1">
            @foreach ($dailyCosts as $dia)
                <li class="flex items-center justify-between text-xs">
                    <span class="text-gray-500 dark:text-gray-400">{{ $dia->day->format('d/m') }}</span>
                    <span class="font-medium text-gray-700 dark:text-gray-300">
                        @if ($dia->status === DailyCaptchaCostStatus::Computed)
                            {{ number_format($dia->averageUsd, 5) }} USD
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
</x-filament::dropdown>

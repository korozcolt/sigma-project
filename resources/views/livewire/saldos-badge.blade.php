@php
    use App\Enums\DailyCaptchaCostStatus;
    use App\Services\SaldoColorResolver;
@endphp

<div>
    @if ($isSuperAdmin)
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

                    <div class="flex items-center gap-1">
                        <x-filament::badge :color="SaldoColorResolver::twoCaptcha($captchaBalance)">
                            @if ($captchaBalance !== null)
                                {{ number_format($captchaBalance, 2) }} USD
                            @else
                                N/D
                            @endif
                        </x-filament::badge>

                        <span wire:loading wire:target="refreshTwoCaptchaBalance">
                            <x-filament::loading-indicator class="h-4 w-4 text-gray-400" />
                        </span>

                        <x-filament::icon-button
                            icon="heroicon-o-arrow-path"
                            label="Refrescar saldo 2captcha"
                            wire:click="refreshTwoCaptchaBalance"
                            wire:loading.attr="disabled"
                            wire:target="refreshTwoCaptchaBalance"
                        />
                    </div>
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
    @endif
</div>

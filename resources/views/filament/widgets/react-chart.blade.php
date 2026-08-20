@php
    $chartId = 'react-chart-' . $this->getId();
    $pollingInterval = $this->getPollingInterval();
@endphp

<x-filament-widgets::widget>
    <x-filament::section heading="React Island PoC">
        <div @if ($pollingInterval) wire:poll.{{ $pollingInterval }}="updateChartData" @endif>
            <div
                id="{{ $chartId }}"
                wire:ignore
                x-data="reactChartBridge({
                    initialData: @js($this->getCachedData()),
                    chartKind: @js($this->getChartKind()),
                    theme: @js('light'),
                })"
                class="relative min-h-[8rem]"
            >
                <div data-react-mount class="h-full w-full"></div>

                <div
                    data-react-fallback
                    class="hidden rounded-lg border border-red-300 bg-red-50 p-4 text-sm font-medium text-red-700 dark:border-red-800 dark:bg-red-950 dark:text-red-300"
                    role="alert"
                >
                    No se pudo cargar la gráfica.
                </div>
            </div>
        </div>
    </x-filament::section>

    <script>
        (function () {
            var containerId = @js($chartId);

            window.setTimeout(function () {
                var container = document.getElementById(containerId);
                if (!container || container.dataset.reactMounted === 'true') {
                    return;
                }
                var fallback = container.querySelector('[data-react-fallback]');
                if (fallback) {
                    fallback.classList.remove('hidden');
                }
            }, 5000);
        })();
    </script>
</x-filament-widgets::widget>

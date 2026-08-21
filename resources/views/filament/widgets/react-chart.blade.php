@php
    $chartId = 'react-chart-' . $this->getId();
    $pollingInterval = $this->getPollingInterval();
    $chartKind = $this->getChartKind();
    $questionId = property_exists($this, 'questionId') ? $this->questionId : null;
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        :heading="$this->getHeading()"
        :description="$this->getDescription()"
    >
        <div @if ($pollingInterval) wire:poll.{{ $pollingInterval }}="updateChartData" @endif>
            <div
                id="{{ $chartId }}"
                wire:ignore
                data-chart-kind="{{ $chartKind }}"
                @if ($questionId) data-question-id="{{ $questionId }}" @endif
                x-data="reactChartBridge({
                    initialData: @js($this->getCachedData()),
                    chartKind: @js($chartKind),
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

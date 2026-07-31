@php
    $pollingInterval = $this->getPollingInterval();
@endphp

<x-filament-widgets::widget
    :attributes="
        (new \Illuminate\View\ComponentAttributeBag)
            ->merge([
                'wire:poll.' . $pollingInterval => $pollingInterval ? true : null,
            ], escape: false)
    "
>
    @if ($run)
        @if (is_null($run->finished_at))
            <x-filament::section :compact="true">
                <div class="flex items-center gap-3">
                    <x-filament::loading-indicator class="h-5 w-5 text-warning-500" />

                    <span class="text-sm">
                        <span class="font-medium">Revalidación en progreso</span>
                        — iniciada {{ $run->started_at?->diffForHumans() }}.
                        {{ $run->processed }} / {{ $run->total }} apoyos procesados.
                    </span>
                </div>
            </x-filament::section>
        @else
            <div
                wire:key="revalidation-run-{{ $run->id }}"
                x-data="{ dismissedRunId: $persist(null).as('revalidationBannerDismissedRunId') }"
                x-show="dismissedRunId !== {{ $run->id }}"
                x-cloak
            >
                <x-filament::section :compact="true">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <x-filament::icon
                                icon="heroicon-o-check-circle"
                                class="h-5 w-5 text-success-500"
                            />

                            <span class="text-sm">
                                <span class="font-medium">Última revalidación finalizada</span>
                                {{ $run->finished_at->diffForHumans() }}:
                                {{ $run->processed }} apoyo{{ $run->processed === 1 ? '' : 's' }} revisado{{ $run->processed === 1 ? '' : 's' }},
                                {{ $run->changed }} cambiaron de estado.
                            </span>
                        </div>

                        <x-filament::icon-button
                            icon="heroicon-o-x-mark"
                            label="Cerrar"
                            x-on:click="dismissedRunId = {{ $run->id }}"
                        />
                    </div>
                </x-filament::section>
            </div>
        @endif
    @endif
</x-filament-widgets::widget>

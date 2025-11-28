<x-filament-panels::page>
    {{-- Evento Activo Actual --}}
    @if($activeEvent)
        <x-filament::section
            icon="heroicon-o-check-circle"
            icon-color="success"
        >
            <x-slot name="heading">
                Evento Activo: {{ $activeEvent->name }}
            </x-slot>

            <x-slot name="description">
                Este evento está actualmente en ejecución
            </x-slot>

            <x-slot name="headerEnd">
                <x-filament::button
                    color="warning"
                    wire:click="deactivateEvent({{ $activeEvent->id }})"
                    icon="heroicon-o-stop-circle"
                    size="sm"
                >
                    Desactivar
                </x-filament::button>
            </x-slot>

            <div class="grid gap-3 text-sm md:grid-cols-4">
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Tipo</span>
                    <div class="mt-1">
                        <x-filament::badge :color="$activeEvent->type === 'simulation' ? 'info' : 'danger'">
                            {{ $activeEvent->getTypeLabel() }}
                        </x-filament::badge>
                    </div>
                </div>

                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Fecha</span>
                    <p class="mt-1 font-medium">{{ $activeEvent->date->format('d/m/Y') }}</p>
                </div>

                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Horario</span>
                    <p class="mt-1 font-medium">
                        @if($activeEvent->start_time && $activeEvent->end_time)
                            {{ \Carbon\Carbon::parse($activeEvent->start_time)->format('H:i') }} -
                            {{ \Carbon\Carbon::parse($activeEvent->end_time)->format('H:i') }}
                        @else
                            Sin restricción
                        @endif
                    </p>
                </div>

                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Votos Registrados</span>
                    <p class="mt-1 font-medium">{{ $activeEvent->voteRecords()->count() }}</p>
                </div>
            </div>

            @if($activeEvent->notes)
                <div class="mt-4 rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Notas</p>
                    <p class="mt-1 text-sm">{{ $activeEvent->notes }}</p>
                </div>
            @endif
        </x-filament::section>
    @else
        <x-filament::section
            icon="heroicon-o-exclamation-triangle"
            icon-color="warning"
        >
            <x-slot name="heading">
                No hay eventos activos
            </x-slot>

            <x-slot name="description">
                Para iniciar el registro de votos, activa un evento programado para hoy.
            </x-slot>
        </x-filament::section>
    @endif

    {{-- Eventos Próximos --}}
    <x-filament::section>
        <x-slot name="heading">
            Eventos Próximos
        </x-slot>

        <x-slot name="headerEnd">
            <x-filament::badge color="info">
                {{ count($upcomingEvents) }}
            </x-filament::badge>
        </x-slot>

        @if(count($upcomingEvents) > 0)
            <div class="grid gap-4 md:grid-cols-2">
                @foreach($upcomingEvents as $event)
                    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <h4 class="font-semibold">{{ $event['name'] }}</h4>
                                    <x-filament::badge :color="$event['type'] === 'simulation' ? 'info' : 'danger'">
                                        {{ $event['type'] === 'simulation' ? 'Simulacro' : 'Día D Real' }}
                                    </x-filament::badge>
                                </div>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    {{ \Carbon\Carbon::parse($event['date'])->format('d/m/Y') }}
                                    @if($event['start_time'] && $event['end_time'])
                                        • {{ \Carbon\Carbon::parse($event['start_time'])->format('H:i') }} - {{ \Carbon\Carbon::parse($event['end_time'])->format('H:i') }}
                                    @endif
                                </p>
                            </div>
                        </div>

                        <div class="mt-3 flex items-center gap-2">
                            @if(\Carbon\Carbon::parse($event['date'])->isToday())
                                <x-filament::button
                                    color="success"
                                    size="sm"
                                    wire:click="activateEvent({{ $event['id'] }})"
                                    icon="heroicon-o-play"
                                >
                                    Activar Ahora
                                </x-filament::button>
                            @endif

                            <x-filament::button
                                color="danger"
                                size="sm"
                                outlined
                                wire:click="deleteEvent({{ $event['id'] }})"
                                icon="heroicon-o-trash"
                                wire:confirm="¿Estás seguro de eliminar este evento?"
                            >
                                Eliminar
                            </x-filament::button>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-gray-600 dark:text-gray-400">
                No hay eventos próximos programados
            </p>
        @endif
    </x-filament::section>

    {{-- Eventos Pasados --}}
    <x-filament::section>
        <x-slot name="heading">
            Eventos Realizados
        </x-slot>

        <x-slot name="headerEnd">
            <x-filament::badge color="gray">
                {{ count($pastEvents) }}
            </x-filament::badge>
        </x-slot>

        @if(count($pastEvents) > 0)
            <div class="space-y-2">
                @foreach($pastEvents as $event)
                    <div class="flex items-center justify-between rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <h4 class="font-medium">{{ $event['name'] }}</h4>
                                <x-filament::badge color="gray">
                                    {{ $event['type'] === 'simulation' ? 'Simulacro' : 'Día D Real' }}
                                </x-filament::badge>
                            </div>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                {{ \Carbon\Carbon::parse($event['date'])->format('d/m/Y') }}
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-medium">
                                {{ \App\Models\VoteRecord::where('election_event_id', $event['id'])->count() }} votos
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-gray-600 dark:text-gray-400">
                No hay eventos realizados aún
            </p>
        @endif
    </x-filament::section>
</x-filament-panels::page>

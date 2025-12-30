<x-filament-panels::page>
    {{-- Búsqueda --}}
    <x-filament::section>
        <x-slot name="heading">Búsqueda de Votante</x-slot>

        <form wire:submit.prevent="searchVoter">
            <div class="flex items-end gap-3">
                <div class="flex-1">
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="text"
                            placeholder="Número de documento..."
                            wire:model.defer="documentNumber"
                            autofocus
                        />
                    </x-filament::input.wrapper>
                </div>
                <x-filament::button type="submit" icon="heroicon-o-magnifying-glass">
                    Buscar
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>

    {{-- Información del Votante --}}
    @if ($voterId ?? false)
        <x-filament::section>
            <x-slot name="heading">{{ $voterData['full_name'] ?? 'N/A' }}</x-slot>
            <x-slot name="description">CC {{ $voterData['document_number'] ?? 'N/A' }}</x-slot> 

            <div class="space-y-4">
                {{-- Información de Contacto --}}
                <div class="grid gap-3 text-sm md:grid-cols-2">
                    <div class="flex items-center gap-2">
                        <span class="text-gray-500 dark:text-gray-400">📱 Teléfono:</span>
                        <span class="font-medium">{{ $voterData['phone'] ?? 'Sin teléfono' }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-gray-500 dark:text-gray-400">📍 Municipio:</span>
                        <span class="font-medium">{{ $voterData['municipality'] ?? 'N/A' }}</span>
                    </div>
                </div>

                {{-- Estado --}}
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Estado:</span>
                    <div class="mt-1">
                        <x-filament::badge
                            :color="match($voterData['status_value'] ?? null) {
                                'confirmed', 'voted' => 'success',
                                'did_not_vote' => 'danger',
                                default => 'warning'
                            }"
                        >
                            {{ $voterData['status_label'] ?? 'N/A' }}
                        </x-filament::badge>
                    </div>
                </div>

                {{-- Botones de Acción --}}
                <div class="grid gap-3 pt-2 md:grid-cols-2">
                    @if ($canMarkVoted ?? false)
                        <x-filament::button
                            color="success"
                            wire:click="markVoted"
                            icon="heroicon-o-hand-thumb-up"
                        >
                            Marcar VOTÓ
                        </x-filament::button>
                    @else
                        <div class="flex items-center justify-center rounded-lg bg-green-100 px-4 py-3 text-sm font-medium text-green-800 dark:bg-green-900/30 dark:text-green-300">
                            ✓ Ya marcado como VOTÓ
                        </div>
                    @endif

                    @if ($canMarkDidNotVote ?? false)
                        <x-filament::button
                            color="danger"
                            wire:click="markDidNotVote"
                            icon="heroicon-o-hand-thumb-down"
                            outlined
                        >
                            Marcar NO VOTÓ
                        </x-filament::button>
                    @else
                        <div class="flex items-center justify-center rounded-lg bg-red-100 px-4 py-3 text-sm font-medium text-red-800 dark:bg-red-900/30 dark:text-red-300">
                            ✗ Ya marcado como NO VOTÓ
                        </div>
                    @endif
                </div>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>

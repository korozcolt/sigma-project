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
                            data-testid="dia-d:document-input"
                        />
                    </x-filament::input.wrapper>
                </div>
                <x-filament::button type="submit" icon="heroicon-o-magnifying-glass" data-testid="dia-d:search-button">
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

                {{-- Evidencia (solo para marcar VOTÓ) --}}
                <div
                    class="space-y-3 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/20"
                    x-data="{
                        requesting: false,
                        request() {
                            if (!navigator.geolocation) { return; }
                            this.requesting = true;
                            navigator.geolocation.getCurrentPosition(
                                (pos) => {
                                    $wire.captureCoordinates(pos.coords.latitude, pos.coords.longitude);
                                    this.requesting = false;
                                },
                                () => { this.requesting = false; },
                                { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
                            );
                        },
                        init() { this.request(); },
                    }"
                >
                    <div class="text-sm font-medium text-gray-700 dark:text-gray-200">Evidencia (obligatoria para marcar VOTÓ)</div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs text-gray-600 dark:text-gray-400">Foto</label>
                            <input
                                type="file"
                                accept="image/*"
                                capture="environment"
                                wire:model="photo"
                                data-testid="dia-d:photo-input"
                                class="block w-full rounded-lg border border-gray-300 bg-white p-2 text-sm dark:border-gray-700 dark:bg-gray-950"
                            />
                            @error('photo')
                                <div class="mt-1 text-xs text-danger-600">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-xs text-gray-600 dark:text-gray-400">GPS</div>
                                    <div class="text-sm font-medium text-gray-800 dark:text-gray-200">
                                        @if($latitude && $longitude)
                                            {{ $latitude }}, {{ $longitude }}
                                        @else
                                            Sin capturar
                                        @endif
                                    </div>
                                </div>
                                <x-filament::button type="button" size="sm" outlined x-on:click="request()" x-bind:disabled="requesting">
                                    <span x-show="!requesting">Capturar</span>
                                    <span x-show="requesting">Capturando...</span>
                                </x-filament::button>
                            </div>
                            @error('latitude')
                                <div class="text-xs text-danger-600">{{ $message }}</div>
                            @enderror
                            @error('longitude')
                                <div class="text-xs text-danger-600">{{ $message }}</div>
                            @enderror

                            <x-filament::input.wrapper>
                                <x-filament::input
                                    type="text"
                                    placeholder="Puesto de votación (opcional)"
                                    wire:model.defer="pollingStation"
                                />
                            </x-filament::input.wrapper>
                        </div>
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
                            data-testid="dia-d:mark-voted"
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
                            data-testid="dia-d:mark-did-not-vote"
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

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
                            inputmode="numeric"
                            placeholder="Número de documento..."
                            wire:model.defer="documentNumber"
                            autofocus
                            data-testid="dia-d:document-input"
                        />
                    </x-filament::input.wrapper>
                </div>
                <x-filament::button type="submit" icon="heroicon-o-magnifying-glass" data-testid="dia-d:search-button" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="searchVoter">Buscar</span>
                    <span wire:loading wire:target="searchVoter">Buscando...</span>
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
                {{-- Estado prominente --}}
                <div class="flex items-center gap-2">
                    <span class="text-xs text-gray-500 dark:text-gray-400">Estado:</span>
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

                {{-- Botones de Acción — lo más prominente una vez encontrado el votante --}}
                <div class="grid gap-3 md:grid-cols-2">
                    @if ($canMarkVoted ?? false)
                        <x-filament::button
                            color="success"
                            wire:click="markVoted"
                            icon="heroicon-o-hand-thumb-up"
                            size="lg"
                            data-testid="dia-d:mark-voted"
                            wire:loading.attr="disabled"
                        >
                            <span wire:loading.remove wire:target="markVoted">✓ Marcar VOTÓ</span>
                            <span wire:loading wire:target="markVoted">Guardando...</span>
                        </x-filament::button>
                    @else
                        <div class="flex items-center justify-center rounded-lg bg-green-100 px-4 py-3 text-sm font-medium text-green-800 dark:bg-green-900/30 dark:text-green-300">
                            <x-heroicon-o-check-circle class="mr-2 h-5 w-5" aria-hidden="true" /> Ya marcado como VOTÓ
                        </div>
                    @endif

                    @if ($canMarkDidNotVote ?? false)
                        <x-filament::button
                            color="danger"
                            wire:click="markDidNotVote"
                            icon="heroicon-o-hand-thumb-down"
                            outlined
                            data-testid="dia-d:mark-did-not-vote"
                            wire:loading.attr="disabled"
                        >
                            <span wire:loading.remove wire:target="markDidNotVote">✗ Marcar NO VOTÓ</span>
                            <span wire:loading wire:target="markDidNotVote">Guardando...</span>
                        </x-filament::button>
                    @else
                        <div class="flex items-center justify-center rounded-lg bg-red-100 px-4 py-3 text-sm font-medium text-red-800 dark:bg-red-900/30 dark:text-red-300">
                            <x-heroicon-o-x-circle class="mr-2 h-5 w-5" aria-hidden="true" /> Ya marcado como NO VOTÓ
                        </div>
                    @endif
                </div>

                <hr class="border-gray-200 dark:border-gray-700">

                {{-- Información de Contacto --}}
                <div class="grid gap-3 text-sm md:grid-cols-2">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-phone class="h-4 w-4 text-gray-400" aria-hidden="true" />
                        <span class="text-gray-500 dark:text-gray-400">Teléfono:</span>
                        <span class="font-medium">{{ $voterData['phone'] ?? 'Sin teléfono' }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-map-pin class="h-4 w-4 text-gray-400" aria-hidden="true" />
                        <span class="text-gray-500 dark:text-gray-400">Municipio:</span>
                        <span class="font-medium">{{ $voterData['municipality'] ?? 'N/A' }}</span>
                    </div>
                </div>

                {{-- Evidencia (solo para marcar VOTÓ) --}}
                <div
                    class="space-y-3 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/20"
                    x-data="{
                        requesting: false,
                        photoPreview: null,
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
                        init() {
                            // GPS requested on interaction, not automatically, to give context first
                        },
                        handlePhoto(event) {
                            const file = event.target.files[0];
                            if (!file) return;
                            const reader = new FileReader();
                            reader.onload = (e) => { this.photoPreview = e.target.result; };
                            reader.readAsDataURL(file);
                        },
                    }"
                >
                    <div class="text-sm font-medium text-gray-700 dark:text-gray-200">
                        Evidencia
                        <span class="text-xs font-normal text-gray-500">(obligatoria para marcar VOTÓ)</span>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs text-gray-600 dark:text-gray-400">Foto del votante</label>
                            <input
                                type="file"
                                accept="image/*"
                                capture="environment"
                                wire:model="photo"
                                data-testid="dia-d:photo-input"
                                class="block w-full rounded-lg border border-gray-300 bg-white p-2 text-sm dark:border-gray-700 dark:bg-gray-950"
                                x-on:change="handlePhoto($event)"
                            />
                            @error('photo')
                                <div class="mt-1 text-xs text-danger-600">{{ $message }}</div>
                            @enderror
                            {{-- Photo preview --}}
                            <div x-show="photoPreview" x-cloak class="mt-2">
                                <img :src="photoPreview" class="h-24 w-24 rounded-lg object-cover shadow-sm" alt="Vista previa de foto">
                            </div>
                        </div>

                        <div class="space-y-2">
                            <div class="text-xs text-gray-600 dark:text-gray-400">
                                <x-heroicon-o-map-pin class="inline h-3.5 w-3.5" aria-hidden="true" /> GPS
                                <span class="block text-gray-400 text-xs">(necesario para registrar ubicación de voto)</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <div class="text-sm font-medium text-gray-800 dark:text-gray-200">
                                    @if($latitude && $longitude)
                                        {{ $latitude }}, {{ $longitude }}
                                    @else
                                        Sin capturar
                                    @endif
                                </div>
                                <x-filament::button type="button" size="sm" outlined x-on:click="request()" x-bind:disabled="requesting">
                                    <span x-show="!requesting">Capturar GPS</span>
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
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>

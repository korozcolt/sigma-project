<div>
    {{-- El componente vive dentro de páginas cuyo middleware permite roles que no
         necesariamente pueden asignar a ESTE registro (p. ej. admin_campaign o
         super_admin con otro contexto de campaña activo). Se renderiza vacío en
         lugar de abortar: un abort() en el mount() de un hijo tumba la página padre. --}}
    @if (! $this->canAssign)
    @else
        <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-1">Metadata</flux:heading>
            <flux:subheading class="mb-4">Valores del catálogo asignados a este usuario.</flux:subheading>

            @if ($successMessage)
                <div class="mb-4 rounded-xl bg-green-50 p-3 dark:bg-green-900/20">
                    <flux:text class="text-green-900 dark:text-green-100">{{ $successMessage }}</flux:text>
                </div>
            @endif

            {{-- D-09: solo el valor vigente por llave + quién y cuándo. Sin historial. --}}
            <div class="mb-6 space-y-2">
                @forelse ($this->currentValues as $current)
                    <div class="flex items-center justify-between gap-3 border-b border-zinc-100 pb-2 dark:border-zinc-800">
                        <flux:text class="font-medium">{{ $current->metadataKey?->label }}</flux:text>
                        <div class="flex items-center gap-2">
                            <flux:badge size="sm">{{ $current->value }}</flux:badge>
                            <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                                {{ $current->assignedByUser?->name ?? 'Sistema' }} ·
                                {{ $current->assigned_at?->format('d/m/Y H:i') }}
                            </flux:text>
                        </div>
                    </div>
                @empty
                    <flux:text class="text-zinc-500 dark:text-zinc-400">Sin metadata asignada.</flux:text>
                @endforelse
            </div>

            <form wire:submit="assign" class="space-y-4">
                <flux:select wire:model.live="metadataKeyId" label="Llave" placeholder="Selecciona una llave">
                    @foreach ($this->keys as $key)
                        <flux:select.option value="{{ $key->id }}">{{ $key->label }}</flux:select.option>
                    @endforeach
                </flux:select>

                @if ($this->selectedKey?->type === 'select')
                    <flux:select wire:model="value" label="Valor" placeholder="Selecciona un valor">
                        @foreach ($this->selectedKey->options ?? [] as $option)
                            <flux:select.option value="{{ $option }}">{{ $option }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @elseif ($this->selectedKey?->type === 'numeric')
                    <flux:input wire:model="value" type="number" step="0.01" label="Valor" />
                @elseif ($this->selectedKey?->type === 'date')
                    <flux:input wire:model="value" type="date" label="Valor" />
                @elseif ($this->selectedKey?->type === 'text')
                    <flux:input wire:model="value" label="Valor" />
                @endif

                @error('metadataKeyId') <flux:text class="text-red-600">{{ $message }}</flux:text> @enderror
                @error('value') <flux:text class="text-red-600">{{ $message }}</flux:text> @enderror

                <flux:button type="submit" variant="primary" icon="tag" :disabled="! $this->selectedKey">
                    Asignar
                </flux:button>
            </form>
        </div>
    @endif
</div>

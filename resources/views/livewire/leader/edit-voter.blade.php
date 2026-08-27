<?php

use App\Models\Gremio;
use App\Models\Subcategoria;
use App\Models\Voter;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

use function Livewire\Volt\layout;

layout('components.layouts::leader', ['title' => 'Editar Información Adicional']);

new class extends Component
{
    public Voter $voter;

    public ?int $gremio_id = null;

    public ?int $subcategoria_id = null;

    public ?string $lugar_expedicion_cedula = null;

    public ?string $placa = null;

    public function mount(Voter $voter): void
    {
        abort_unless($voter->registered_by === auth()->id(), 403);

        $this->authorize('update', $voter);

        $this->voter = $voter;
        $this->gremio_id = $voter->gremio_id;
        $this->subcategoria_id = $voter->subcategoria_id;
        $this->lugar_expedicion_cedula = $voter->lugar_expedicion_cedula;
        $this->placa = $voter->placa;
    }

    public function updatedGremioId(): void
    {
        $this->subcategoria_id = null;
    }

    public function getGremiosProperty()
    {
        return Gremio::orderBy('name')->get();
    }

    public function getSubcategoriasProperty()
    {
        if (! $this->gremio_id) {
            return collect();
        }

        return Subcategoria::where('gremio_id', $this->gremio_id)->orderBy('name')->get();
    }

    public function save(): void
    {
        $this->authorize('update', $this->voter);

        $this->validate([
            'gremio_id' => ['nullable', 'exists:gremios,id'],
            'subcategoria_id' => [
                'nullable',
                Rule::exists('subcategorias', 'id')->when(
                    filled($this->gremio_id),
                    fn ($rule) => $rule->where('gremio_id', $this->gremio_id),
                ),
            ],
            'lugar_expedicion_cedula' => ['nullable', 'string', 'max:255'],
            'placa' => ['nullable', 'string', 'max:20'],
        ]);

        $this->voter->update([
            'gremio_id' => $this->gremio_id,
            'subcategoria_id' => $this->subcategoria_id,
            'lugar_expedicion_cedula' => $this->lugar_expedicion_cedula,
            'placa' => $this->placa,
        ]);

        session()->flash('success', 'Información adicional actualizada.');

        $this->redirect(route('leader.my-voters'), navigate: true);
    }
}; ?>

<div class="mx-auto max-w-2xl space-y-6 p-6">
    <div class="flex items-center gap-4">
        <flux:button variant="ghost" :href="route('leader.my-voters')" wire:navigate icon="arrow-left" size="sm">
            Volver
        </flux:button>
        <div class="flex-1">
            <flux:heading size="xl">Editar Información Adicional</flux:heading>
            <flux:subheading>{{ $voter->first_name }} {{ $voter->last_name }} — {{ $voter->document_number }}</flux:subheading>
        </div>
    </div>

    <form wire:submit="save" class="flex flex-col gap-4">
        <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-zinc-900">
            <div class="flex flex-col gap-4">
                <flux:select wire:model.live="gremio_id" label="Gremio" placeholder="Selecciona un gremio (opcional)">
                    @foreach($this->gremios as $gremio)
                        <option value="{{ $gremio->id }}">{{ $gremio->name }}</option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="subcategoria_id" label="Subcategoría" placeholder="Selecciona una subcategoría (opcional)" :disabled="!$gremio_id">
                    @foreach($this->subcategorias as $subcategoria)
                        <option value="{{ $subcategoria->id }}">{{ $subcategoria->name }}</option>
                    @endforeach
                </flux:select>

                <flux:input wire:model.blur="lugar_expedicion_cedula" label="Lugar de Expedición de Cédula" type="text" placeholder="Sincelejo" />

                <flux:input wire:model.blur="placa" label="Placa" type="text" placeholder="ABC123" />
            </div>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row">
            <flux:button type="submit" variant="primary" class="flex-1" wire:loading.attr="disabled">
                Guardar Cambios
            </flux:button>
            <flux:button variant="ghost" :href="route('leader.my-voters')" wire:navigate class="flex-1">
                Cancelar
            </flux:button>
        </div>
    </form>
</div>

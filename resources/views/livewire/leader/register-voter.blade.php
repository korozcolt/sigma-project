<?php

use App\Enums\VoterStatus;
use App\Models\Campaign;
use App\Models\Municipality;
use App\Models\Neighborhood;
use App\Models\Voter;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use function Livewire\Volt\{state, rules, layout};

layout('components.layouts::leader', ['title' => 'Registrar Votante']);

new class extends Component {
    #[Validate('required|digits:10|unique:voters,document_number')]
    public string $document_number = '';

    #[Validate('required|string|max:255')]
    public string $first_name = '';

    #[Validate('required|string|max:255')]
    public string $last_name = '';

    #[Validate('required|digits:10')]
    public string $phone = '';

    #[Validate('nullable|digits:10')]
    public ?string $secondary_phone = null;

    #[Validate('nullable|email')]
    public ?string $email = null;

    #[Validate('required|exists:municipalities,id')]
    public ?int $municipality_id = null;

    #[Validate('nullable|exists:neighborhoods,id')]
    public ?int $neighborhood_id = null;

    #[Validate('nullable|string|max:500')]
    public ?string $address = null;

    #[Validate('nullable|date')]
    public ?string $birth_date = null;

    public bool $registerAnother = false;
    public bool $showSuccess = false;
    public ?string $lastVoterName = null;

    public function mount(): void
    {
        // Pre-cargar el municipio del líder si existe
        if (auth()->user()->municipality_id) {
            $this->municipality_id = auth()->user()->municipality_id;
        }
    }

    public function getMunicipalitiesProperty()
    {
        return Municipality::orderBy('name')->get();
    }

    public function getNeighborhoodsProperty()
    {
        if (! $this->municipality_id) {
            return collect();
        }

        return Neighborhood::where('municipality_id', $this->municipality_id)
            ->orderBy('name')
            ->get();
    }

    public function updatedMunicipalityId(): void
    {
        $this->neighborhood_id = null;
    }

    public function save(): void
    {
        $this->validate();

        // Obtener la primera campaña del líder
        $campaign = auth()->user()->campaigns()->first();

        if (! $campaign) {
            $this->addError('campaign', 'No tienes una campaña asignada. Contacta al administrador.');

            return;
        }

        // Crear el votante
        Voter::create([
            'campaign_id' => $campaign->id,
            'document_number' => $this->document_number,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone' => $this->phone,
            'secondary_phone' => $this->secondary_phone,
            'email' => $this->email,
            'municipality_id' => $this->municipality_id,
            'neighborhood_id' => $this->neighborhood_id,
            'address' => $this->address,
            'birth_date' => $this->birth_date,
            'registered_by' => auth()->id(),
            'status' => VoterStatus::PENDING_REVIEW,
        ]);

        $this->lastVoterName = $this->first_name.' '.$this->last_name;
        $this->showSuccess = true;

        // Si marcó "Registrar otro", limpiar formulario
        if ($this->registerAnother) {
            $this->reset([
                'document_number',
                'first_name',
                'last_name',
                'phone',
                'secondary_phone',
                'email',
                'neighborhood_id',
                'address',
                'birth_date',
            ]);

            // Ocultar mensaje de éxito después de 2 segundos
            $this->dispatch('voter-registered');
        } else {
            // Redirigir a "Mis Votantes" después de 1 segundo
            $this->dispatch('redirect-to-voters');
        }
    }
}; ?>

<div class="flex flex-col gap-6">
        <!-- Success Message -->
        @if($showSuccess)
            <div
                x-data="{ show: true }"
                x-show="show"
                x-init="setTimeout(() => show = false, 3000)"
                class="rounded-xl bg-green-50 p-4 dark:bg-green-900/20"
            >
                <div class="flex items-center gap-3">
                    <div class="rounded-full bg-green-100 p-2 dark:bg-green-900/50">
                        <flux:icon.check-circle class="h-5 w-5 text-green-600 dark:text-green-400" />
                    </div>
                    <div>
                        <p class="font-medium text-green-900 dark:text-green-100">¡Votante registrado!</p>
                        <p class="text-sm text-green-700 dark:text-green-300">{{ $lastVoterName }} fue agregado exitosamente</p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Form -->
        <form wire:submit="save" class="flex flex-col gap-4">
            <!-- Datos Personales -->
            <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-zinc-900">
                <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-white">Datos Personales</h2>

                <div class="flex flex-col gap-4">
                    <flux:input
                        wire:model.blur="document_number"
                        label="Número de Documento *"
                        type="number"
                        placeholder="1234567890"
                        inputmode="numeric"
                    />

                    <flux:input
                        wire:model.blur="first_name"
                        label="Nombres *"
                        type="text"
                        placeholder="Juan Carlos"
                        autocomplete="given-name"
                    />

                    <flux:input
                        wire:model.blur="last_name"
                        label="Apellidos *"
                        type="text"
                        placeholder="Pérez García"
                        autocomplete="family-name"
                    />

                    <flux:input
                        wire:model.blur="birth_date"
                        label="Fecha de Nacimiento"
                        type="date"
                    />
                </div>
            </div>

            <!-- Contacto -->
            <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-zinc-900">
                <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-white">Contacto</h2>

                <div class="flex flex-col gap-4">
                    <flux:input
                        wire:model.blur="phone"
                        label="Teléfono Principal *"
                        type="tel"
                        placeholder="3001234567"
                        inputmode="tel"
                        autocomplete="tel"
                    />

                    <flux:input
                        wire:model.blur="secondary_phone"
                        label="Teléfono Secundario"
                        type="tel"
                        placeholder="3009876543"
                        inputmode="tel"
                    />

                    <flux:input
                        wire:model.blur="email"
                        label="Correo Electrónico"
                        type="email"
                        placeholder="correo@ejemplo.com"
                        autocomplete="email"
                    />
                </div>
            </div>

            <!-- Ubicación -->
            <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-zinc-900">
                <h2 class="mb-4 text-lg font-semibold text-zinc-900 dark:text-white">Ubicación</h2>

                <div class="flex flex-col gap-4">
                    <flux:select
                        wire:model.live="municipality_id"
                        label="Municipio *"
                        placeholder="Selecciona un municipio"
                    >
                        @foreach($this->municipalities as $municipality)
                            <option value="{{ $municipality->id }}">{{ $municipality->name }}</option>
                        @endforeach
                    </flux:select>

                    <flux:select
                        wire:model.live="neighborhood_id"
                        label="Barrio"
                        placeholder="Selecciona un barrio"
                        :disabled="!$municipality_id"
                    >
                        @foreach($this->neighborhoods as $neighborhood)
                            <option value="{{ $neighborhood->id }}">{{ $neighborhood->name }}</option>
                        @endforeach
                    </flux:select>

                    <flux:textarea
                        wire:model.blur="address"
                        label="Dirección"
                        rows="2"
                        placeholder="Calle 123 #45-67"
                    />
                </div>
            </div>

            <!-- Register Another Checkbox -->
            <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-zinc-900">
                <flux:checkbox
                    wire:model.live="registerAnother"
                    label="Registrar otro votante después de guardar"
                />
            </div>

            <!-- Actions -->
            <div class="flex flex-col gap-3">
                <flux:button
                    type="submit"
                    variant="primary"
                    class="w-full"
                >
                    @if($registerAnother)
                        Guardar y Registrar Otro
                    @else
                        Guardar Votante
                    @endif
                </flux:button>

                <flux:button
                    variant="ghost"
                    href="{{ route('leader.dashboard') }}"
                    wire:navigate
                    class="w-full"
                >
                    Cancelar
                </flux:button>
            </div>
        </form>
</div>

@script
<script>
    $wire.on('redirect-to-voters', () => {
        setTimeout(() => {
            window.location.href = '{{ route('leader.my-voters') }}';
        }, 1000);
    });
</script>
@endscript

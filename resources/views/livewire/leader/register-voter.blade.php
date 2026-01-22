<?php

use App\Enums\VoterStatus;
use App\Models\Campaign;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\Neighborhood;
use App\Models\PollingPlace;
use App\Models\Voter;
use App\Rules\MaxTablesForPollingPlace;
use Livewire\Volt\Component;
use Illuminate\Validation\Rule;
use function Livewire\Volt\{layout};

layout('components.layouts::leader', ['title' => 'Registrar Votante']);

new class extends Component {
    public string $document_number = '';

    public string $first_name = '';

    public string $last_name = '';

    public string $phone = '';

    public ?string $secondary_phone = null;

    public ?string $email = null;

    public ?int $department_id = null;

    public ?int $municipality_id = null;

    public ?int $neighborhood_id = null;

    public ?int $polling_place_id = null;

    public ?int $polling_table_number = null;

    public ?string $address = null;

    public ?string $birth_date = null;

    public ?int $campaign_id = null;

    public bool $departmentLocked = false;
    public bool $municipalityLocked = false;

    public bool $registerAnother = false;
    public bool $showSuccess = false;
    public ?string $lastVoterName = null;

    public function mount(): void
    {
        $campaign = auth()->user()->campaigns()->first();
        $this->campaign_id = $campaign?->id;

        $this->departmentLocked = (bool) ($campaign?->department_id || $campaign?->municipality_id);
        $this->municipalityLocked = (bool) $campaign?->municipality_id;

        if ($campaign?->municipality_id) {
            $this->municipality_id = $campaign->municipality_id;
            $this->department_id = $campaign->department_id
                ?? Municipality::query()->whereKey($campaign->municipality_id)->value('department_id');
        } elseif ($campaign?->department_id) {
            $this->department_id = $campaign->department_id;
        } elseif (auth()->user()->municipality_id) {
            $this->municipality_id = auth()->user()->municipality_id;
            $this->department_id = Municipality::query()->whereKey($this->municipality_id)->value('department_id');
        }
    }

    public function getDepartmentsProperty()
    {
        return Department::orderBy('name')->get();
    }

    public function getMunicipalitiesProperty()
    {
        return Municipality::query()
            ->when($this->department_id, fn ($q) => $q->where('department_id', $this->department_id))
            ->orderBy('name')
            ->get();
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
        $this->polling_place_id = null;
        $this->polling_table_number = null;
    }

    public function updatedDepartmentId(): void
    {
        $this->municipality_id = null;
        $this->neighborhood_id = null;
        $this->polling_place_id = null;
        $this->polling_table_number = null;
    }

    public function getPollingPlacesProperty()
    {
        if (! $this->municipality_id) {
            return collect();
        }

        return PollingPlace::query()
            ->where('municipality_id', $this->municipality_id)
            ->orderBy('name')
            ->get();
    }

    public function save(): void
    {
        $campaign = $this->campaign_id ? Campaign::query()->find($this->campaign_id) : auth()->user()->campaigns()->first();

        if (! $campaign) {
            $this->addError('campaign', 'No tienes una campaña asignada. Contacta al administrador.');

            return;
        }

        $this->validate([
            'document_number' => [
                'required',
                'digits:10',
                Rule::unique('voters', 'document_number')
                    ->where(fn ($query) => $query
                        ->where('campaign_id', $campaign->id)
                        ->whereNull('deleted_at')),
            ],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'digits:10'],
            'secondary_phone' => ['nullable', 'digits:10'],
            'email' => ['nullable', 'email', 'max:255'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'municipality_id' => [
                'required',
                Rule::exists('municipalities', 'id')->when(
                    filled($this->department_id),
                    fn ($rule) => $rule->where('department_id', $this->department_id),
                ),
            ],
            'neighborhood_id' => ['nullable', 'exists:neighborhoods,id'],
            'polling_place_id' => [
                'nullable',
                Rule::exists('polling_places', 'id')->when(
                    filled($this->municipality_id),
                    fn ($rule) => $rule->where('municipality_id', $this->municipality_id),
                ),
            ],
            'polling_table_number' => array_values(array_filter([
                'nullable',
                'integer',
                'min:1',
                $this->polling_place_id ? new MaxTablesForPollingPlace((int) $this->polling_place_id) : null,
            ])),
            'address' => ['nullable', 'string', 'max:500'],
            'birth_date' => ['nullable', 'date'],
        ]);

        if ($campaign->prefersMunicipality() && filled($campaign->municipality_id) && (int) $this->municipality_id !== (int) $campaign->municipality_id) {
            $this->addError('municipality_id', 'El municipio debe coincidir con el de la campaña.');
            return;
        }

        if ($campaign->prefersDepartment() && filled($campaign->department_id) && filled($this->municipality_id)) {
            $municipalityDepartmentId = Municipality::query()->whereKey($this->municipality_id)->value('department_id');
            if ((int) $municipalityDepartmentId !== (int) $campaign->department_id) {
                $this->addError('municipality_id', 'El municipio debe pertenecer al departamento de la campaña.');
                return;
            }
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
            'polling_place_id' => $this->polling_place_id,
            'polling_table_number' => $this->polling_table_number,
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
                'polling_place_id',
                'polling_table_number',
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
                        wire:model.live="department_id"
                        label="Departamento"
                        placeholder="Selecciona un departamento"
                        :disabled="$departmentLocked"
                    >
                        @foreach($this->departments as $department)
                            <option value="{{ $department->id }}">{{ $department->name }}</option>
                        @endforeach
                    </flux:select>

                    <flux:select
                        wire:model.live="municipality_id"
                        label="Municipio *"
                        placeholder="Selecciona un municipio"
                        :disabled="$municipalityLocked"
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

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:select
                            wire:model.live="polling_place_id"
                            label="Puesto de votación"
                            placeholder="Selecciona un puesto (opcional)"
                            :disabled="!$municipality_id"
                        >
                            <option value="">Sin puesto asignado</option>
                            @foreach($this->pollingPlaces as $pollingPlace)
                                <option value="{{ $pollingPlace->id }}">
                                    {{ $pollingPlace->name }}
                                </option>
                            @endforeach
                        </flux:select>

                        <flux:input
                            wire:model.blur="polling_table_number"
                            label="Número de mesa"
                            type="number"
                            inputmode="numeric"
                            min="1"
                            :disabled="!$polling_place_id"
                        />
                    </div>

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

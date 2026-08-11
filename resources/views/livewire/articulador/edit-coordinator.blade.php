<?php

use App\Enums\UserRole;
use App\Models\Municipality;
use App\Models\Neighborhood;
use App\Models\User;
use App\Services\CampaignContext;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

use function Livewire\Volt\layout;

layout('components.layouts::app', ['title' => 'Editar Coordinador']);

new class extends Component
{
    public User $coordinator;

    #[Validate('required|string|max:255')]
    public string $name = '';

    public string $email = '';

    public string $document_number = '';

    #[Validate('nullable|date')]
    public ?string $birth_date = null;

    #[Validate('required|string|max:20')]
    public string $phone = '';

    #[Validate('nullable|string|max:20')]
    public string $secondary_phone = '';

    #[Validate('nullable|string|max:500')]
    public string $address = '';

    #[Validate('required|exists:municipalities,id')]
    public ?int $municipality_id = null;

    #[Validate('nullable|exists:neighborhoods,id')]
    public ?int $neighborhood_id = null;

    #[Validate('nullable|string|min:8')]
    public ?string $password = null;

    public function mount(User $coordinator): void
    {
        if (! $coordinator->hasRole(UserRole::COORDINATOR->value)) {
            abort(404);
        }

        abort_unless(auth()->user()->can('update', $coordinator), 403);

        $this->coordinator = $coordinator;
        $this->name = $coordinator->name;
        $this->email = $coordinator->email;
        $this->document_number = $coordinator->document_number;
        $this->birth_date = $coordinator->birth_date?->format('Y-m-d');
        $this->phone = $coordinator->phone;
        $this->secondary_phone = $coordinator->secondary_phone ?? '';
        $this->address = $coordinator->address ?? '';
        $this->municipality_id = $coordinator->municipality_id;
        $this->neighborhood_id = $coordinator->neighborhood_id;
    }

    public function getMunicipalitiesProperty()
    {
        $campaign = CampaignContext::currentCampaign();

        if ($campaign?->municipality_id) {
            return Municipality::where('id', $campaign->municipality_id)->orderBy('name')->get();
        }

        if ($campaign?->department_id) {
            return Municipality::where('department_id', $campaign->department_id)->orderBy('name')->get();
        }

        return Municipality::orderBy('name')->get();
    }

    public function getNeighborhoodsProperty()
    {
        if (! $this->municipality_id) {
            return collect();
        }

        return Neighborhood::where('municipality_id', $this->municipality_id)->orderBy('name')->get();
    }

    public function updatedMunicipalityId(): void
    {
        $this->neighborhood_id = null;
    }

    public function save(): void
    {
        $this->validate([
            'email' => 'required|email|unique:users,email,' . $this->coordinator->id,
            'document_number' => 'required|string|max:50|unique:users,document_number,' . $this->coordinator->id,
        ]);

        $this->coordinator->update([
            'name' => $this->name,
            'email' => $this->email,
            'document_number' => $this->document_number,
            'birth_date' => $this->birth_date,
            'phone' => $this->phone,
            'secondary_phone' => $this->secondary_phone,
            'address' => $this->address,
            'municipality_id' => $this->municipality_id,
            'neighborhood_id' => $this->neighborhood_id,
            'password' => filled($this->password) ? Hash::make($this->password) : $this->coordinator->password,
        ]);

        session()->flash('success', 'Coordinador actualizado exitosamente.');

        $this->redirect(route('articulador.coordinadores'), navigate: true);
    }
}; ?>

<div class="mx-auto max-w-2xl space-y-6 p-6">
    <div>
        <flux:heading size="xl">Editar Coordinador</flux:heading>
        <flux:subheading>Actualiza la información del coordinador</flux:subheading>
    </div>

    @if (session('success'))
        <div class="rounded-xl bg-green-50 p-4 dark:bg-green-900/20">
            <div class="flex items-center gap-3">
                <div class="rounded-full bg-green-100 p-2 dark:bg-green-900/50">
                    <flux:icon.check-circle class="h-5 w-5 text-green-600 dark:text-green-400" />
                </div>
                <flux:text class="text-green-900 dark:text-green-100">{{ session('success') }}</flux:text>
            </div>
        </div>
    @endif

    <form wire:submit="save" class="space-y-4">
        <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-4">Información Personal</flux:heading>

            <div class="space-y-4">
                <flux:input
                    wire:model.blur="name"
                    label="Nombre Completo *"
                    type="text"
                    autocomplete="name"
                />

                <flux:input
                    wire:model.blur="document_number"
                    label="Número de Documento *"
                    type="text"
                    inputmode="numeric"
                    pattern="[0-9]*"
                />

                <flux:input
                    wire:model="birth_date"
                    label="Fecha de Nacimiento"
                    type="date"
                />
            </div>
        </div>

        <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-4">Contacto</flux:heading>

            <div class="space-y-4">
                <flux:input
                    wire:model.blur="email"
                    label="Correo Electrónico *"
                    type="email"
                    autocomplete="email"
                />

                <flux:input
                    wire:model.blur="phone"
                    label="Teléfono Principal *"
                    type="tel"
                />

                <flux:input
                    wire:model.blur="secondary_phone"
                    label="Teléfono Secundario"
                    type="tel"
                />

                <flux:textarea
                    wire:model.blur="address"
                    label="Dirección"
                    rows="2"
                />
            </div>
        </div>

        <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-4">Ubicación</flux:heading>

            <div class="space-y-4">
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
                    wire:model="neighborhood_id"
                    label="Barrio (opcional)"
                    placeholder="Selecciona un barrio"
                    :disabled="! $municipality_id"
                >
                    @foreach($this->neighborhoods as $neighborhood)
                        <option value="{{ $neighborhood->id }}">{{ $neighborhood->name }}</option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-4">Acceso</flux:heading>

            <div class="space-y-4">
                <flux:input
                    wire:model.blur="password"
                    label="Contraseña (opcional)"
                    type="password"
                    placeholder="Dejar en blanco para no cambiar"
                    autocomplete="new-password"
                />
            </div>
        </div>

        <div class="flex gap-3">
            <flux:button type="submit" variant="primary" class="flex-1">
                Guardar cambios
            </flux:button>

            <flux:button
                type="button"
                variant="ghost"
                :href="route('articulador.coordinadores')"
                wire:navigate
                class="flex-1"
            >
                Cancelar
            </flux:button>
        </div>
    </form>
    <livewire:metadata-assignment-panel :user="$coordinator" wire:key="metadata-panel-{{ $coordinator->id }}" />
</div>

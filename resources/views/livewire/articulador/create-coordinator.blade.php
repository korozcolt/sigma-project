<?php

use App\Enums\UserRole;
use App\Models\Municipality;
use App\Models\Neighborhood;
use App\Models\User;
use App\Services\CampaignContext;
use App\Services\IdentityLookupService;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

use function Livewire\Volt\layout;

layout('components.layouts::app', ['title' => 'Agregar Coordinador']);

new class extends Component
{
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|email|unique:users,email')]
    public string $email = '';

    #[Validate('required|string|min:8')]
    public string $password = '';

    #[Validate('required|string|max:50|unique:users,document_number')]
    public string $document_number = '';

    #[Validate('required|string|max:20')]
    public string $phone = '';

    #[Validate('nullable|string|max:20')]
    public string $secondary_phone = '';

    #[Validate('nullable|string|max:500')]
    public string $address = '';

    public ?string $birth_date = null;

    #[Validate('required|exists:municipalities,id')]
    public ?int $municipality_id = null;

    #[Validate('nullable|exists:neighborhoods,id')]
    public ?int $neighborhood_id = null;

    public bool $nameLocked = false;

    public ?int $fixedMunicipalityId = null;

    public function mount(): void
    {
        $campaign = CampaignContext::currentCampaign();

        if ($campaign?->municipality_id) {
            $this->fixedMunicipalityId = $campaign->municipality_id;
            $this->municipality_id = $campaign->municipality_id;
        }
    }

    protected function rules(): array
    {
        return [
            'birth_date' => 'nullable|date|before_or_equal:'.now()->subYears(18)->toDateString(),
        ];
    }

    public function updatedDocumentNumber(): void
    {
        $this->nameLocked = false;

        if (blank($this->document_number)) {
            return;
        }

        $identity = app(IdentityLookupService::class)->findByDocumentNumber($this->document_number);

        if ($identity) {
            $this->name = preg_replace('/\s+/', ' ', trim("{$identity->nombre1} {$identity->nombre2} {$identity->apellido1} {$identity->apellido2}"));
            $this->nameLocked = true;
        }
    }

    public function unlockName(): void
    {
        $this->nameLocked = false;
    }

    public function updatedMunicipalityId(): void
    {
        $this->neighborhood_id = null;
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

    public function save(): void
    {
        $this->validate();

        $areaCoordinatorUserId = auth()->user()->hasRole(UserRole::AREA_COORDINATOR->value)
            ? auth()->id()
            : null;

        $coordinator = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'phone' => $this->phone,
            'secondary_phone' => $this->secondary_phone,
            'document_number' => $this->document_number,
            'birth_date' => $this->birth_date,
            'address' => $this->address,
            'municipality_id' => $this->municipality_id,
            'neighborhood_id' => $this->neighborhood_id,
            'area_coordinator_user_id' => $areaCoordinatorUserId,
            'email_verified_at' => now(),
        ]);

        $coordinator->assignRole(UserRole::COORDINATOR->value);

        $campaignIds = auth()->user()->campaigns()->pluck('campaigns.id');
        $coordinator->campaigns()->attach($campaignIds);

        session()->flash('success', '¡Coordinador creado exitosamente!');

        $this->redirect(route('articulador.coordinadores'), navigate: true);
    }
}; ?>

<div class="mx-auto max-w-2xl space-y-6 p-6">
    <!-- Header -->
    <div>
        <flux:heading size="xl">Agregar Nuevo Coordinador</flux:heading>
        <flux:subheading>Crea un nuevo coordinador para tu equipo</flux:subheading>
    </div>

    <!-- Success Message -->
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

    <!-- Form -->
    <form wire:submit="save" class="space-y-4">
        <!-- Información Personal -->
        <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-4">Información Personal</flux:heading>

            <div class="space-y-4">
                <flux:input
                    wire:model.blur="name"
                    label="Nombre Completo *"
                    type="text"
                    placeholder="Juan Carlos Pérez"
                    autocomplete="name"
                    :disabled="$nameLocked"
                />

                @if($nameLocked)
                    <flux:button type="button" variant="ghost" size="sm" wire:click="unlockName">
                        ¿Nombre incorrecto? Editar manualmente
                    </flux:button>
                @endif

                <flux:input
                    wire:model.blur="document_number"
                    label="Número de Documento *"
                    type="text"
                    inputmode="numeric"
                    pattern="[0-9]*"
                    placeholder="1234567890"
                />

                <flux:input
                    wire:model="birth_date"
                    label="Fecha de Nacimiento"
                    type="date"
                />

                <flux:input
                    wire:model.blur="email"
                    label="Correo Electrónico *"
                    type="email"
                    placeholder="juan@ejemplo.com"
                    autocomplete="email"
                />
            </div>
        </div>

        <!-- Contacto -->
        <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-4">Contacto</flux:heading>

            <div class="space-y-4">
                <flux:input
                    wire:model.blur="phone"
                    label="Teléfono Principal *"
                    type="tel"
                    placeholder="3001234567"
                />

                <flux:input
                    wire:model.blur="secondary_phone"
                    label="Teléfono Secundario"
                    type="tel"
                    placeholder="3009876543"
                />

                <flux:textarea
                    wire:model.blur="address"
                    label="Dirección"
                    placeholder="Calle 123 # 45-67"
                />
            </div>
        </div>

        <!-- Ubicación -->
        <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-4">Ubicación</flux:heading>

            <div class="space-y-4">
                <flux:select
                    wire:model.live="municipality_id"
                    label="Municipio *"
                    placeholder="Selecciona un municipio"
                    :disabled="$fixedMunicipalityId !== null"
                >
                    @foreach($this->municipalities as $municipality)
                        <option value="{{ $municipality->id }}">{{ $municipality->name }}</option>
                    @endforeach
                </flux:select>

                @if($fixedMunicipalityId !== null)
                    <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                        Fijado por la campaña activa.
                    </flux:text>
                @endif

                <flux:select
                    wire:model="neighborhood_id"
                    label="Barrio (Opcional)"
                    placeholder="Selecciona un barrio"
                    :disabled="! $municipality_id"
                >
                    @foreach($this->neighborhoods as $neighborhood)
                        <option value="{{ $neighborhood->id }}">{{ $neighborhood->name }}</option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        <!-- Acceso -->
        <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-4">Acceso</flux:heading>

            <div class="space-y-4">
                <flux:input
                    wire:model.blur="password"
                    label="Contraseña *"
                    type="password"
                    placeholder="Mínimo 8 caracteres"
                    autocomplete="new-password"
                />

                <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                    El coordinador recibirá estas credenciales para acceder al sistema
                </flux:text>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex gap-3">
            <flux:button
                type="submit"
                variant="primary"
                class="flex-1"
            >
                Crear Coordinador
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
</div>

<?php

use App\Models\Neighborhood;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use function Livewire\Volt\{layout, state};

layout('components.layouts::app', ['title' => 'Agregar Líder']);

new class extends Component {
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|email|unique:users,email')]
    public string $email = '';

    #[Validate('required|string|min:8')]
    public string $password = '';

    #[Validate('nullable|exists:neighborhoods,id')]
    public ?int $neighborhood_id = null;

    public function getNeighborhoodsProperty()
    {
        $municipalityId = auth()->user()->municipality_id;

        if (! $municipalityId) {
            return collect();
        }

        return Neighborhood::where('municipality_id', $municipalityId)
            ->orderBy('name')
            ->get();
    }

    public function save(): void
    {
        $this->validate();

        $coordinatorUser = auth()->user();
        $campaignIds = $coordinatorUser->campaigns()->pluck('campaigns.id');

        // Crear el usuario líder
        $leader = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'municipality_id' => $coordinatorUser->municipality_id,
            'neighborhood_id' => $this->neighborhood_id,
            'email_verified_at' => now(), // Auto-verificar
        ]);

        // Asignar rol de líder
        $leader->assignRole('leader');

        // Asignar a las mismas campañas del coordinador
        $leader->campaigns()->attach($campaignIds);

        session()->flash('success', '¡Líder creado exitosamente!');

        $this->redirect(route('coordinator.leaders'), navigate: true);
    }
}; ?>

<div class="mx-auto max-w-2xl space-y-6 p-6">
    <!-- Header -->
    <div>
        <flux:heading size="xl">Agregar Nuevo Líder</flux:heading>
        <flux:subheading>Crea un nuevo líder para tu equipo</flux:subheading>
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
                />

                <flux:input
                    wire:model.blur="email"
                    label="Correo Electrónico *"
                    type="email"
                    placeholder="juan@ejemplo.com"
                    autocomplete="email"
                />

                <flux:input
                    wire:model.blur="password"
                    label="Contraseña *"
                    type="password"
                    placeholder="Mínimo 8 caracteres"
                    autocomplete="new-password"
                />

                <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                    El líder recibirá estas credenciales para acceder al sistema
                </flux:text>
            </div>
        </div>

        <!-- Ubicación -->
        <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-4">Ubicación</flux:heading>

            <div class="space-y-4">
                <flux:field>
                    <flux:label>Municipio</flux:label>
                    <flux:input
                        value="{{ auth()->user()->municipality->name ?? 'Sin municipio' }}"
                        disabled
                        readonly
                    />
                    <flux:description>El líder será asignado a tu municipio</flux:description>
                </flux:field>

                <flux:select
                    wire:model="neighborhood_id"
                    label="Barrio (Opcional)"
                    placeholder="Selecciona un barrio"
                >
                    @foreach($this->neighborhoods as $neighborhood)
                        <option value="{{ $neighborhood->id }}">{{ $neighborhood->name }}</option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        <!-- Campañas -->
        <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-2">Campañas</flux:heading>
            <flux:text class="text-zinc-500 dark:text-zinc-400">
                El líder será asignado automáticamente a las mismas campañas que tú coordinas
            </flux:text>
            <div class="mt-3 space-y-2">
                @foreach(auth()->user()->campaigns as $campaign)
                    <div class="flex items-center gap-2 rounded-lg border border-zinc-200 p-2 dark:border-zinc-700">
                        <flux:icon.check-circle class="h-5 w-5 text-green-600" />
                        <flux:text class="font-medium">{{ $campaign->name }}</flux:text>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Actions -->
        <div class="flex gap-3">
            <flux:button
                type="submit"
                variant="primary"
                class="flex-1"
            >
                Crear Líder
            </flux:button>

            <flux:button
                type="button"
                variant="ghost"
                :href="route('coordinator.leaders')"
                wire:navigate
                class="flex-1"
            >
                Cancelar
            </flux:button>
        </div>
    </form>
</div>

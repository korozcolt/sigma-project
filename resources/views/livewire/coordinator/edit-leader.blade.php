<?php

use App\Enums\UserRole;
use App\Models\Neighborhood;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use function Livewire\Volt\{layout, state};

layout('components.layouts::app', ['title' => 'Editar Líder']);

new class extends Component {
    public User $leader;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|email|unique:users,email')]
    public string $email = '';

    #[Validate('nullable|string|min:8')]
    public ?string $password = null;

    #[Validate('nullable|exists:neighborhoods,id')]
    public ?int $neighborhood_id = null;

    #[Validate('required|exists:users,id')]
    public int $coordinator_user_id;

    public function mount(User $leader): void
    {
        if (! $leader->hasRole(UserRole::LEADER->value)) {
            abort(404);
        }

        $user = auth()->user();

        if ($user->hasRole(UserRole::COORDINATOR->value) && $leader->coordinator_user_id !== $user->id) {
            abort(403);
        }

        $this->leader = $leader;
        $this->name = $leader->name;
        $this->email = $leader->email;
        $this->neighborhood_id = $leader->neighborhood_id;
        $this->coordinator_user_id = $leader->coordinator_user_id ?? ($user->hasRole(UserRole::COORDINATOR->value) ? $user->id : 0);
    }

    public function getCoordinatorsProperty()
    {
        if (auth()->user()->hasRole(UserRole::COORDINATOR->value)) {
            return collect();
        }

        return User::role(UserRole::COORDINATOR->value)->orderBy('name')->get();
    }

    public function getCoordinatorProperty(): ?User
    {
        return User::query()
            ->whereKey($this->coordinator_user_id)
            ->whereHas('roles', fn ($q) => $q->where('name', UserRole::COORDINATOR->value))
            ->first();
    }

    public function getNeighborhoodsProperty()
    {
        $municipalityId = $this->coordinator?->municipality_id;

        if (! $municipalityId) {
            return collect();
        }

        return Neighborhood::where('municipality_id', $municipalityId)
            ->orderBy('name')
            ->get();
    }

    public function save(): void
    {
        if (auth()->user()->hasRole(UserRole::COORDINATOR->value)) {
            $this->coordinator_user_id = auth()->id();
        }

        $this->validate([
            'email' => 'required|email|unique:users,email,' . $this->leader->id,
        ]);

        $coordinator = $this->coordinator;

        if (! $coordinator) {
            $this->addError('coordinator_user_id', 'Debes seleccionar un coordinador válido.');
            return;
        }

        $this->leader->update([
            'name' => $this->name,
            'email' => $this->email,
            'municipality_id' => $coordinator->municipality_id,
            'coordinator_user_id' => $coordinator->id,
            'neighborhood_id' => $this->neighborhood_id,
            'password' => filled($this->password) ? Hash::make($this->password) : $this->leader->password,
        ]);

        $campaignIds = $coordinator->campaigns()->pluck('campaigns.id');
        $this->leader->campaigns()->sync($campaignIds);

        session()->flash('success', 'Líder actualizado exitosamente.');

        $this->redirect(route('coordinator.leaders'), navigate: true);
    }
}; ?>

<div class="mx-auto max-w-2xl space-y-6 p-6">
    <div>
        <flux:heading size="xl">Editar Líder</flux:heading>
        <flux:subheading>Actualiza la información del líder</flux:subheading>
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
        @if(!auth()->user()->hasRole(\App\Enums\UserRole::COORDINATOR->value))
            <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
                <flux:heading size="lg" class="mb-4">Coordinador</flux:heading>

                <flux:select
                    wire:model="coordinator_user_id"
                    label="Coordinador *"
                    placeholder="Selecciona un coordinador"
                >
                    @foreach($this->coordinators as $coordinator)
                        <option value="{{ $coordinator->id }}">{{ $coordinator->name }}</option>
                    @endforeach
                </flux:select>
            </div>
        @endif

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
                    wire:model.blur="email"
                    label="Correo Electrónico *"
                    type="email"
                    autocomplete="email"
                />

                <flux:input
                    wire:model.blur="password"
                    label="Contraseña (opcional)"
                    type="password"
                    placeholder="Dejar en blanco para no cambiar"
                    autocomplete="new-password"
                />
            </div>
        </div>

        <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-4">Ubicación</flux:heading>

            <div class="space-y-4">
                <flux:field>
                    <flux:label>Municipio</flux:label>
                    <flux:input
                        value="{{ $this->coordinator?->municipality?->name ?? 'Sin municipio' }}"
                        disabled
                        readonly
                    />
                    <flux:description>El líder será asignado al municipio del coordinador</flux:description>
                </flux:field>

                <flux:select
                    wire:model="neighborhood_id"
                    label="Barrio (opcional)"
                    placeholder="Selecciona un barrio"
                >
                    @foreach($this->neighborhoods as $neighborhood)
                        <option value="{{ $neighborhood->id }}">{{ $neighborhood->name }}</option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        <div class="flex gap-3">
            <flux:button type="submit" variant="primary" class="flex-1">
                Guardar cambios
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


<?php

use App\Enums\UserRole;
use App\Models\Neighborhood;
use App\Models\User;
use App\Services\CampaignContext;
use App\Services\OtpVerificationService;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

use function Livewire\Volt\layout;

layout('components.layouts::app', ['title' => 'Agregar Líder']);

new class extends Component
{
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|email|unique:users,email')]
    public string $email = '';

    #[Validate('required|string|min:8')]
    public string $password = '';

    #[Validate('required|string|min:10')]
    public string $phone = '';

    public bool $otpSent = false;

    public bool $otpVerified = false;

    public string $otp_code = '';

    #[Validate('nullable|exists:neighborhoods,id')]
    public ?int $neighborhood_id = null;

    #[Validate('required|exists:users,id')]
    public ?int $coordinator_user_id = null;

    public function mount(): void
    {
        if (auth()->user()->hasRole(UserRole::COORDINATOR->value)) {
            $this->coordinator_user_id = auth()->id();
        }
    }

    private function resolveActiveCampaign()
    {
        return $this->getCoordinatorUser()?->campaigns()->first() ?? CampaignContext::currentCampaign();
    }

    public function sendOtp(): void
    {
        $this->validateOnly('phone');

        $campaign = $this->resolveActiveCampaign();

        app(OtpVerificationService::class)->generate($this->phone, $campaign);

        $this->otpSent = true;

        session()->flash('otp_sent', 'Código enviado por SMS');
    }

    public function verifyOtp(): void
    {
        $campaign = $this->resolveActiveCampaign();

        $verified = app(OtpVerificationService::class)->verify($this->phone, $campaign, $this->otp_code);

        if ($verified) {
            $this->otpVerified = true;

            return;
        }

        $this->addError('otp_code', 'Código incorrecto o expirado.');
    }

    public function getNeighborhoodsProperty()
    {
        $coordinator = $this->getCoordinatorUser();
        $municipalityId = $coordinator?->municipality_id;

        if (! $municipalityId) {
            return collect();
        }

        return Neighborhood::where('municipality_id', $municipalityId)
            ->orderBy('name')
            ->get();
    }

    public function getCoordinatorsProperty()
    {
        if (auth()->user()->hasRole(UserRole::COORDINATOR->value)) {
            return collect();
        }

        return User::role(UserRole::COORDINATOR->value)->orderBy('name')->get();
    }

    private function getCoordinatorUser(): ?User
    {
        if (! $this->coordinator_user_id) {
            return null;
        }

        return User::query()
            ->whereKey($this->coordinator_user_id)
            ->whereHas('roles', fn ($q) => $q->where('name', UserRole::COORDINATOR->value))
            ->first();
    }

    public function save(): void
    {
        if (! $this->otpVerified) {
            $this->addError('otp_code', 'Debes verificar el teléfono del líder antes de continuar.');

            return;
        }

        if (auth()->user()->hasRole(UserRole::COORDINATOR->value)) {
            $this->coordinator_user_id = auth()->id();
        }

        $this->validate();

        $coordinatorUser = $this->getCoordinatorUser();

        if (! $coordinatorUser) {
            $this->addError('coordinator_user_id', 'Debes seleccionar un coordinador válido.');

            return;
        }

        $campaignIds = $coordinatorUser->campaigns()->pluck('campaigns.id');

        // Crear el usuario líder
        $leader = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'phone' => $this->phone,
            'municipality_id' => $coordinatorUser->municipality_id,
            'coordinator_user_id' => $coordinatorUser->id,
            'neighborhood_id' => $this->neighborhood_id,
            'email_verified_at' => now(), // Auto-verificar
        ]);

        // Asignar rol de líder
        $leader->assignRole(UserRole::LEADER->value);

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

        <!-- Verificación de Teléfono -->
        <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-4">Verificación de Teléfono</flux:heading>

            @if (session('otp_sent'))
                <flux:text class="mb-4 text-green-700 dark:text-green-400">{{ session('otp_sent') }}</flux:text>
            @endif

            <div class="space-y-4">
                <flux:input
                    wire:model.blur="phone"
                    label="Teléfono *"
                    type="tel"
                    placeholder="3001234567"
                    :disabled="$otpVerified"
                />

                @if (! $otpSent)
                    <flux:button type="button" variant="primary" wire:click="sendOtp">
                        Enviar código
                    </flux:button>
                @endif

                @if ($otpSent && ! $otpVerified)
                    <flux:input
                        wire:model="otp_code"
                        label="Código de verificación *"
                        type="text"
                        placeholder="123456"
                    />

                    <flux:button type="button" variant="primary" wire:click="verifyOtp">
                        Verificar
                    </flux:button>
                @endif

                @if ($otpVerified)
                    <div class="flex items-center gap-2">
                        <flux:icon.check-circle class="h-5 w-5 text-green-600" />
                        <flux:text class="font-medium text-green-700 dark:text-green-400">Teléfono verificado</flux:text>
                    </div>
                @endif
            </div>
        </div>

        <!-- Ubicación -->
        <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-4">Ubicación</flux:heading>

            <div class="space-y-4">
                <flux:field>
                    <flux:label>Municipio</flux:label>
                    <flux:input
                        value="{{ $this->getCoordinatorUser()?->municipality?->name ?? 'Sin municipio' }}"
                        disabled
                        readonly
                    />
                    <flux:description>El líder será asignado al municipio del coordinador</flux:description>
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
                El líder será asignado automáticamente a las mismas campañas del coordinador
            </flux:text>
            <div class="mt-3 space-y-2">
                @foreach($this->getCoordinatorUser()?->campaigns ?? [] as $campaign)
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
                :disabled="! $otpVerified"
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

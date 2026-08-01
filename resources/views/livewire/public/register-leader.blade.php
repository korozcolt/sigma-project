<?php

use App\Enums\UserRole;
use App\Models\Invitation;
use App\Models\Neighborhood;
use App\Models\RegistraduriaLookup;
use App\Models\User;
use App\Services\IdentityLookupService;
use App\Services\InvitationService;
use App\Services\OtpVerificationService;
use App\Services\VoterValidationService;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.public', ['title' => 'Registro de líder'])] class extends Component
{
    public Invitation $invitation;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|email|unique:users,email')]
    public string $email = '';

    #[Validate('required|string|min:8')]
    public string $password = '';

    #[Validate('required|string|min:10')]
    public string $phone = '';

    #[Validate('required|string|max:50|unique:users,document_number')]
    public string $document_number = '';

    public bool $nameLocked = false;

    public bool $otpSent = false;

    public bool $otpVerified = false;

    public string $otp_code = '';

    public bool $registraduriaVerified = false;

    public bool $censusNotFoundWarning = false;

    #[Validate('nullable|exists:neighborhoods,id')]
    public ?int $neighborhood_id = null;

    public bool $registrationComplete = false;

    public function mount(string $token): void
    {
        $invitation = app(InvitationService::class)->validateLeaderInvitation($token);

        if (! $invitation) {
            session()->flash('error', 'El enlace de registro no es válido, ya expiró o ya fue utilizado.');
            $this->redirect(route('home'));

            return;
        }

        $this->invitation = $invitation->loadMissing('coordinator.municipality');
    }

    public function updatedDocumentNumber(): void
    {
        $this->registraduriaVerified = false;
        $this->censusNotFoundWarning = false;
        $this->nameLocked = false;

        if (blank($this->document_number)) {
            return;
        }

        $identity = app(IdentityLookupService::class)->findByDocumentNumber($this->document_number);

        if ($identity) {
            $this->name = preg_replace('/\s+/', ' ', trim("{$identity->nombre1} {$identity->nombre2} {$identity->apellido1} {$identity->apellido2}"));
            $this->nameLocked = true;
        }

        if (RegistraduriaLookup::query()->where('document_number', $this->document_number)->exists()) {
            $this->registraduriaVerified = true;

            return;
        }

        $campaign = $this->resolveActiveCampaign();

        if (! $campaign) {
            return;
        }

        $this->censusNotFoundWarning = ! app(VoterValidationService::class)
            ->documentExistsInCensus($campaign->id, $this->document_number);
    }

    public function unlockName(): void
    {
        $this->nameLocked = false;
    }

    private function resolveActiveCampaign()
    {
        return $this->invitation->coordinator->campaigns()->first();
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
        $municipalityId = $this->invitation->coordinator->municipality_id;

        if (! $municipalityId) {
            return collect();
        }

        return Neighborhood::where('municipality_id', $municipalityId)
            ->orderBy('name')
            ->get();
    }

    public function save(): void
    {
        if (! $this->otpVerified) {
            $this->addError('otp_code', 'Debes verificar tu teléfono antes de continuar.');

            return;
        }

        $this->validate();

        $coordinator = $this->invitation->coordinator;

        $campaignIds = $coordinator->campaigns()->pluck('campaigns.id');

        $leader = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'phone' => $this->phone,
            'document_number' => $this->document_number,
            'municipality_id' => $coordinator->municipality_id,
            'coordinator_user_id' => $coordinator->id,
            'neighborhood_id' => $this->neighborhood_id,
            'email_verified_at' => now(),
        ]);

        $leader->assignRole(UserRole::LEADER->value);

        $leader->campaigns()->attach($campaignIds);

        app(InvitationService::class)->markLeaderInvitationAccepted($this->invitation, $leader);

        $this->registrationComplete = true;
    }
}; ?>

<div class="relative overflow-hidden bg-gray-50 dark:bg-gray-950">
    <div class="absolute inset-0 bg-gradient-to-b from-gray-100 to-transparent dark:from-white/5"></div>

    <div class="relative mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:px-8">
        <div class="mb-8 flex items-center justify-center">
            <img
                src="{{ asset('images/logo-sigma_small.webp') }}"
                alt="{{ config('app.name') }}"
                class="h-12 w-auto"
                loading="eager"
            />
        </div>

        <div class="flex flex-col items-center gap-3 text-center">
            <div class="max-w-2xl">
                <h1 class="text-balance text-2xl font-semibold tracking-tight text-gray-900 dark:text-white sm:text-3xl">
                    Registro de líder
                </h1>
                <p class="mt-2 text-pretty text-sm text-gray-600 dark:text-gray-300 sm:text-base">
                    Fuiste invitado por <span class="font-medium">{{ $invitation->coordinator?->name }}</span> a unirte como líder.
                </p>
            </div>
        </div>

        @if ($registrationComplete)
            <div class="mt-8 rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-gray-200 dark:bg-zinc-900 dark:ring-white/10">
                <flux:icon.check-circle class="mx-auto h-12 w-12 text-green-600 dark:text-green-400" />
                <flux:heading size="lg" class="mt-4">¡Tu cuenta fue creada!</flux:heading>
                <flux:text class="mt-2 text-zinc-500 dark:text-zinc-400">Ya puedes iniciar sesión con tu correo y contraseña.</flux:text>
                <flux:button variant="primary" class="mt-6" :href="route('login')" wire:navigate>
                    Iniciar sesión
                </flux:button>
            </div>
        @else
            <div class="mt-8 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-zinc-900 dark:ring-white/10 sm:p-8">
                <form wire:submit="save" class="space-y-6">
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

                        <div wire:key="document-status-banner">
                            @if($registraduriaVerified)
                                <div class="flex items-start gap-2 rounded-lg bg-green-50 p-3 text-sm text-green-800 dark:bg-green-900/20 dark:text-green-300">
                                    <flux:icon.check-badge class="mt-0.5 h-4 w-4 shrink-0" />
                                    <span>Verificado por Registraduría.</span>
                                </div>
                            @elseif($censusNotFoundWarning)
                                <div class="flex items-start gap-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
                                    <flux:icon.exclamation-triangle class="mt-0.5 h-4 w-4 shrink-0" />
                                    <span>Esta cédula no aparece en el censo actual, revísala.</span>
                                </div>
                            @endif
                        </div>

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
                    </div>

                    <div class="space-y-4">
                        <flux:heading size="lg">Verificación de Teléfono</flux:heading>

                        @if (session('otp_sent'))
                            <flux:text class="text-green-700 dark:text-green-400">{{ session('otp_sent') }}</flux:text>
                        @endif

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

                    <div class="space-y-4">
                        <flux:heading size="lg">Ubicación</flux:heading>

                        <flux:field>
                            <flux:label>Municipio</flux:label>
                            <flux:input
                                value="{{ $invitation->coordinator?->municipality?->name ?? 'Sin municipio' }}"
                                disabled
                                readonly
                            />
                            <flux:description>Serás asignado al municipio de tu coordinador</flux:description>
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

                    <flux:button
                        type="submit"
                        variant="primary"
                        class="w-full"
                        :disabled="! $otpVerified"
                    >
                        Crear mi cuenta
                    </flux:button>
                </form>
            </div>
        @endif
    </div>
</div>

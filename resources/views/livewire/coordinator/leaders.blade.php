<?php

use App\Enums\UserRole;
use App\Models\MetadataKey;
use App\Models\User;
use App\Models\Voter;
use App\Services\InvitationService;
use App\Services\MetadataAssignmentService;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use function Livewire\Volt\{layout, with};

layout('components.layouts::app', ['title' => 'Gestión de Líderes']);

new class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'coordinator')]
    public ?int $coordinatorUserId = null;

    public bool $showLeaderInvitationModal = false;

    public ?string $leaderInvitationUrl = null;

    /** @var array<int, string> */
    public array $selectedLeaderIds = [];

    public bool $selectAllOnPage = false;

    public bool $showBulkMetadataModal = false;

    public ?int $bulkMetadataKeyId = null;

    public string $bulkMetadataValue = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->selectedLeaderIds = [];
        $this->selectAllOnPage = false;
    }

    public function updatedSelectAllOnPage(bool $value): void
    {
        $pageIds = $this->leadersQuery()->latest()->paginate(15)->pluck('id')->map(fn ($id) => (string) $id)->all();

        $this->selectedLeaderIds = $value ? $pageIds : [];
    }

    public function getMetadataKeysProperty()
    {
        return app(MetadataAssignmentService::class)->activeKeys();
    }

    public function getSelectedMetadataKeyProperty(): ?MetadataKey
    {
        return app(MetadataAssignmentService::class)->findActiveKey($this->bulkMetadataKeyId);
    }

    public function updatedBulkMetadataKeyId(): void
    {
        $this->bulkMetadataValue = '';
        $this->resetErrorBag('bulkMetadataValue');
    }

    public function assignBulkMetadata(): void
    {
        $service = app(MetadataAssignmentService::class);
        $actor = auth()->user();

        $this->validate([
            'bulkMetadataKeyId' => ['required', 'integer', 'exists:metadata_keys,id'],
            'bulkMetadataValue' => ['required', 'string', 'max:255'],
            'selectedLeaderIds' => ['required', 'array', 'min:1'],
        ]);

        $key = $service->findActiveKey($this->bulkMetadataKeyId);

        if (! $key) {
            $this->addError('bulkMetadataKeyId', 'La llave seleccionada no está disponible.');

            return;
        }

        // Los ids llegan del navegador y no son de confianza: se re-filtran contra
        // los subordinados directos del actor antes de escribir nada.
        $targets = $service->subordinatesByIds($actor, $this->selectedLeaderIds);

        if ($targets->isEmpty()) {
            $this->addError('selectedLeaderIds', 'No hay líderes válidos seleccionados.');

            return;
        }

        try {
            $assigned = $service->assignMany($targets, $key, $this->bulkMetadataValue, $actor);
        } catch (\InvalidArgumentException $e) {
            $this->addError('bulkMetadataValue', $e->getMessage());

            return;
        }

        $this->reset(['selectedLeaderIds', 'selectAllOnPage', 'showBulkMetadataModal', 'bulkMetadataKeyId', 'bulkMetadataValue']);

        session()->flash('success', "Metadata asignada a {$assigned} líder(es).");
    }

    public function generateLeaderInvitationLink(): void
    {
        abort_unless(auth()->user()->hasRole(UserRole::COORDINATOR->value), 403);

        $invitation = app(InvitationService::class)->createLeaderRegistrationLink(auth()->user());

        $this->leaderInvitationUrl = $invitation->getLeaderRegistrationUrl();
        $this->showLeaderInvitationModal = true;
    }

    public function getCoordinatorsProperty()
    {
        if (auth()->user()->hasRole(UserRole::COORDINATOR->value)) {
            return collect();
        }

        return User::role(UserRole::COORDINATOR->value)->orderBy('name')->get();
    }

    public function becomeMyOwnLeader(): void
    {
        $user = auth()->user();

        if (! $user->hasRole(UserRole::COORDINATOR->value)) {
            abort(403);
        }

        if (! $user->hasRole(UserRole::LEADER->value)) {
            $user->assignRole(UserRole::LEADER->value);
        }

        $user->update(['coordinator_user_id' => $user->id]);

        session()->flash('success', 'Ahora apareces como líder en tu lista.');
    }

    protected function leadersQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $user = auth()->user();
        $query = User::role(UserRole::LEADER->value)->withCount(['registeredVoters as voters_count']);

        if ($user->hasRole(UserRole::COORDINATOR->value)) {
            $query->where('coordinator_user_id', $user->id);
        } elseif ($this->coordinatorUserId) {
            $query->where('coordinator_user_id', $this->coordinatorUserId);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%");
            });
        }

        return $query;
    }

    public function with(): array
    {
        $query = $this->leadersQuery();

        $leaders = $query->latest()->paginate(15);

        $totalLeaders = $query->clone()->count();

        $leaderIds = $query->clone()->pluck('id');
        $totalVoters = $leaderIds->isEmpty()
            ? 0
            : Voter::whereIn('registered_by', $leaderIds)->count();

        return [
            'leaders' => $leaders,
            'totalLeaders' => $totalLeaders,
            'totalVoters' => $totalVoters,
        ];
    }
}; ?>

<div class="mx-auto max-w-7xl space-y-6 p-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Gestión de Líderes</flux:heading>
            <flux:subheading>Administra tu equipo de líderes</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:button variant="outline" :href="route('coordinator.leaders.export')" icon="arrow-down-tray" data-testid="coordinator:export-leaders">
                Exportar Líderes
            </flux:button>
            <flux:button variant="outline" wire:click="generateLeaderInvitationLink" icon="link" data-testid="coordinator:generate-leader-link">
                Generar enlace de registro
            </flux:button>
            <flux:button variant="primary" :href="route('coordinator.leaders.create')" wire:navigate icon="user-plus">
                Agregar Líder
            </flux:button>
        </div>
    </div>

    @if ($showLeaderInvitationModal)
        <flux:modal wire:model="showLeaderInvitationModal">
            <div class="p-6" x-data="{ copied: false }">
                <flux:heading size="lg" class="mb-2">Enlace de registro de líder</flux:heading>
                <flux:text size="sm" class="mb-4 text-zinc-500 dark:text-zinc-400">
                    Comparte este enlace con la persona que quieres invitar como líder. Expira en 7 días y solo puede usarse una vez.
                </flux:text>

                <div class="flex items-center gap-2">
                    <flux:input
                        readonly
                        value="{{ $leaderInvitationUrl }}"
                        data-testid="coordinator:leader-invitation-url"
                        x-ref="leaderInvitationUrlInput"
                    />
                    <flux:button
                        type="button"
                        variant="primary"
                        x-on:click="navigator.clipboard.writeText($refs.leaderInvitationUrlInput.value); copied = true; setTimeout(() => copied = false, 2000)"
                    >
                        <span x-show="!copied">Copiar</span>
                        <span x-show="copied" x-cloak>¡Copiado!</span>
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

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

    <!-- Stats Summary -->
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-zinc-900">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Total Líderes</flux:text>
            <flux:heading size="xl" class="mt-1">{{ $totalLeaders }}</flux:heading>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-zinc-900">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Apoyos Registrados</flux:text>
            <flux:heading size="xl" class="mt-1">{{ number_format($totalVoters) }}</flux:heading>
        </div>
    </div>

    {{-- Self-promote action (secondary, separated from primary CTAs) --}}
    @if(auth()->user()->hasRole(\App\Enums\UserRole::COORDINATOR->value) && !auth()->user()->hasRole(\App\Enums\UserRole::LEADER->value))
        <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
            <div class="flex items-center justify-between">
                <div>
                    <flux:text class="font-medium text-zinc-700 dark:text-zinc-300">Registro personal</flux:text>
                    <flux:text size="sm" class="text-zinc-500">Regístrate como líder para aparecer en el listado y gestionar apoyos directamente.</flux:text>
                </div>
                <flux:button variant="outline" wire:click="becomeMyOwnLeader" icon="user" size="sm">
                    Ser mi propio líder
                </flux:button>
            </div>
        </div>
    @endif

    @if(!auth()->user()->hasRole(\App\Enums\UserRole::COORDINATOR->value))
        <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-zinc-900">
            <flux:select wire:model.live="coordinatorUserId" label="Filtrar por coordinador" placeholder="Todos los coordinadores">
                <option value="">Todos los coordinadores</option>
                @foreach($this->coordinators as $coordinator)
                    <option value="{{ $coordinator->id }}">{{ $coordinator->name }}</option>
                @endforeach
            </flux:select>
        </div>
    @endif

    <!-- Search -->
    <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-zinc-900">
        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="Buscar por nombre o email..."
            icon="magnifying-glass"
        />
    </div>

    @if (count($selectedLeaderIds) > 0)
        <div class="flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm dark:bg-zinc-900">
            <flux:text>{{ count($selectedLeaderIds) }} seleccionado(s)</flux:text>
            <flux:button variant="primary" icon="tag" wire:click="$set('showBulkMetadataModal', true)">
                Asignar metadata
            </flux:button>
            <flux:button variant="ghost" wire:click="$set('selectedLeaderIds', [])">
                Limpiar selección
            </flux:button>
        </div>
    @endif

    <!-- Leaders List -->
    @if($leaders->isEmpty())
        <div class="rounded-xl bg-white p-8 text-center shadow-sm dark:bg-zinc-900">
            @if($search)
                <flux:icon.magnifying-glass class="mx-auto h-12 w-12 text-zinc-400 dark:text-zinc-600" />
                <flux:heading size="lg" class="mt-2">No se encontraron resultados</flux:heading>
                <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">
                    Intenta con otros términos de búsqueda
                </flux:text>
                <flux:button
                    wire:click="$set('search', '')"
                    variant="ghost"
                    class="mt-4"
                >
                    Limpiar búsqueda
                </flux:button>
            @else
                <flux:icon.user-group class="mx-auto h-12 w-12 text-zinc-400 dark:text-zinc-600" />
                <flux:heading size="lg" class="mt-2">No hay líderes registrados</flux:heading>
                <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">
                    Comienza agregando tu primer líder
                </flux:text>
                <flux:button
                    :href="route('coordinator.leaders.create')"
                    wire:navigate
                    variant="primary"
                    class="mt-4"
                >
                    Agregar Líder
                </flux:button>
            @endif
        </div>
    @else
        <div class="rounded-xl bg-white shadow-sm dark:bg-zinc-900">
            <div class="border-b border-zinc-200 p-4 dark:border-zinc-700">
                <flux:checkbox wire:model.live="selectAllOnPage" label="Seleccionar todos" />
            </div>
            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach($leaders as $leader)
                    <div class="p-4 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-start gap-3">
                                <flux:checkbox wire:model.live="selectedLeaderIds" value="{{ $leader->id }}" wire:key="select-leader-{{ $leader->id }}" class="mt-3" />
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-base font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                    {{ substr($leader->name, 0, 1) }}{{ substr(explode(' ', $leader->name)[1] ?? $leader->name, 0, 1) }}
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('coordinator.leaders.voters', $leader) }}" wire:navigate class="hover:underline">
                                            <flux:heading size="sm">{{ $leader->name }}</flux:heading>
                                        </a>
                                        <flux:badge color="blue" size="sm">Líder</flux:badge>
                                    </div>
                                    <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                                        {{ $leader->email }}
                                    </flux:text>
                                    @if($leader->neighborhood)
                                        <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                                            <flux:icon.map-pin class="inline h-3.5 w-3.5" />
                                            {{ $leader->neighborhood->name }}
                                        </flux:text>
                                    @endif
                                    <div class="mt-2 flex items-center gap-4">
                                        <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">
                                            <flux:icon.users class="inline h-4 w-4" />
                                            {{ $leader->voters_count }} apoyos registrados
                                        </flux:text>
                                        <flux:text size="sm" class="text-zinc-500 dark:text-zinc-500">
                                            <flux:icon.clock class="inline h-3.5 w-3.5" />
                                            Unido {{ $leader->created_at->diffForHumans() }}
                                        </flux:text>
                                    </div>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="pencil-square"
                                    :href="route('coordinator.leaders.edit', $leader)"
                                    wire:navigate
                                >
                                    Editar
                                </flux:button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Pagination -->
        @if($leaders->hasPages())
            <div class="mt-4">
                {{ $leaders->links() }}
            </div>
        @endif
    @endif

    <flux:modal wire:model="showBulkMetadataModal" class="md:w-96">
        <div class="space-y-4">
            <flux:heading size="lg">Asignar metadata</flux:heading>
            <flux:text>Se aplicará la misma llave y el mismo valor a {{ count($selectedLeaderIds) }} líder(es).</flux:text>

            <flux:select wire:model.live="bulkMetadataKeyId" label="Llave" placeholder="Selecciona una llave">
                @foreach ($this->metadataKeys as $key)
                    <flux:select.option value="{{ $key->id }}">{{ $key->label }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($this->selectedMetadataKey?->type === 'select')
                <flux:select wire:model="bulkMetadataValue" label="Valor" placeholder="Selecciona un valor">
                    @foreach ($this->selectedMetadataKey->options ?? [] as $option)
                        <flux:select.option value="{{ $option }}">{{ $option }}</flux:select.option>
                    @endforeach
                </flux:select>
            @elseif ($this->selectedMetadataKey?->type === 'numeric')
                <flux:input wire:model="bulkMetadataValue" type="number" step="0.01" label="Valor" />
            @elseif ($this->selectedMetadataKey?->type === 'date')
                <flux:input wire:model="bulkMetadataValue" type="date" label="Valor" />
            @elseif ($this->selectedMetadataKey?->type === 'text')
                <flux:input wire:model="bulkMetadataValue" label="Valor" />
            @endif

            @error('bulkMetadataKeyId') <flux:text class="text-red-600">{{ $message }}</flux:text> @enderror
            @error('bulkMetadataValue') <flux:text class="text-red-600">{{ $message }}</flux:text> @enderror
            @error('selectedLeaderIds') <flux:text class="text-red-600">{{ $message }}</flux:text> @enderror

            <div class="flex gap-2">
                <flux:button variant="primary" wire:click="assignBulkMetadata" :disabled="! $this->selectedMetadataKey">
                    Asignar
                </flux:button>
                <flux:button variant="ghost" wire:click="$set('showBulkMetadataModal', false)">Cancelar</flux:button>
            </div>
        </div>
    </flux:modal>
</div>

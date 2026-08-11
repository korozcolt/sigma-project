<?php

use App\Enums\UserRole;
use App\Models\MetadataKey;
use App\Models\User;
use App\Services\MetadataAssignmentService;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

use function Livewire\Volt\layout;

layout('components.layouts::app', ['title' => 'Gestión de Coordinadores']);

new class extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    /** @var array<int, string> */
    public array $selectedCoordinatorIds = [];

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
        $this->selectedCoordinatorIds = [];
        $this->selectAllOnPage = false;
    }

    public function updatedSelectAllOnPage(bool $value): void
    {
        $pageIds = $this->coordinatorsQuery()->latest()->paginate(15)->pluck('id')->map(fn ($id) => (string) $id)->all();

        $this->selectedCoordinatorIds = $value ? $pageIds : [];
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
            'selectedCoordinatorIds' => ['required', 'array', 'min:1'],
        ]);

        $key = $service->findActiveKey($this->bulkMetadataKeyId);

        if (! $key) {
            $this->addError('bulkMetadataKeyId', 'La llave seleccionada no está disponible.');

            return;
        }

        // Los ids llegan del navegador y no son de confianza: se re-filtran contra
        // los subordinados directos del actor antes de escribir nada.
        $targets = $service->subordinatesByIds($actor, $this->selectedCoordinatorIds);

        if ($targets->isEmpty()) {
            $this->addError('selectedCoordinatorIds', 'No hay coordinadores válidos seleccionados.');

            return;
        }

        try {
            $assigned = $service->assignMany($targets, $key, $this->bulkMetadataValue, $actor);
        } catch (\InvalidArgumentException $e) {
            $this->addError('bulkMetadataValue', $e->getMessage());

            return;
        }

        $this->reset(['selectedCoordinatorIds', 'selectAllOnPage', 'showBulkMetadataModal', 'bulkMetadataKeyId', 'bulkMetadataValue']);

        session()->flash('success', "Metadata asignada a {$assigned} coordinador(es).");
    }

    protected function coordinatorsQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $user = auth()->user();
        $query = User::role(UserRole::COORDINATOR->value)->withCount(['leaders as leaders_count']);

        if ($user->hasRole(UserRole::AREA_COORDINATOR->value)) {
            $query->where('area_coordinator_user_id', $user->id);
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
        $query = $this->coordinatorsQuery();

        $coordinators = $query->latest()->paginate(15);

        $totalCoordinators = $query->clone()->count();
        $totalLeaders = $query->clone()->get()->sum('leaders_count');

        return [
            'coordinators' => $coordinators,
            'totalCoordinators' => $totalCoordinators,
            'totalLeaders' => $totalLeaders,
        ];
    }
}; ?>

<div class="mx-auto max-w-7xl space-y-6 p-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Gestión de Coordinadores</flux:heading>
            <flux:subheading>Administra tu equipo de coordinadores</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:button variant="primary" :href="route('articulador.coordinadores.create')" wire:navigate icon="user-plus">
                Agregar Coordinador
            </flux:button>
        </div>
    </div>

    <!-- Stats Summary -->
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-zinc-900">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Total Coordinadores</flux:text>
            <flux:heading size="xl" class="mt-1">{{ $totalCoordinators }}</flux:heading>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-zinc-900">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Total Líderes</flux:text>
            <flux:heading size="xl" class="mt-1">{{ number_format($totalLeaders) }}</flux:heading>
        </div>
    </div>

    <!-- Search -->
    <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-zinc-900">
        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="Buscar por nombre o email..."
            icon="magnifying-glass"
        />
    </div>

    @if (count($selectedCoordinatorIds) > 0)
        <div class="flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm dark:bg-zinc-900">
            <flux:text>{{ count($selectedCoordinatorIds) }} seleccionado(s)</flux:text>
            <flux:button variant="primary" icon="tag" wire:click="$set('showBulkMetadataModal', true)">
                Asignar metadata
            </flux:button>
            <flux:button variant="ghost" wire:click="$set('selectedCoordinatorIds', [])">
                Limpiar selección
            </flux:button>
        </div>
    @endif

    <!-- Coordinators List -->
    @if($coordinators->isEmpty())
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
                <flux:heading size="lg" class="mt-2">No hay coordinadores registrados</flux:heading>
                <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">
                    Comienza agregando tu primer coordinador
                </flux:text>
                <flux:button
                    :href="route('articulador.coordinadores.create')"
                    wire:navigate
                    variant="primary"
                    class="mt-4"
                >
                    Agregar Coordinador
                </flux:button>
            @endif
        </div>
    @else
        <div class="rounded-xl bg-white shadow-sm dark:bg-zinc-900">
            <div class="border-b border-zinc-200 p-4 dark:border-zinc-700">
                <flux:checkbox wire:model.live="selectAllOnPage" label="Seleccionar todos" />
            </div>
            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach($coordinators as $coordinator)
                    <div class="p-4 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-start gap-3">
                                <flux:checkbox wire:model.live="selectedCoordinatorIds" value="{{ $coordinator->id }}" wire:key="select-coordinator-{{ $coordinator->id }}" class="mt-3" />
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-base font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                    {{ substr($coordinator->name, 0, 1) }}{{ substr(explode(' ', $coordinator->name)[1] ?? $coordinator->name, 0, 1) }}
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <flux:heading size="sm">{{ $coordinator->name }}</flux:heading>
                                        <flux:badge color="blue" size="sm">Coordinador</flux:badge>
                                    </div>
                                    <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                                        {{ $coordinator->email }}
                                    </flux:text>
                                    <div class="mt-2 flex items-center gap-4">
                                        <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">
                                            <flux:icon.users class="inline h-4 w-4" />
                                            {{ $coordinator->leaders_count }} líderes
                                        </flux:text>
                                        <flux:text size="sm" class="text-zinc-500 dark:text-zinc-500">
                                            <flux:icon.clock class="inline h-3.5 w-3.5" />
                                            Unido {{ $coordinator->created_at->diffForHumans() }}
                                        </flux:text>
                                    </div>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="pencil-square"
                                    :href="route('articulador.coordinadores.edit', $coordinator)"
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
        @if($coordinators->hasPages())
            <div class="mt-4">
                {{ $coordinators->links() }}
            </div>
        @endif
    @endif

    <flux:modal wire:model="showBulkMetadataModal" class="md:w-96">
        <div class="space-y-4">
            <flux:heading size="lg">Asignar metadata</flux:heading>
            <flux:text>Se aplicará la misma llave y el mismo valor a {{ count($selectedCoordinatorIds) }} coordinador(es).</flux:text>

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
            @error('selectedCoordinatorIds') <flux:text class="text-red-600">{{ $message }}</flux:text> @enderror

            <div class="flex gap-2">
                <flux:button variant="primary" wire:click="assignBulkMetadata" :disabled="! $this->selectedMetadataKey">
                    Asignar
                </flux:button>
                <flux:button variant="ghost" wire:click="$set('showBulkMetadataModal', false)">Cancelar</flux:button>
            </div>
        </div>
    </flux:modal>
</div>

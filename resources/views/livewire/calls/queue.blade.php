<?php

use App\Models\CallAssignment;
use App\Models\Campaign;
use App\Models\Municipality;
use App\Services\CallAssignmentService;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public ?int $campaignFilter = null;

    public ?int $municipalityFilter = null;

    public ?string $statusFilter = null;

    public ?string $priorityFilter = null;

    public string $search = '';

    public function mount(): void
    {
        // Set default campaign to first active campaign for current user
        $this->campaignFilter = Campaign::active()
            ->whereHas('users', fn ($q) => $q->where('users.id', auth()->id()))
            ->first()?->id;
    }

    #[Computed]
    public function campaigns(): \Illuminate\Database\Eloquent\Collection
    {
        return Campaign::active()
            ->whereHas('users', fn ($q) => $q->where('users.id', auth()->id()))
            ->get();
    }

    #[Computed]
    public function municipalities(): \Illuminate\Database\Eloquent\Collection
    {
        return Municipality::orderBy('name')->get();
    }

    #[Computed]
    public function myQueue(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = CallAssignment::with(['voter.municipality', 'voter.neighborhood', 'campaign'])
            ->where('caller_id', auth()->id())
            ->orderedByPriority();

        // Apply filters
        if ($this->campaignFilter) {
            $query->forCampaign($this->campaignFilter);
        }

        if ($this->statusFilter) {
            match ($this->statusFilter) {
                'pending' => $query->pending(),
                'in_progress' => $query->inProgress(),
                'completed' => $query->completed(),
                default => null,
            };
        }

        if ($this->priorityFilter) {
            $query->where('priority', $this->priorityFilter);
        }

        if ($this->municipalityFilter) {
            $query->whereHas('voter', fn ($q) => $q->where('municipality_id', $this->municipalityFilter));
        }

        if ($this->search) {
            $query->whereHas('voter', function ($q) {
                $q->where('first_name', 'like', "%{$this->search}%")
                    ->orWhere('last_name', 'like', "%{$this->search}%")
                    ->orWhere('document_number', 'like', "%{$this->search}%")
                    ->orWhere('phone', 'like', "%{$this->search}%");
            });
        }

        return $query->paginate(20);
    }

    #[Computed]
    public function nextAssignment(): ?CallAssignment
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        $service = app(CallAssignmentService::class);

        return $service->getNextAssignment(
            $user,
            $this->campaignFilter
        );
    }

    #[Computed]
    public function stats(): array
    {
        $baseQuery = CallAssignment::where('caller_id', auth()->id());

        if ($this->campaignFilter) {
            $baseQuery->forCampaign($this->campaignFilter);
        }

        return [
            'pending' => (clone $baseQuery)->pending()->count(),
            'in_progress' => (clone $baseQuery)->inProgress()->count(),
            'completed_today' => (clone $baseQuery)->completed()
                ->whereDate('updated_at', today())
                ->count(),
            'urgent' => (clone $baseQuery)->pending()->highPriority()->count(),
        ];
    }

    public function startNext(): void
    {
        if (! $this->nextAssignment) {
            session()->flash('error', 'No hay asignaciones pendientes.');

            return;
        }

        $this->nextAssignment->markInProgress();

        $this->redirect("/calls/{$this->nextAssignment->id}/register");
    }

    public function startAssignment(int $assignmentId): void
    {
        $assignment = CallAssignment::findOrFail($assignmentId);

        // Verify assignment belongs to current user
        if ($assignment->caller_id !== auth()->id()) {
            session()->flash('error', 'No tiene permisos para esta asignación.');

            return;
        }

        $assignment->markInProgress();

        $this->redirect("/calls/{$assignmentId}/register");
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCampaignFilter(): void
    {
        $this->resetPage();
    }

    public function updatingMunicipalityFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPriorityFilter(): void
    {
        $this->resetPage();
    }
}; ?>

<div class="max-w-7xl mx-auto py-8 px-4">
    {{-- Header --}}
    <div class="mb-6">
        <flux:heading size="lg">Cola de Llamadas</flux:heading>
        <flux:text>Gestiona tus asignaciones de llamadas priorizadas</flux:text>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <flux:text class="text-sm text-gray-600 dark:text-gray-400">Pendientes</flux:text>
            <flux:heading size="xl">{{ $this->stats['pending'] }}</flux:heading>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <flux:text class="text-sm text-gray-600 dark:text-gray-400">En Progreso</flux:text>
            <flux:heading size="xl">{{ $this->stats['in_progress'] }}</flux:heading>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <flux:text class="text-sm text-gray-600 dark:text-gray-400">Completadas Hoy</flux:text>
            <flux:heading size="xl">{{ $this->stats['completed_today'] }}</flux:heading>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <flux:text class="text-sm text-gray-600 dark:text-gray-400">Urgentes</flux:text>
            <flux:heading size="xl" class="text-red-600">{{ $this->stats['urgent'] }}</flux:heading>
        </div>
    </div>

    {{-- Quick Dial Next --}}
    @if($this->nextAssignment)
        <div class="bg-blue-50 dark:bg-blue-900/20 border-2 border-blue-300 dark:border-blue-700 rounded-lg p-6 mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <flux:heading size="md" class="mb-2">Siguiente Llamada Recomendada</flux:heading>
                    <flux:text class="font-semibold">{{ $this->nextAssignment->voter->full_name }}</flux:text>
                    <flux:text class="text-sm">
                        {{ $this->nextAssignment->voter->phone ?? 'Sin teléfono' }} -
                        Prioridad: {{ ucfirst($this->nextAssignment->priority) }}
                    </flux:text>
                </div>
                <div>
                    <flux:button wire:click="startNext" variant="primary" size="lg">
                        Iniciar Llamada
                    </flux:button>
                </div>
            </div>
        </div>
    @endif

    {{-- Filters --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            {{-- Search --}}
            <flux:field class="md:col-span-2">
                <flux:text>Buscar</flux:text>
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Nombre, documento, teléfono..." />
            </flux:field>

            {{-- Campaign Filter --}}
            <flux:field>
                <flux:text>Campaña</flux:text>
                <flux:select wire:model.live="campaignFilter">
                    <option value="">Todas</option>
                    @foreach($this->campaigns as $campaign)
                        <option value="{{ $campaign->id }}">{{ $campaign->name }}</option>
                    @endforeach
                </flux:select>
            </flux:field>

            {{-- Status Filter --}}
            <flux:field>
                <flux:text>Estado</flux:text>
                <flux:select wire:model.live="statusFilter">
                    <option value="">Todos</option>
                    <option value="pending">Pendiente</option>
                    <option value="in_progress">En Progreso</option>
                    <option value="completed">Completado</option>
                </flux:select>
            </flux:field>

            {{-- Priority Filter --}}
            <flux:field>
                <flux:text>Prioridad</flux:text>
                <flux:select wire:model.live="priorityFilter">
                    <option value="">Todas</option>
                    <option value="urgent">Urgente</option>
                    <option value="high">Alta</option>
                    <option value="medium">Media</option>
                    <option value="low">Baja</option>
                </flux:select>
            </flux:field>
        </div>

        {{-- Municipality Filter (second row) --}}
        <div class="mt-4">
            <flux:field>
                <flux:text>Municipio</flux:text>
                <flux:select wire:model.live="municipalityFilter">
                    <option value="">Todos</option>
                    @foreach($this->municipalities as $municipality)
                        <option value="{{ $municipality->id }}">{{ $municipality->name }}</option>
                    @endforeach
                </flux:select>
            </flux:field>
        </div>
    </div>

    {{-- Queue Table --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Prioridad</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Votante</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Documento</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Teléfono</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Ubicación</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Estado</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($this->myQueue as $assignment)
                        <tr wire:key="assignment-{{ $assignment->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:badge variant="{{ match($assignment->priority) {
                                    'urgent' => 'danger',
                                    'high' => 'warning',
                                    'medium' => 'primary',
                                    'low' => 'ghost',
                                    default => 'ghost'
                                } }}">
                                    {{ ucfirst($assignment->priority) }}
                                </flux:badge>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:text class="font-semibold">{{ $assignment->voter->full_name }}</flux:text>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:text>{{ $assignment->voter->document_number }}</flux:text>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:text>{{ $assignment->voter->phone ?? 'N/A' }}</flux:text>
                            </td>
                            <td class="px-6 py-4">
                                <flux:text class="text-sm">
                                    {{ $assignment->voter->municipality?->name ?? 'N/A' }}<br>
                                    <span class="text-gray-500 dark:text-gray-400">{{ $assignment->voter->neighborhood?->name ?? 'N/A' }}</span>
                                </flux:text>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:badge variant="{{ match($assignment->status) {
                                    'pending' => 'ghost',
                                    'in_progress' => 'primary',
                                    'completed' => 'success',
                                    'reassigned' => 'warning',
                                    default => 'ghost'
                                } }}">
                                    {{ ucfirst($assignment->status) }}
                                </flux:badge>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($assignment->isPending() || $assignment->isInProgress())
                                    <flux:button wire:click="startAssignment({{ $assignment->id }})" variant="primary" size="sm">
                                        {{ $assignment->isPending() ? 'Iniciar' : 'Continuar' }}
                                    </flux:button>
                                @else
                                    <flux:text class="text-sm text-gray-500 dark:text-gray-400">-</flux:text>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center">
                                <flux:text class="text-gray-500 dark:text-gray-400">
                                    No hay asignaciones en tu cola
                                </flux:text>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            {{ $this->myQueue->links() }}
        </div>
    </div>
</div>

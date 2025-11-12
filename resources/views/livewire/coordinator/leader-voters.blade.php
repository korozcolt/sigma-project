<?php

use App\Models\User;
use App\Models\Voter;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use function Livewire\Volt\{layout, state, with};

layout('components.layouts::app', ['title' => 'Votantes del Líder']);

new class extends Component {
    use WithPagination;

    public User $leader;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $statusFilter = 'all';

    public function mount(User $leader): void
    {
        // Verificar que el coordinador puede ver este líder
        $coordinator = auth()->user();
        $campaignIds = $coordinator->campaigns()->pluck('campaigns.id');

        // Verificar que el líder pertenece al mismo municipio y campaña
        abort_unless(
            $leader->hasRole('leader') &&
            $leader->municipality_id === $coordinator->municipality_id &&
            $leader->campaigns()->whereIn('campaigns.id', $campaignIds)->exists(),
            403
        );

        $this->leader = $leader;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $query = Voter::where('registered_by', $this->leader->id)
            ->with(['municipality', 'neighborhood']);

        // Aplicar filtro de búsqueda
        if ($this->search) {
            $query->where(function (Builder $q) {
                $q->where('first_name', 'like', "%{$this->search}%")
                    ->orWhere('last_name', 'like', "%{$this->search}%")
                    ->orWhere('document_number', 'like', "%{$this->search}%")
                    ->orWhere('phone', 'like', "%{$this->search}%");
            });
        }

        // Aplicar filtro de estado
        match ($this->statusFilter) {
            'confirmed' => $query->whereNotNull('confirmed_at'),
            'pending' => $query->whereNull('confirmed_at'),
            'voted' => $query->whereNotNull('voted_at'),
            default => null,
        };

        $voters = $query->latest()->paginate(15);

        // Estadísticas
        $totalVoters = Voter::where('registered_by', $this->leader->id)->count();
        $confirmedVoters = Voter::where('registered_by', $this->leader->id)
            ->whereNotNull('confirmed_at')->count();
        $pendingVoters = Voter::where('registered_by', $this->leader->id)
            ->whereNull('confirmed_at')->count();
        $votedVoters = Voter::where('registered_by', $this->leader->id)
            ->whereNotNull('voted_at')->count();

        return [
            'voters' => $voters,
            'totalVoters' => $totalVoters,
            'confirmedVoters' => $confirmedVoters,
            'pendingVoters' => $pendingVoters,
            'votedVoters' => $votedVoters,
            'confirmationRate' => $totalVoters > 0 ? round(($confirmedVoters / $totalVoters) * 100, 1) : 0,
        ];
    }
}; ?>

<div class="mx-auto max-w-7xl space-y-6 p-6">
    <!-- Header with Back Button -->
    <div class="flex items-center gap-4">
        <flux:button
            variant="ghost"
            :href="route('coordinator.leaders')"
            wire:navigate
            icon="arrow-left"
            size="sm"
        >
            Volver
        </flux:button>
        <div class="flex-1">
            <flux:heading size="xl">Votantes de {{ $leader->name }}</flux:heading>
            <flux:subheading>{{ $leader->email }}</flux:subheading>
        </div>
    </div>

    <!-- Leader Info Card -->
    <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
        <div class="flex items-center gap-4">
            <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-xl font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                {{ substr($leader->name, 0, 1) }}{{ substr(explode(' ', $leader->name)[1] ?? $leader->name, 0, 1) }}
            </div>
            <div class="flex-1">
                <flux:heading size="lg">{{ $leader->name }}</flux:heading>
                <flux:text class="text-zinc-500 dark:text-zinc-400">{{ $leader->email }}</flux:text>
                @if($leader->neighborhood)
                    <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                        <flux:icon.map-pin class="inline h-3.5 w-3.5" />
                        {{ $leader->neighborhood->name }}, {{ $leader->municipality->name }}
                    </flux:text>
                @endif
            </div>
            <flux:badge color="blue">Líder</flux:badge>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-zinc-900">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Total Votantes</flux:text>
            <div class="mt-1 flex items-baseline gap-2">
                <flux:heading size="xl">{{ number_format($totalVoters) }}</flux:heading>
            </div>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-zinc-900">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Confirmados</flux:text>
            <div class="mt-1 flex items-baseline gap-2">
                <flux:heading size="xl" class="text-green-600 dark:text-green-400">{{ number_format($confirmedVoters) }}</flux:heading>
                <flux:text size="sm" class="text-zinc-500">{{ $confirmationRate }}%</flux:text>
            </div>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-zinc-900">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Pendientes</flux:text>
            <div class="mt-1 flex items-baseline gap-2">
                <flux:heading size="xl" class="text-yellow-600 dark:text-yellow-400">{{ number_format($pendingVoters) }}</flux:heading>
            </div>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-zinc-900">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Han Votado</flux:text>
            <div class="mt-1 flex items-baseline gap-2">
                <flux:heading size="xl" class="text-purple-600 dark:text-purple-400">{{ number_format($votedVoters) }}</flux:heading>
            </div>
        </div>
    </div>

    <!-- Search and Filters -->
    <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-zinc-900">
        <div class="flex flex-col gap-4 sm:flex-row">
            <div class="flex-1">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Buscar por nombre, documento o teléfono..."
                    icon="magnifying-glass"
                />
            </div>
            <div class="w-full sm:w-48">
                <flux:select wire:model.live="statusFilter" placeholder="Todos los estados">
                    <option value="all">Todos los estados</option>
                    <option value="confirmed">Confirmados</option>
                    <option value="pending">Pendientes</option>
                    <option value="voted">Han votado</option>
                </flux:select>
            </div>
        </div>
    </div>

    <!-- Voters List -->
    @if($voters->isEmpty())
        <div class="rounded-xl bg-white p-8 text-center shadow-sm dark:bg-zinc-900">
            @if($search || $statusFilter !== 'all')
                <flux:icon.magnifying-glass class="mx-auto h-12 w-12 text-zinc-400 dark:text-zinc-600" />
                <flux:heading size="lg" class="mt-2">No se encontraron resultados</flux:heading>
                <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">
                    Intenta con otros términos de búsqueda o filtros
                </flux:text>
                <flux:button
                    wire:click="$set('search', '')"
                    variant="ghost"
                    class="mt-4"
                >
                    Limpiar búsqueda
                </flux:button>
            @else
                <flux:icon.users class="mx-auto h-12 w-12 text-zinc-400 dark:text-zinc-600" />
                <flux:heading size="lg" class="mt-2">No hay votantes registrados</flux:heading>
                <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">
                    Este líder aún no ha registrado votantes
                </flux:text>
            @endif
        </div>
    @else
        <div class="rounded-xl bg-white shadow-sm dark:bg-zinc-900">
            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach($voters as $voter)
                    <div class="p-4 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-start gap-3">
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-base font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                    {{ substr($voter->first_name, 0, 1) }}{{ substr($voter->last_name, 0, 1) }}
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <flux:heading size="sm">{{ $voter->first_name }} {{ $voter->last_name }}</flux:heading>
                                        @if($voter->confirmed_at)
                                            <flux:badge color="green" size="sm">Confirmado</flux:badge>
                                        @else
                                            <flux:badge color="yellow" size="sm">Pendiente</flux:badge>
                                        @endif
                                        @if($voter->voted_at)
                                            <flux:badge color="purple" size="sm">Votó</flux:badge>
                                        @endif
                                    </div>
                                    <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                                        <flux:icon.identification class="inline h-3.5 w-3.5" />
                                        {{ $voter->document_number }}
                                    </flux:text>
                                    @if($voter->phone)
                                        <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                                            <flux:icon.phone class="inline h-3.5 w-3.5" />
                                            {{ $voter->phone }}
                                        </flux:text>
                                    @endif
                                    @if($voter->neighborhood)
                                        <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                                            <flux:icon.map-pin class="inline h-3.5 w-3.5" />
                                            {{ $voter->neighborhood->name }}, {{ $voter->municipality->name }}
                                        </flux:text>
                                    @endif
                                    <flux:text size="sm" class="text-zinc-500 dark:text-zinc-500">
                                        <flux:icon.clock class="inline h-3.5 w-3.5" />
                                        Registrado {{ $voter->created_at->diffForHumans() }}
                                    </flux:text>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Pagination -->
        @if($voters->hasPages())
            <div class="mt-4">
                {{ $voters->links() }}
            </div>
        @endif
    @endif
</div>

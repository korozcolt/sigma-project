<?php

use App\Models\User;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use function Livewire\Volt\{layout, with};

layout('components.layouts::app', ['title' => 'Gestión de Líderes']);

new class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $user = auth()->user();
        $campaignIds = $user->campaigns()->pluck('campaigns.id');

        $query = User::role('leader')
            ->whereHas('campaigns', fn ($q) => $q->whereIn('campaigns.id', $campaignIds))
            ->where('municipality_id', $user->municipality_id)
            ->withCount(['registeredVoters as voters_count']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%");
            });
        }

        $leaders = $query->latest()->paginate(15);

        $totalLeaders = User::role('leader')
            ->whereHas('campaigns', fn ($q) => $q->whereIn('campaigns.id', $campaignIds))
            ->where('municipality_id', $user->municipality_id)
            ->count();

        $totalVoters = \App\Models\Voter::whereIn('registered_by',
            User::role('leader')
                ->whereHas('campaigns', fn ($q) => $q->whereIn('campaigns.id', $campaignIds))
                ->where('municipality_id', $user->municipality_id)
                ->pluck('id')
        )->count();

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
            <flux:button variant="primary" :href="route('coordinator.leaders.create')" wire:navigate icon="user-plus">
                Agregar Líder
            </flux:button>
        </div>
    </div>

    <!-- Stats Summary -->
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-zinc-900">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Total Líderes</flux:text>
            <flux:heading size="xl" class="mt-1">{{ $totalLeaders }}</flux:heading>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-zinc-900">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Votantes Registrados</flux:text>
            <flux:heading size="xl" class="mt-1">{{ number_format($totalVoters) }}</flux:heading>
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
            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach($leaders as $leader)
                    <div class="p-4 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-start gap-3">
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
                                            {{ $leader->voters_count }} votantes registrados
                                        </flux:text>
                                        <flux:text size="sm" class="text-zinc-500 dark:text-zinc-500">
                                            <flux:icon.clock class="inline h-3.5 w-3.5" />
                                            Unido {{ $leader->created_at->diffForHumans() }}
                                        </flux:text>
                                    </div>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <flux:button size="sm" variant="ghost" icon="eye" title="Ver detalles">
                                    Ver
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
</div>

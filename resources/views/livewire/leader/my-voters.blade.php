<?php

use App\Models\Voter;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use function Livewire\Volt\{state, with, layout};

layout('components.layouts::leader', ['title' => 'Mis Votantes']);

new class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $status = 'all';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $query = Voter::where('registered_by', auth()->id())
            ->with(['municipality', 'neighborhood']);

        // Aplicar búsqueda
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('first_name', 'like', "%{$this->search}%")
                    ->orWhere('last_name', 'like', "%{$this->search}%")
                    ->orWhere('document_number', 'like', "%{$this->search}%")
                    ->orWhere('phone', 'like', "%{$this->search}%");
            });
        }

        // Aplicar filtro de estado
        if ($this->status !== 'all') {
            match ($this->status) {
                'confirmed' => $query->whereNotNull('confirmed_at'),
                'pending' => $query->whereNull('confirmed_at'),
                'voted' => $query->whereNotNull('voted_at'),
                default => null,
            };
        }

        $voters = $query->latest()->paginate(20);

        // Calcular estadísticas
        $total = Voter::where('registered_by', auth()->id())->count();
        $confirmed = Voter::where('registered_by', auth()->id())->whereNotNull('confirmed_at')->count();

        return [
            'voters' => $voters,
            'total' => $total,
            'confirmed' => $confirmed,
        ];
    }
}; ?>

<div class="flex flex-col gap-6">
        <!-- Stats Summary -->
        <div class="grid grid-cols-2 gap-4">
            <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-zinc-900">
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Total</p>
                <p class="mt-1 text-2xl font-bold text-zinc-900 dark:text-white">{{ $total }}</p>
            </div>

            <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-zinc-900">
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Confirmados</p>
                <p class="mt-1 text-2xl font-bold text-green-600 dark:text-green-400">{{ $confirmed }}</p>
            </div>
        </div>

        <!-- Search and Filter -->
        <div class="flex flex-col gap-3">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Buscar por nombre, documento o teléfono..."
                icon="magnifying-glass"
            />

            <div class="flex gap-2 overflow-x-auto pb-2">
                <flux:button
                    wire:click="$set('status', 'all')"
                    :variant="$status === 'all' ? 'primary' : 'ghost'"
                >
                    Todos
                </flux:button>

                <flux:button
                    wire:click="$set('status', 'confirmed')"
                    :variant="$status === 'confirmed' ? 'primary' : 'ghost'"
                >
                    Confirmados
                </flux:button>

                <flux:button
                    wire:click="$set('status', 'pending')"
                    :variant="$status === 'pending' ? 'primary' : 'ghost'"
                >
                    Pendientes
                </flux:button>

                <flux:button
                    wire:click="$set('status', 'voted')"
                    :variant="$status === 'voted' ? 'primary' : 'ghost'"
                >
                    Votaron
                </flux:button>
            </div>
        </div>

        <!-- Voters List -->
        @if($voters->isEmpty())
            <div class="rounded-xl bg-white p-8 text-center shadow-sm dark:bg-zinc-900">
                @if($search || $status !== 'all')
                    <flux:icon.magnifying-glass class="mx-auto h-12 w-12 text-zinc-400 dark:text-zinc-600" />
                    <h3 class="mt-2 text-lg font-medium text-zinc-900 dark:text-white">No se encontraron resultados</h3>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                        Intenta con otros términos de búsqueda o filtros
                    </p>
                    <flux:button
                        wire:click="$set('search', ''); $set('status', 'all')"
                        variant="ghost"
                        class="mt-4"
                    >
                        Limpiar filtros
                    </flux:button>
                @else
                    <flux:icon.user-plus class="mx-auto h-12 w-12 text-zinc-400 dark:text-zinc-600" />
                    <h3 class="mt-2 text-lg font-medium text-zinc-900 dark:text-white">No hay votantes registrados</h3>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                        Comienza registrando tu primer votante
                    </p>
                    <flux:button
                        href="{{ route('leader.register-voter') }}"
                        wire:navigate
                        variant="primary"
                        class="mt-4"
                    >
                        Registrar Votante
                    </flux:button>
                @endif
            </div>
        @else
            <div class="flex flex-col gap-3">
                @foreach($voters as $voter)
                    <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-zinc-900">
                        <div class="flex items-start gap-3">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-base font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                {{ substr($voter->first_name, 0, 1) }}{{ substr($voter->last_name, 0, 1) }}
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex-1 min-w-0">
                                        <p class="font-semibold text-zinc-900 dark:text-white truncate">
                                            {{ $voter->first_name }} {{ $voter->last_name }}
                                        </p>
                                        <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                            {{ $voter->document_number }}
                                        </p>
                                    </div>

                                    <div class="shrink-0">
                                        @if($voter->voted_at)
                                            <flux:badge color="purple" size="sm">Votó</flux:badge>
                                        @elseif($voter->confirmed_at)
                                            <flux:badge color="green" size="sm">Confirmado</flux:badge>
                                        @else
                                            <flux:badge color="yellow" size="sm">Pendiente</flux:badge>
                                        @endif
                                    </div>
                                </div>

                                <div class="mt-2 flex flex-col gap-1 text-sm text-zinc-600 dark:text-zinc-400">
                                    <div class="flex items-center gap-1.5">
                                        <flux:icon.phone class="h-4 w-4" />
                                        <span>{{ $voter->phone }}</span>
                                    </div>

                                    @if($voter->neighborhood)
                                        <div class="flex items-center gap-1.5">
                                            <flux:icon.map-pin class="h-4 w-4" />
                                            <span class="truncate">{{ $voter->neighborhood->name }}</span>
                                        </div>
                                    @elseif($voter->municipality)
                                        <div class="flex items-center gap-1.5">
                                            <flux:icon.map-pin class="h-4 w-4" />
                                            <span class="truncate">{{ $voter->municipality->name }}</span>
                                        </div>
                                    @endif

                                    <div class="flex items-center gap-1.5 text-xs text-zinc-400 dark:text-zinc-500">
                                        <flux:icon.clock class="h-3.5 w-3.5" />
                                        <span>Registrado {{ $voter->created_at->diffForHumans() }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Pagination -->
            @if($voters->hasPages())
                <div class="mt-4">
                    {{ $voters->links() }}
                </div>
            @endif
        @endif

        <!-- Quick Action Button -->
        <div class="fixed bottom-24 right-6 z-30">
            <flux:button
                variant="primary"
                href="{{ route('leader.register-voter') }}"
                wire:navigate
                class="shadow-lg !rounded-full h-14 w-14 p-0 flex items-center justify-center"
            >
                <flux:icon.plus class="h-6 w-6" />
            </flux:button>
        </div>
</div>

<?php

use App\Models\Voter;
use Illuminate\Database\Eloquent\Builder;
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

    public array $exportColumns = [
        'cedula',
        'nombre_completo',
        'telefono',
        'telefono_secundario',
        'email',
        'municipio',
        'barrio',
        'direccion',
        'puesto_votacion',
        'mesa',
        'estado',
        'fecha_registro',
        'fecha_nacimiento',
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    protected function buildQuery(bool $applyFilters = true): Builder
    {
        $query = Voter::where('registered_by', auth()->id())
            ->with(['municipality', 'neighborhood', 'pollingPlace']);

        if ($applyFilters && $this->search) {
            $query->where(function ($q) {
                $q->where('first_name', 'like', "%{$this->search}%")
                    ->orWhere('last_name', 'like', "%{$this->search}%")
                    ->orWhere('document_number', 'like', "%{$this->search}%")
                    ->orWhere('phone', 'like', "%{$this->search}%");
            });
        }

        if ($applyFilters && $this->status !== 'all') {
            match ($this->status) {
                'confirmed' => $query->whereNotNull('confirmed_at'),
                'pending' => $query->whereNull('confirmed_at'),
                'voted' => $query->whereNotNull('voted_at'),
                default => null,
            };
        }

        return $query;
    }

    protected function columnLabels(): array
    {
        return [
            'cedula' => 'Cédula',
            'nombre_completo' => 'Nombre Completo',
            'telefono' => 'Teléfono',
            'telefono_secundario' => 'Teléfono Secundario',
            'email' => 'Email',
            'municipio' => 'Municipio',
            'barrio' => 'Barrio',
            'direccion' => 'Dirección',
            'puesto_votacion' => 'Puesto de Votación',
            'mesa' => 'Mesa',
            'estado' => 'Estado',
            'fecha_registro' => 'Fecha Registro',
            'fecha_nacimiento' => 'Fecha Nacimiento',
        ];
    }

    protected function getSelectedColumns(): array
    {
        $columns = array_values(array_filter($this->exportColumns ?? []));

        if (empty($columns)) {
            return array_keys($this->columnLabels());
        }

        return $columns;
    }

    protected function exportRowFor(Voter $voter, array $columns): array
    {
        $values = [];

        foreach ($columns as $column) {
            $values[] = match ($column) {
                'cedula' => $voter->document_number,
                'nombre_completo' => $voter->full_name,
                'telefono' => $voter->phone,
                'telefono_secundario' => $voter->secondary_phone,
                'email' => $voter->email,
                'municipio' => $voter->municipality?->name ?? 'N/A',
                'barrio' => $voter->neighborhood?->name ?? 'N/A',
                'direccion' => $voter->address,
                'puesto_votacion' => $voter->pollingPlace?->name ?? 'N/A',
                'mesa' => $voter->polling_table_number,
                'estado' => $voter->status->getLabel(),
                'fecha_registro' => $voter->created_at?->format('d/m/Y H:i'),
                'fecha_nacimiento' => $voter->birth_date?->format('d/m/Y'),
                default => null,
            };
        }

        return $values;
    }

    public function with(): array
    {
        $voters = $this->buildQuery()->latest()->paginate(20);

        // Calcular estadísticas
        $total = Voter::where('registered_by', auth()->id())->count();
        $confirmed = Voter::where('registered_by', auth()->id())->whereNotNull('confirmed_at')->count();

        return [
            'voters' => $voters,
            'total' => $total,
            'confirmed' => $confirmed,
        ];
    }

    public function exportVoters()
    {
        $query = $this->buildQuery()->latest();
        $columns = $this->getSelectedColumns();
        $labels = $this->columnLabels();

        $filename = 'mis-votantes-filtrados-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($query, $columns, $labels) {
            $handle = fopen('php://output', 'w');
            if (! $handle) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, array_map(fn ($column) => $labels[$column] ?? $column, $columns));

            $query->chunk(1000, function ($voters) use ($handle, $columns) {
                foreach ($voters as $voter) {
                    fputcsv($handle, $this->exportRowFor($voter, $columns));
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportAllVoters()
    {
        $query = $this->buildQuery(false)->latest();
        $columns = $this->getSelectedColumns();
        $labels = $this->columnLabels();

        $filename = 'mis-votantes-todos-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($query, $columns, $labels) {
            $handle = fopen('php://output', 'w');
            if (! $handle) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, array_map(fn ($column) => $labels[$column] ?? $column, $columns));

            $query->chunk(1000, function ($voters) use ($handle, $columns) {
                foreach ($voters as $voter) {
                    fputcsv($handle, $this->exportRowFor($voter, $columns));
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
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
            <div class="flex items-center justify-between gap-2">
                <div class="text-sm text-zinc-500 dark:text-zinc-400">
                    Exporta tus votantes según el filtro actual
                </div>
                <div class="flex items-center gap-2">
                    <flux:button wire:click="exportVoters" variant="outline">
                        Exportar filtrados
                    </flux:button>
                    <flux:button wire:click="exportAllVoters" variant="outline">
                        Exportar todos
                    </flux:button>
                </div>
            </div>

            <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-zinc-900">
                <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Columnas del CSV</p>
                <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <flux:checkbox wire:model.live="exportColumns" value="cedula" label="Cédula" />
                    <flux:checkbox wire:model.live="exportColumns" value="nombre_completo" label="Nombre" />
                    <flux:checkbox wire:model.live="exportColumns" value="telefono" label="Teléfono" />
                    <flux:checkbox wire:model.live="exportColumns" value="telefono_secundario" label="Teléfono Secundario" />
                    <flux:checkbox wire:model.live="exportColumns" value="email" label="Email" />
                    <flux:checkbox wire:model.live="exportColumns" value="municipio" label="Municipio" />
                    <flux:checkbox wire:model.live="exportColumns" value="barrio" label="Barrio" />
                    <flux:checkbox wire:model.live="exportColumns" value="direccion" label="Dirección" />
                    <flux:checkbox wire:model.live="exportColumns" value="puesto_votacion" label="Puesto de Votación" />
                    <flux:checkbox wire:model.live="exportColumns" value="mesa" label="Mesa" />
                    <flux:checkbox wire:model.live="exportColumns" value="estado" label="Estado" />
                    <flux:checkbox wire:model.live="exportColumns" value="fecha_registro" label="Fecha Registro" />
                    <flux:checkbox wire:model.live="exportColumns" value="fecha_nacimiento" label="Fecha Nacimiento" />
                </div>
            </div>

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

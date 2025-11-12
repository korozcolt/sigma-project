<?php

use App\Models\Voter;
use Livewire\Volt\Component;
use function Livewire\Volt\{computed, state, with, layout};

layout('components.layouts::leader', ['title' => 'Dashboard']);

new class extends Component {
    public function with(): array
    {
        $leaderId = auth()->id();

        $total = Voter::where('registered_by', $leaderId)->count();
        $confirmed = Voter::where('registered_by', $leaderId)
            ->whereNotNull('confirmed_at')
            ->count();
        $pending = Voter::where('registered_by', $leaderId)
            ->whereNull('confirmed_at')
            ->count();
        $voted = Voter::where('registered_by', $leaderId)
            ->whereNotNull('voted_at')
            ->count();

        $confirmationRate = $total > 0 ? round(($confirmed / $total) * 100, 1) : 0;

        $recentVoters = Voter::where('registered_by', $leaderId)
            ->with(['municipality', 'neighborhood'])
            ->latest()
            ->limit(5)
            ->get();

        return [
            'total' => $total,
            'confirmed' => $confirmed,
            'pending' => $pending,
            'voted' => $voted,
            'confirmationRate' => $confirmationRate,
            'recentVoters' => $recentVoters,
        ];
    }
}; ?>

<div class="flex flex-col gap-6">
        <!-- Welcome Section -->
        <div class="rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 p-6 text-white dark:from-blue-600 dark:to-blue-700">
            <h1 class="text-2xl font-bold">¡Bienvenido, {{ auth()->user()->name }}!</h1>
            <p class="mt-1 text-sm text-blue-100">Continúa registrando votantes y alcanza tus metas</p>
        </div>

        <!-- Quick Stats Grid -->
        <div class="grid grid-cols-2 gap-4">
            <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Total Votantes</p>
                        <p class="mt-1 text-2xl font-bold text-zinc-900 dark:text-white">{{ $total }}</p>
                    </div>
                    <div class="rounded-full bg-blue-100 p-3 dark:bg-blue-900/30">
                        <flux:icon.users class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                    </div>
                </div>
            </div>

            <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Confirmados</p>
                        <p class="mt-1 text-2xl font-bold text-green-600 dark:text-green-400">{{ $confirmed }}</p>
                    </div>
                    <div class="rounded-full bg-green-100 p-3 dark:bg-green-900/30">
                        <flux:icon.check-circle class="h-6 w-6 text-green-600 dark:text-green-400" />
                    </div>
                </div>
            </div>

            <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Pendientes</p>
                        <p class="mt-1 text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ $pending }}</p>
                    </div>
                    <div class="rounded-full bg-yellow-100 p-3 dark:bg-yellow-900/30">
                        <flux:icon.clock class="h-6 w-6 text-yellow-600 dark:text-yellow-400" />
                    </div>
                </div>
            </div>

            <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Tasa Confirm.</p>
                        <p class="mt-1 text-2xl font-bold text-purple-600 dark:text-purple-400">{{ $confirmationRate }}%</p>
                    </div>
                    <div class="rounded-full bg-purple-100 p-3 dark:bg-purple-900/30">
                        <flux:icon.chart-bar class="h-6 w-6 text-purple-600 dark:text-purple-400" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Action Button -->
        <flux:button
            variant="primary"
            href="{{ route('leader.register-voter') }}"
            wire:navigate
            class="w-full"
        >
            <flux:icon.user-plus class="mr-2 h-5 w-5" />
            Registrar Nuevo Votante
        </flux:button>

        <!-- Recent Voters -->
        @if($recentVoters->isNotEmpty())
            <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-zinc-900">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">Registros Recientes</h2>
                    <flux:link href="{{ route('leader.my-voters') }}" wire:navigate class="text-sm">
                        Ver todos
                    </flux:link>
                </div>

                <div class="space-y-3">
                    @foreach($recentVoters as $voter)
                        <div class="flex items-start gap-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-sm font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                {{ substr($voter->first_name, 0, 1) }}{{ substr($voter->last_name, 0, 1) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-zinc-900 dark:text-white truncate">
                                    {{ $voter->first_name }} {{ $voter->last_name }}
                                </p>
                                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ $voter->document_number }}
                                </p>
                                <p class="text-xs text-zinc-400 dark:text-zinc-500">
                                    {{ $voter->neighborhood?->name ?? $voter->municipality?->name }}
                                </p>
                            </div>
                            <div>
                                @if($voter->confirmed_at)
                                    <flux:badge color="green" size="sm">Confirmado</flux:badge>
                                @else
                                    <flux:badge color="yellow" size="sm">Pendiente</flux:badge>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="rounded-xl bg-white p-8 text-center shadow-sm dark:bg-zinc-900">
                <flux:icon.user-plus class="mx-auto h-12 w-12 text-zinc-400 dark:text-zinc-600" />
                <h3 class="mt-2 text-lg font-medium text-zinc-900 dark:text-white">No hay votantes registrados</h3>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                    Comienza registrando tu primer votante
                </p>
            </div>
        @endif
</div>

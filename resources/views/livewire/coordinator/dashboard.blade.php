<?php

use App\Models\User;
use App\Models\Voter;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;
use function Livewire\Volt\{layout, with};

layout('components.layouts::app', ['title' => 'Dashboard de Coordinador']);

new class extends Component {
    public function with(): array
    {
        $user = auth()->user();
        $campaignIds = $user->campaigns()->pluck('campaigns.id');

        // Obtener líderes bajo este coordinador
        $leaders = User::role('leader')
            ->whereHas('campaigns', fn ($q) => $q->whereIn('campaigns.id', $campaignIds))
            ->where('municipality_id', $user->municipality_id)
            ->withCount(['registeredVoters as voters_count'])
            ->get();

        $leaderIds = $leaders->pluck('id');

        // Estadísticas de votantes
        $totalVoters = Voter::whereIn('registered_by', $leaderIds)
            ->whereIn('campaign_id', $campaignIds)
            ->count();

        $confirmedVoters = Voter::whereIn('registered_by', $leaderIds)
            ->whereIn('campaign_id', $campaignIds)
            ->whereNotNull('confirmed_at')
            ->count();

        $pendingVoters = $totalVoters - $confirmedVoters;

        $confirmationRate = $totalVoters > 0 ? round(($confirmedVoters / $totalVoters) * 100, 1) : 0;

        // Actividad reciente de líderes
        $recentLeaderActivity = Voter::whereIn('registered_by', $leaderIds)
            ->whereIn('campaign_id', $campaignIds)
            ->where('created_at', '>=', now()->subDays(7))
            ->select('registered_by', DB::raw('count(*) as count'))
            ->with('registeredBy:id,name')
            ->groupBy('registered_by')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        // Líderes más productivos
        $topLeaders = $leaders->sortByDesc('voters_count')->take(5);

        return [
            'totalLeaders' => $leaders->count(),
            'totalVoters' => $totalVoters,
            'confirmedVoters' => $confirmedVoters,
            'pendingVoters' => $pendingVoters,
            'confirmationRate' => $confirmationRate,
            'topLeaders' => $topLeaders,
            'recentLeaderActivity' => $recentLeaderActivity,
            'municipality' => $user->municipality,
        ];
    }
}; ?>

<div class="mx-auto max-w-7xl space-y-6 p-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Dashboard de Coordinador</flux:heading>
            <flux:subheading>{{ $municipality->name ?? 'Tu zona' }}</flux:subheading>
        </div>
        <flux:button variant="primary" :href="route('coordinator.leaders')" wire:navigate icon="users">
            Gestionar Líderes
        </flux:button>
    </div>

    <!-- Main Stats Grid -->
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <!-- Total Líderes -->
        <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <div>
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Líderes Activos</flux:text>
                    <flux:heading size="xl" class="mt-2">{{ $totalLeaders }}</flux:heading>
                </div>
                <div class="rounded-full bg-blue-100 p-3 dark:bg-blue-900/30">
                    <flux:icon.user-group class="h-8 w-8 text-blue-600 dark:text-blue-400" />
                </div>
            </div>
        </div>

        <!-- Total Votantes -->
        <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <div>
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Total Votantes</flux:text>
                    <flux:heading size="xl" class="mt-2">{{ number_format($totalVoters) }}</flux:heading>
                </div>
                <div class="rounded-full bg-purple-100 p-3 dark:bg-purple-900/30">
                    <flux:icon.users class="h-8 w-8 text-purple-600 dark:text-purple-400" />
                </div>
            </div>
        </div>

        <!-- Confirmados -->
        <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <div>
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Confirmados</flux:text>
                    <flux:heading size="xl" class="mt-2">{{ number_format($confirmedVoters) }}</flux:heading>
                    <flux:text size="sm" class="text-green-600 dark:text-green-400">{{ $confirmationRate }}%</flux:text>
                </div>
                <div class="rounded-full bg-green-100 p-3 dark:bg-green-900/30">
                    <flux:icon.check-circle class="h-8 w-8 text-green-600 dark:text-green-400" />
                </div>
            </div>
        </div>

        <!-- Pendientes -->
        <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <div>
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Pendientes</flux:text>
                    <flux:heading size="xl" class="mt-2">{{ number_format($pendingVoters) }}</flux:heading>
                </div>
                <div class="rounded-full bg-yellow-100 p-3 dark:bg-yellow-900/30">
                    <flux:icon.clock class="h-8 w-8 text-yellow-600 dark:text-yellow-400" />
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="grid gap-6 lg:grid-cols-2">
        <!-- Top Leaders -->
        <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-4">Líderes Más Productivos</flux:heading>
            @if($topLeaders->isEmpty())
                <flux:text class="text-center text-zinc-500">No hay líderes registrados aún</flux:text>
            @else
                <div class="space-y-3">
                    @foreach($topLeaders as $index => $leader)
                        <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-zinc-100 text-sm font-bold dark:bg-zinc-800">
                                    #{{ $index + 1 }}
                                </div>
                                <div>
                                    <flux:text class="font-semibold">{{ $leader->name }}</flux:text>
                                    <flux:text size="sm" class="text-zinc-500">{{ $leader->email }}</flux:text>
                                </div>
                            </div>
                            <flux:badge color="blue">{{ $leader->voters_count }} votantes</flux:badge>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Recent Activity -->
        <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-4">Actividad de la Última Semana</flux:heading>
            @if($recentLeaderActivity->isEmpty())
                <flux:text class="text-center text-zinc-500">No hay actividad reciente</flux:text>
            @else
                <div class="space-y-3">
                    @foreach($recentLeaderActivity as $activity)
                        <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                            <div>
                                <flux:text class="font-semibold">{{ $activity->registeredBy->name }}</flux:text>
                                <flux:text size="sm" class="text-zinc-500">Últimos 7 días</flux:text>
                            </div>
                            <flux:badge color="green">{{ $activity->count }} registros</flux:badge>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <flux:button :href="route('coordinator.leaders')" wire:navigate variant="outline" class="h-auto flex-col items-start p-4 text-left">
            <div class="mb-2 rounded-full bg-blue-100 p-2 dark:bg-blue-900/30">
                <flux:icon.users class="h-6 w-6 text-blue-600 dark:text-blue-400" />
            </div>
            <flux:heading size="sm">Gestionar Líderes</flux:heading>
            <flux:text size="sm" class="text-zinc-500">Ver, editar y administrar tu equipo de líderes</flux:text>
        </flux:button>

        <flux:button :href="route('coordinator.leaders.create')" wire:navigate variant="outline" class="h-auto flex-col items-start p-4 text-left">
            <div class="mb-2 rounded-full bg-green-100 p-2 dark:bg-green-900/30">
                <flux:icon.user-plus class="h-6 w-6 text-green-600 dark:text-green-400" />
            </div>
            <flux:heading size="sm">Agregar Líder</flux:heading>
            <flux:text size="sm" class="text-zinc-500">Registrar un nuevo líder en tu equipo</flux:text>
        </flux:button>

        <flux:button href="{{ route('profile.edit') }}" wire:navigate variant="outline" class="h-auto flex-col items-start p-4 text-left">
            <div class="mb-2 rounded-full bg-purple-100 p-2 dark:bg-purple-900/30">
                <flux:icon.cog class="h-6 w-6 text-purple-600 dark:text-purple-400" />
            </div>
            <flux:heading size="sm">Configuración</flux:heading>
            <flux:text size="sm" class="text-zinc-500">Actualiza tu perfil y preferencias</flux:text>
        </flux:button>
    </div>
</div>

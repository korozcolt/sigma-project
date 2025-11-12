<?php

use App\Models\Campaign;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;
use function Livewire\Volt\{layout, with};

layout('components.layouts::app', ['title' => 'Dashboard de Campaña']);

new class extends Component {
    public function with(): array
    {
        $user = auth()->user();

        // Obtener las campañas del admin
        $campaigns = $user->campaigns()->with(['users'])->get();
        $campaignIds = $campaigns->pluck('id');

        // Estadísticas generales
        $totalVoters = Voter::whereIn('campaign_id', $campaignIds)->count();
        $confirmedVoters = Voter::whereIn('campaign_id', $campaignIds)
            ->whereNotNull('confirmed_at')
            ->count();
        $votedVoters = Voter::whereIn('campaign_id', $campaignIds)
            ->whereNotNull('voted_at')
            ->count();

        // Tasas
        $confirmationRate = $totalVoters > 0 ? round(($confirmedVoters / $totalVoters) * 100, 1) : 0;
        $voteRate = $totalVoters > 0 ? round(($votedVoters / $totalVoters) * 100, 1) : 0;

        // Equipos (coordinadores y líderes)
        $totalCoordinators = User::role('coordinator')
            ->whereHas('campaigns', fn ($q) => $q->whereIn('campaigns.id', $campaignIds))
            ->count();

        $totalLeaders = User::role('leader')
            ->whereHas('campaigns', fn ($q) => $q->whereIn('campaigns.id', $campaignIds))
            ->count();

        // Top 5 líderes con más votantes
        $topLeaders = User::role('leader')
            ->whereHas('campaigns', fn ($q) => $q->whereIn('campaigns.id', $campaignIds))
            ->withCount(['registeredVoters as voters_count' => fn ($q) => $q->whereIn('campaign_id', $campaignIds)])
            ->orderByDesc('voters_count')
            ->limit(5)
            ->get();

        // Estadísticas por municipio
        $votersByMunicipality = Voter::whereIn('campaign_id', $campaignIds)
            ->select('municipality_id', DB::raw('count(*) as total'))
            ->with('municipality')
            ->groupBy('municipality_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // Actividad reciente (últimos 7 días)
        $recentActivity = Voter::whereIn('campaign_id', $campaignIds)
            ->where('created_at', '>=', now()->subDays(7))
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();

        return [
            'campaigns' => $campaigns,
            'totalVoters' => $totalVoters,
            'confirmedVoters' => $confirmedVoters,
            'votedVoters' => $votedVoters,
            'confirmationRate' => $confirmationRate,
            'voteRate' => $voteRate,
            'totalCoordinators' => $totalCoordinators,
            'totalLeaders' => $totalLeaders,
            'topLeaders' => $topLeaders,
            'votersByMunicipality' => $votersByMunicipality,
            'recentActivity' => $recentActivity,
        ];
    }
}; ?>

<div class="mx-auto max-w-7xl space-y-6 p-6">
    <!-- Header -->
    <div>
        <flux:heading size="xl">Panel de Administración de Campaña</flux:heading>
        <flux:subheading>Vista estadística general de tus campañas</flux:subheading>
    </div>

    <!-- Campaigns Overview -->
    @if($campaigns->count() > 1)
        <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-3">Tus Campañas</flux:heading>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($campaigns as $campaign)
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                        <flux:heading size="sm">{{ $campaign->name }}</flux:heading>
                        <flux:text size="sm" class="text-zinc-500">
                            {{ $campaign->users->count() }} miembros del equipo
                        </flux:text>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Main Stats Grid -->
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <!-- Total Votantes -->
        <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <div>
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Total Votantes</flux:text>
                    <flux:heading size="xl" class="mt-2">{{ number_format($totalVoters) }}</flux:heading>
                </div>
                <div class="rounded-full bg-blue-100 p-3 dark:bg-blue-900/30">
                    <flux:icon.users class="h-8 w-8 text-blue-600 dark:text-blue-400" />
                </div>
            </div>
        </div>

        <!-- Votantes Confirmados -->
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

        <!-- Votantes que Votaron -->
        <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <div>
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Han Votado</flux:text>
                    <flux:heading size="xl" class="mt-2">{{ number_format($votedVoters) }}</flux:heading>
                    <flux:text size="sm" class="text-purple-600 dark:text-purple-400">{{ $voteRate }}%</flux:text>
                </div>
                <div class="rounded-full bg-purple-100 p-3 dark:bg-purple-900/30">
                    <flux:icon.check-badge class="h-8 w-8 text-purple-600 dark:text-purple-400" />
                </div>
            </div>
        </div>

        <!-- Total Equipo -->
        <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <div>
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Equipo</flux:text>
                    <flux:heading size="xl" class="mt-2">{{ $totalCoordinators + $totalLeaders }}</flux:heading>
                    <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                        {{ $totalCoordinators }} coordinadores, {{ $totalLeaders }} líderes
                    </flux:text>
                </div>
                <div class="rounded-full bg-orange-100 p-3 dark:bg-orange-900/30">
                    <flux:icon.user-group class="h-8 w-8 text-orange-600 dark:text-orange-400" />
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="grid gap-6 lg:grid-cols-2">
        <!-- Top Leaders -->
        <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-4">Top 5 Líderes</flux:heading>
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

        <!-- Voters by Municipality -->
        <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-4">Votantes por Municipio</flux:heading>
            @if($votersByMunicipality->isEmpty())
                <flux:text class="text-center text-zinc-500">No hay datos disponibles</flux:text>
            @else
                <div class="space-y-2">
                    @foreach($votersByMunicipality as $item)
                        <div class="flex items-center justify-between">
                            <flux:text>{{ $item->municipality->name ?? 'Sin municipio' }}</flux:text>
                            <div class="flex items-center gap-2">
                                <div class="h-2 w-32 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                                    <div
                                        class="h-full rounded-full bg-blue-500"
                                        style="width: {{ $totalVoters > 0 ? ($item->total / $totalVoters) * 100 : 0 }}%"
                                    ></div>
                                </div>
                                <flux:text class="w-12 text-right font-semibold">{{ $item->total }}</flux:text>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
        <flux:heading size="lg" class="mb-4">Actividad Reciente (Últimos 7 días)</flux:heading>
        @if($recentActivity->isEmpty())
            <flux:text class="text-center text-zinc-500">No hay actividad reciente</flux:text>
        @else
            <div class="grid gap-2 sm:grid-cols-7">
                @foreach($recentActivity as $day)
                    <div class="rounded-lg border border-zinc-200 p-3 text-center dark:border-zinc-700">
                        <flux:text size="sm" class="text-zinc-500">
                            {{ \Carbon\Carbon::parse($day->date)->format('d/m') }}
                        </flux:text>
                        <flux:heading size="sm" class="mt-1">{{ $day->count }}</flux:heading>
                        <flux:text size="xs" class="text-zinc-400">registros</flux:text>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

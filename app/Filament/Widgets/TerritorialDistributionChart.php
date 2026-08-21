<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Voter;
use App\Services\CampaignContext;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TerritorialDistributionChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Distribución Territorial';

    protected ?string $description = 'Haz clic en un departamento o municipio para explorar el siguiente nivel.';

    protected ?string $pollingInterval = '120s';

    protected string $view = 'filament.widgets.react-chart';

    protected function getData(): array
    {
        $activeCampaign = CampaignContext::currentCampaign();

        if (! $activeCampaign) {
            return ['tree' => [], 'emptyReason' => 'no_campaign'];
        }

        // D-09: role-scoping carries over unchanged from this widget's prior flat-bar implementation.
        $user = Auth::user();

        // Pitfall 3: neighborhood_id is nullable on Voter - LEFT JOIN (never INNER JOIN) and
        // bucket nulls into an explicit "Sin barrio" leaf below, or municipio totals would
        // silently undercount versus the campaign's real total.
        $rows = Voter::query()
            ->select(
                'departments.id as dept_id',
                'departments.name as dept_name',
                'municipalities.id as muni_id',
                'municipalities.name as muni_name',
                'neighborhoods.id as hood_id',
                'neighborhoods.name as hood_name',
                DB::raw('COUNT(*) as total')
            )
            ->join('municipalities', 'voters.municipality_id', '=', 'municipalities.id')
            ->join('departments', 'municipalities.department_id', '=', 'departments.id')
            ->leftJoin('neighborhoods', 'voters.neighborhood_id', '=', 'neighborhoods.id')
            ->where('voters.campaign_id', $activeCampaign->id)
            ->when(
                $user?->hasRole(UserRole::LEADER->value),
                fn ($q) => $q->where('voters.registered_by', Auth::id())
            )
            ->when(
                $user?->hasAnyRole([UserRole::COORDINATOR->value, UserRole::AREA_COORDINATOR->value]),
                fn ($q) => $q->whereIn('voters.registered_by', User::whereIn('coordinator_user_id', $user->teamCoordinatorUserIds())->pluck('id'))
            )
            ->groupBy('departments.id', 'departments.name', 'municipalities.id', 'municipalities.name', 'neighborhoods.id', 'neighborhoods.name')
            ->get();

        if ($rows->isEmpty()) {
            return ['tree' => [], 'emptyReason' => 'no_voters'];
        }

        // D-11: nest Departamento -> Municipio -> Barrio for TreemapChart.jsx's type="nest" drill-down.
        // D-12: no leaf-tile cap - all barrios render, Recharts' squarified layout handles sizing.
        $tree = $rows->groupBy('dept_name')->map(function ($deptRows, $deptName) {
            $municipios = $deptRows->groupBy('muni_name')->map(function ($muniRows, $muniName) {
                $barrios = $muniRows->map(fn ($r) => ['name' => $r->hood_name ?? 'Sin barrio', 'value' => (int) $r->total]);

                return ['name' => $muniName, 'children' => $barrios->values()->toArray()];
            });

            return ['name' => $deptName, 'children' => $municipios->values()->toArray()];
        })->values()->toArray();

        return ['tree' => $tree];
    }

    protected function getType(): string
    {
        return $this->getChartKind();
    }

    protected function getChartKind(): string
    {
        return 'treemap';
    }
}

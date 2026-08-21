<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Enums\VoterStatus;
use App\Models\User;
use App\Models\Voter;
use App\Services\CampaignContext;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

class CoordinatorTeamStackedBarChart extends ChartWidget
{
    protected static ?int $sort = 21;

    protected ?string $heading = 'Apoyos por Coordinador: Validados, Rechazados y Registrados';

    protected ?string $pollingInterval = '120s';

    protected string $view = 'filament.widgets.react-chart';

    /**
     * D-03: passed some formal verification, regardless of Día D status.
     * Excludes VOTED/DID_NOT_VOTE (residual "Registrado" bucket, D-05) and
     * PENDING_REVIEW/CORRECTION_REQUIRED (still in-process, D-04 bucket instead).
     */
    private const VALIDADO_STATUSES = [
        VoterStatus::VERIFIED_CENSUS->value,
        VoterStatus::VERIFIED_REGISTRADURIA->value,
        VoterStatus::VERIFIED_CALL->value,
        VoterStatus::CONFIRMED->value,
    ];

    /**
     * D-04: verbatim 4-status bucket locked by 22-CONTEXT.md.
     */
    private const RECHAZADO_STATUSES = [
        VoterStatus::REJECTED_CENSUS->value,
        VoterStatus::REJECTED_OUT_OF_SCOPE->value,
        VoterStatus::CENSUS_NOT_FOUND->value,
        VoterStatus::CORRECTION_REQUIRED->value,
    ];

    protected function getData(): array
    {
        $activeCampaign = CampaignContext::currentCampaign();

        if (! $activeCampaign) {
            return [
                'labels' => [],
                'datasets' => [
                    ['label' => 'Validado', 'data' => []],
                    ['label' => 'Rechazado', 'data' => []],
                    ['label' => 'Registrado', 'data' => []],
                ],
                'emptyReason' => 'no_campaign',
            ];
        }

        $coordinators = User::query()
            ->role(UserRole::COORDINATOR->value)
            ->whereHas('campaigns', fn (Builder $q) => $q->where('campaigns.id', $activeCampaign->id))
            ->get();

        if ($coordinators->isEmpty()) {
            return [
                'labels' => [],
                'datasets' => [
                    ['label' => 'Validado', 'data' => []],
                    ['label' => 'Rechazado', 'data' => []],
                    ['label' => 'Registrado', 'data' => []],
                ],
                'emptyReason' => 'no_coordinators',
            ];
        }

        $labels = [];
        $validado = [];
        $rechazado = [];
        $registrado = [];

        foreach ($coordinators as $coordinator) {
            // D-07: team = coordinator + their líderes, exact TopCoordinatorsTable pattern.
            $teamUserIds = $coordinator->leaders()->pluck('id')->push($coordinator->id)->all();

            $statusCounts = Voter::query()
                ->where('campaign_id', $activeCampaign->id)
                ->whereIn('registered_by', $teamUserIds)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $validadoCount = 0;
            foreach (self::VALIDADO_STATUSES as $status) {
                $validadoCount += (int) ($statusCounts[$status] ?? 0);
            }

            $rechazadoCount = 0;
            foreach (self::RECHAZADO_STATUSES as $status) {
                $rechazadoCount += (int) ($statusCounts[$status] ?? 0);
            }

            $total = (int) $statusCounts->sum();

            $labels[] = $coordinator->name;
            $validado[] = $validadoCount;
            $rechazado[] = $rechazadoCount;
            // D-05: residual bucket — never drops a voter (includes PENDING_REVIEW, DUPLICATE, VOTED, DID_NOT_VOTE).
            $registrado[] = $total - $validadoCount - $rechazadoCount;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                ['label' => 'Validado', 'data' => $validado],
                ['label' => 'Rechazado', 'data' => $rechazado],
                ['label' => 'Registrado', 'data' => $registrado],
            ],
        ];
    }

    protected function getType(): string
    {
        return $this->getChartKind();
    }

    protected function getChartKind(): string
    {
        return 'stacked-bar';
    }
}

<?php

namespace App\Filament\Widgets;

use App\Enums\VoterStatus;
use App\Models\Voter;
use App\Services\CampaignContext;
use Filament\Widgets\ChartWidget;

class VoterStatusDonutChart extends ChartWidget
{
    protected static ?int $sort = 20;

    protected ?string $heading = 'Distribución de Estados de Apoyos';

    protected ?string $pollingInterval = '120s';

    protected string $view = 'filament.widgets.react-chart';

    protected function getData(): array
    {
        $activeCampaign = CampaignContext::currentCampaign();

        if (! $activeCampaign) {
            return [
                'labels' => [],
                'datasets' => [['label' => 'Apoyos', 'data' => []]],
                'emptyReason' => 'no_campaign',
            ];
        }

        $counts = Voter::query()
            ->where('campaign_id', $activeCampaign->id)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        if ($counts->isEmpty()) {
            return [
                'labels' => [],
                'datasets' => [['label' => 'Apoyos', 'data' => []]],
                'emptyReason' => 'no_voters',
            ];
        }

        $labels = [];
        $data = [];

        foreach (VoterStatus::cases() as $status) {
            $total = (int) ($counts[$status->value] ?? 0);
            if ($total === 0) {
                continue;
            }
            $labels[] = $status->getLabel();
            $data[] = $total;
        }

        return [
            'labels' => $labels,
            'datasets' => [['label' => 'Apoyos', 'data' => $data]],
        ];
    }

    protected function getType(): string
    {
        return $this->getChartKind();
    }

    protected function getChartKind(): string
    {
        return 'pie';
    }
}

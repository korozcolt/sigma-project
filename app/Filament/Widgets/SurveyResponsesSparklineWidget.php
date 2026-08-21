<?php

namespace App\Filament\Widgets;

use App\Models\Survey;
use App\Services\CampaignContext;
use Filament\Widgets\ChartWidget;

class SurveyResponsesSparklineWidget extends ChartWidget
{
    protected string $view = 'filament.widgets.react-chart';

    protected static ?int $sort = 0;

    protected ?string $heading = 'Respuestas de Encuestas — últimos 7 días';

    protected function getType(): string
    {
        return $this->getChartKind();
    }

    protected function getChartKind(): string
    {
        return 'sparkline';
    }

    protected function getData(): array
    {
        $campaign = CampaignContext::currentCampaign();

        if (! $campaign) {
            return ['points' => [], 'emptyReason' => 'no_campaign'];
        }

        $survey = Survey::forCampaign($campaign->id)->active()->latest()->first()
            ?? Survey::forCampaign($campaign->id)->latest()->first();

        if (! $survey) {
            return ['points' => []];
        }

        $counts = (new SurveyStatsOverview)->getResponsesChart($survey);

        return ['points' => $this->toPoints($counts)];
    }

    /**
     * @param  array<int, float>  $counts  oldest-first, index 0 = 6 days ago, last index = today
     * @return array<int, array{label: string, value: float}>
     */
    private function toPoints(array $counts): array
    {
        $total = count($counts);

        return collect($counts)->values()->map(function (float $value, int $i) use ($total): array {
            $daysAgo = $total - 1 - $i;

            return [
                'label' => $daysAgo === 0 ? 'Hoy' : "Hace {$daysAgo}d",
                'value' => $value,
            ];
        })->all();
    }
}

<?php

namespace App\Filament\Widgets;

use App\Enums\VoterStatus;
use App\Models\ValidationHistory;
use App\Services\CampaignContext;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class RejectionReasonsStackedAreaChart extends ChartWidget
{
    protected static ?int $sort = 25;

    protected ?string $heading = 'Motivos de Rechazo en el Tiempo';

    protected ?string $pollingInterval = '120s';

    protected string $view = 'filament.widgets.react-chart';

    // D-18: VoterStatus rejection states only, in this fixed emission order (also determines
    // color-rank order per 23-UI-SPEC.md - REJECTED_CENSUS is always index 0/accent).
    private const REJECTION_STATUSES = [
        VoterStatus::REJECTED_CENSUS,
        VoterStatus::REJECTED_OUT_OF_SCOPE,
        VoterStatus::CENSUS_NOT_FOUND,
        VoterStatus::CORRECTION_REQUIRED,
    ];

    protected function getData(): array
    {
        $activeCampaign = CampaignContext::currentCampaign();

        $emptyPayload = fn (string $reason): array => ['labels' => [], 'datasets' => [], 'emptyReason' => $reason];

        if (! $activeCampaign) {
            return $emptyPayload('no_campaign');
        }

        $rows = ValidationHistory::query()
            ->join('voters', 'voters.id', '=', 'validation_histories.voter_id')
            ->where('voters.campaign_id', $activeCampaign->id)
            ->whereIn('validation_histories.new_status', array_map(fn (VoterStatus $s) => $s->value, self::REJECTION_STATUSES))
            ->select('validation_histories.new_status', 'validation_histories.created_at')
            ->get();

        if ($rows->isEmpty()) {
            return $emptyPayload('no_rejections');
        }

        // D-19/D-20: bucket by created_at into weeks, in PHP/Carbon - never raw SQL, which
        // disagrees between MySQL (prod) and sqlite (tests) on week-numbering (Pitfall 2).
        $byWeek = $rows->groupBy(fn ($r) => Carbon::parse($r->created_at)->startOfWeek()->format('Y-m-d'));
        $weekLabels = $byWeek->keys()->sort()->values();

        $datasets = collect(self::REJECTION_STATUSES)->map(fn (VoterStatus $status) => [
            'label' => $status->getLabel(),
            'data' => $weekLabels->map(
                fn ($week) => $byWeek[$week]->where('new_status', $status)->count()
            )->toArray(),
        ])->toArray();

        return ['labels' => $weekLabels->toArray(), 'datasets' => $datasets];
    }

    protected function getType(): string
    {
        return $this->getChartKind();
    }

    protected function getChartKind(): string
    {
        return 'stacked-area';
    }
}

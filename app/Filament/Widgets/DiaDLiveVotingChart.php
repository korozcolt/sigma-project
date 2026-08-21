<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\ElectionEvent;
use App\Models\VoteRecord;
use App\Services\CampaignContext;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class DiaDLiveVotingChart extends ChartWidget
{
    protected ?string $heading = 'Votación en Vivo — Día D';

    protected ?string $description = 'Votos acumulados por hora (actualiza cada 30 segundos)';

    protected ?string $pollingInterval = '30s';

    protected string $view = 'filament.widgets.react-chart';

    protected function getData(): array
    {
        $activeCampaign = CampaignContext::currentCampaign();

        if (! $activeCampaign) {
            return ['labels' => [], 'datasets' => [], 'emptyReason' => 'no_campaign'];
        }

        $activeEvent = ElectionEvent::where('is_active', true)->first();

        if (! $activeEvent) {
            return ['labels' => [], 'datasets' => [], 'emptyReason' => 'no_active_event'];
        }

        $cacheKey = "diad-live-voting:{$activeCampaign->id}:{$activeEvent->id}";

        return Cache::remember($cacheKey, now()->addSeconds(30), fn () => $this->buildChartData($activeCampaign->id, $activeEvent->id));
    }

    private function buildChartData(int $campaignId, int $electionEventId): array
    {
        $votes = VoteRecord::query()
            ->where('campaign_id', $campaignId)
            ->where('election_event_id', $electionEventId)
            ->select('voted_at')
            ->get();

        if ($votes->isEmpty()) {
            return ['labels' => [], 'datasets' => [], 'emptyReason' => 'no_votes_yet'];
        }

        // Bucket in PHP/Carbon, never raw SQL DATE_FORMAT/strftime - MySQL (prod) and
        // sqlite (tests) disagree on date-truncation functions. Mirrors
        // RejectionReasonsStackedAreaChart's (Phase 23) documented fix for this bug class.
        $byHour = $votes->groupBy(fn (VoteRecord $v) => Carbon::parse($v->voted_at)->format('Y-m-d H:00'));

        $firstHour = Carbon::parse($byHour->keys()->sort()->first())->startOfHour();
        $lastHour = now()->startOfHour();

        $labels = [];
        $cumulative = [];
        $runningTotal = 0;

        for ($hour = $firstHour->copy(); $hour->lte($lastHour); $hour->addHour()) {
            $key = $hour->format('Y-m-d H:00');
            $runningTotal += $byHour->has($key) ? $byHour[$key]->count() : 0;

            // Backfill zero-vote hours as a flat continuation of the running total
            // rather than omitting them - a flat line reads as "stalled" (meaningful
            // signal), a gap reads as ambiguous "no data" (24-RESEARCH.md Open Question 1).
            $labels[] = $hour->format('H:00');
            $cumulative[] = $runningTotal;
        }

        return [
            'labels' => $labels,
            'datasets' => [[
                'label' => 'Votos acumulados',
                'data' => $cumulative,
                'borderColor' => '#3b82f6',
                'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                'fill' => true,
            ]],
        ];
    }

    protected function getType(): string
    {
        return $this->getChartKind();
    }

    protected function getChartKind(): string
    {
        return 'line';
    }
}

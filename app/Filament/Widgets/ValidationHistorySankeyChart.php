<?php

namespace App\Filament\Widgets;

use App\Models\ValidationHistory;
use App\Services\CampaignContext;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ValidationHistorySankeyChart extends ChartWidget
{
    protected static ?int $sort = 24;

    protected ?string $heading = 'Transiciones de Estado de Apoyos';

    protected ?string $pollingInterval = '120s';

    protected string $view = 'filament.widgets.react-chart';

    private const TOP_N = 8;

    protected function getData(): array
    {
        $activeCampaign = CampaignContext::currentCampaign();

        $emptyPayload = fn (string $reason): array => ['nodes' => [], 'links' => [], 'emptyReason' => $reason];

        if (! $activeCampaign) {
            return $emptyPayload('no_campaign');
        }

        // ValidationHistory has no campaign_id directly - join through voter_id, same pattern as
        // CallContactabilityFunnelChart's VerificationCall join. Guard excludes degenerate
        // previous_status === new_status rows (Pitfall 5 - should not occur in valid data, but a
        // defensive filter avoids a zero-length self-referencing Sankey link).
        $transitions = ValidationHistory::query()
            ->join('voters', 'voters.id', '=', 'validation_histories.voter_id')
            ->where('voters.campaign_id', $activeCampaign->id)
            ->where(function (Builder $q) {
                $q->whereNull('validation_histories.previous_status')
                    ->orWhereColumn('validation_histories.previous_status', '!=', 'validation_histories.new_status');
            })
            ->select('validation_histories.previous_status', 'validation_histories.new_status', DB::raw('COUNT(*) as total'))
            ->groupBy('validation_histories.previous_status', 'validation_histories.new_status')
            ->orderByDesc('total')
            ->get();

        if ($transitions->isEmpty()) {
            return $emptyPayload('no_transitions');
        }

        // D-05: top-N by volume, remainder collapsed into "Otros" edges grouped by source node.
        $kept = $transitions->take(self::TOP_N);
        $excluded = $transitions->slice(self::TOP_N);

        // D-06: null previous_status renders as synthetic "Nuevo" source node.
        $nodeNames = collect(['Nuevo'])
            ->merge($kept->flatMap(fn ($t) => [
                $t->previous_status?->getLabel() ?? 'Nuevo',
                $t->new_status->getLabel(),
            ]))
            ->push('Otros')
            ->unique()
            ->values();

        $nodeIndex = $nodeNames->flip();

        $links = $kept->map(fn ($t) => [
            'source' => $nodeIndex[$t->previous_status?->getLabel() ?? 'Nuevo'],
            'target' => $nodeIndex[$t->new_status->getLabel()],
            'value' => (int) $t->total,
        ])->values();

        if ($excluded->isNotEmpty()) {
            $otrosBySource = $excluded->groupBy(fn ($t) => $t->previous_status?->getLabel() ?? 'Nuevo');
            foreach ($otrosBySource as $sourceLabel => $group) {
                $links->push([
                    'source' => $nodeIndex[$sourceLabel],
                    'target' => $nodeIndex['Otros'],
                    'value' => (int) $group->sum('total'),
                ]);
            }
        }

        return [
            'nodes' => $nodeNames->map(fn ($name) => ['name' => $name])->toArray(),
            'links' => $links->values()->toArray(),
        ];
    }

    protected function getType(): string
    {
        return $this->getChartKind();
    }

    protected function getChartKind(): string
    {
        return 'sankey';
    }
}

<?php

namespace App\Filament\Widgets;

use App\Enums\CallResult;
use App\Models\VerificationCall;
use App\Services\CampaignContext;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class CallerHourHeatmapChart extends ChartWidget
{
    protected static ?int $sort = 26;

    protected ?string $heading = 'Efectividad de Llamadas por Encuestador y Hora';

    protected ?string $pollingInterval = '120s';

    protected string $view = 'filament.widgets.react-chart';

    // D-15: business-hours-only axis (7am-9pm) - a full 24-column grid would be mostly empty
    // since campaign calling doesn't realistically happen overnight.
    private const HOURS = [7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21];

    protected function getData(): array
    {
        $activeCampaign = CampaignContext::currentCampaign();

        if (! $activeCampaign) {
            return ['cells' => [], 'callers' => [], 'hours' => self::HOURS, 'emptyReason' => 'no_campaign'];
        }

        $hourExpr = DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%H', verification_calls.call_date) AS INTEGER)"
            : 'HOUR(verification_calls.call_date)';

        // D-13: numerator uses CallResult::isSuccessfulContact()'s canonical 3 values
        // (ANSWERED, CONFIRMED, CALLBACK_REQUESTED). VerificationCall has no direct campaign_id -
        // join through voter_id, same pattern as CallContactabilityFunnelChart.
        $rows = VerificationCall::query()
            ->join('voters', 'voters.id', '=', 'verification_calls.voter_id')
            ->join('users', 'users.id', '=', 'verification_calls.caller_id')
            ->where('voters.campaign_id', $activeCampaign->id)
            ->select(
                'users.id as caller_id',
                'users.name as caller_name',
                DB::raw("$hourExpr as hour"),
                DB::raw('COUNT(*) as attempts'),
                DB::raw('SUM(CASE WHEN verification_calls.call_result IN (?, ?, ?) THEN 1 ELSE 0 END) as successes')
            )
            ->addBinding([
                CallResult::ANSWERED->value,
                CallResult::CONFIRMED->value,
                CallResult::CALLBACK_REQUESTED->value,
            ], 'select')
            ->groupBy('users.id', 'users.name', DB::raw($hourExpr))
            ->get();

        if ($rows->isEmpty()) {
            return ['cells' => [], 'callers' => [], 'hours' => self::HOURS, 'emptyReason' => 'no_calls'];
        }

        // D-14: every caller becomes a row, no top-N truncation.
        $callers = $rows->pluck('caller_name', 'caller_id')->toArray();

        $cells = $rows->map(fn ($row) => [
            'caller_id' => (int) $row->caller_id,
            'caller_name' => $row->caller_name,
            'hour' => (int) $row->hour,
            'attempts' => (int) $row->attempts,
            // D-16: null = no data (zero attempts), distinct from a real 0%-effectiveness cell.
            'rate' => $row->attempts > 0 ? round($row->successes / $row->attempts * 100, 1) : null,
        ])->toArray();

        return ['cells' => $cells, 'callers' => $callers, 'hours' => self::HOURS];
    }

    protected function getType(): string
    {
        return $this->getChartKind();
    }

    protected function getChartKind(): string
    {
        return 'heatmap';
    }
}

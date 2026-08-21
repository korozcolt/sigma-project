<?php

namespace App\Filament\Widgets;

use App\Models\VerificationCall;
use App\Services\CampaignContext;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

class CallContactabilityFunnelChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Contactabilidad de Llamadas por Intento';

    protected ?string $pollingInterval = '120s';

    protected string $view = 'filament.widgets.react-chart';

    protected function getData(): array
    {
        $activeCampaign = CampaignContext::currentCampaign();

        $emptyPayload = fn (string $reason): array => [
            'labels' => [],
            'datasets' => [['label' => 'Contactabilidad', 'data' => []]],
            'emptyReason' => $reason,
        ];

        if (! $activeCampaign) {
            return $emptyPayload('no_campaign');
        }

        // VerificationCall has no campaign_id directly — join through voter_id.
        $baseQuery = fn (): Builder => VerificationCall::query()
            ->join('voters', 'voters.id', '=', 'verification_calls.voter_id')
            ->where('voters.campaign_id', $activeCampaign->id);

        // D-08: persistence model, DISTINCT voter counts per stage (not call-row counts).
        $intento1 = $baseQuery()->distinct('verification_calls.voter_id')->count('verification_calls.voter_id');

        if ($intento1 === 0) {
            return $emptyPayload('no_calls');
        }

        $intento2 = $baseQuery()
            ->where('verification_calls.attempt_number', '>=', 2)
            ->distinct('verification_calls.voter_id')
            ->count('verification_calls.voter_id');

        $intento3Plus = $baseQuery()
            ->where('verification_calls.attempt_number', '>=', 3)
            ->distinct('verification_calls.voter_id')
            ->count('verification_calls.voter_id');

        $contactado = $baseQuery()
            ->successful()
            ->distinct('verification_calls.voter_id')
            ->count('verification_calls.voter_id');

        return [
            'labels' => ['Intento 1', 'Intento 2', 'Intento 3+', 'Contactado'],
            'datasets' => [['label' => 'Contactabilidad', 'data' => [$intento1, $intento2, $intento3Plus, $contactado]]],
        ];
    }

    protected function getType(): string
    {
        return $this->getChartKind();
    }

    protected function getChartKind(): string
    {
        return 'funnel';
    }
}

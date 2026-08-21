<?php

namespace App\Filament\Widgets;

use App\Models\Message;
use App\Services\CampaignContext;
use Filament\Widgets\ChartWidget;

class MessageDeliveryFunnelChart extends ChartWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Embudo de Entrega de Mensajes';

    protected ?string $pollingInterval = '120s';

    protected string $view = 'filament.widgets.react-chart';

    protected function getData(): array
    {
        $activeCampaign = CampaignContext::currentCampaign();

        $emptyPayload = fn (string $reason): array => [
            'labels' => [],
            'datasets' => [['label' => 'Mensajes', 'data' => []]],
            'emptyReason' => $reason,
        ];

        if (! $activeCampaign) {
            return $emptyPayload('no_campaign');
        }

        // D-09: all Message records historically, campaign-scoped, no batch/date filter.
        $baseQuery = fn () => Message::query()->where('campaign_id', $activeCampaign->id);

        $enviado = $baseQuery()->whereNotNull('sent_at')->count();

        if ($enviado === 0) {
            return $emptyPayload('no_messages');
        }

        // D-09: read/click counted from Message rows directly — MessageBatch only
        // pre-aggregates sent_count/delivered_count/failed_count, has no read/click counter.
        $entregado = $baseQuery()->whereNotNull('delivered_at')->count();
        $leido = $baseQuery()->whereNotNull('read_at')->count();
        $clic = $baseQuery()->whereNotNull('clicked_at')->count();

        return [
            'labels' => ['Enviado', 'Entregado', 'Leído', 'Clic'],
            'datasets' => [['label' => 'Mensajes', 'data' => [$enviado, $entregado, $leido, $clic]]],
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

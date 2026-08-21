<?php

namespace App\Filament\Widgets;

use App\Enums\VoterStatus;
use App\Models\Voter;
use App\Services\CampaignContext;
use Filament\Widgets\ChartWidget;

class VoterHappyPathFunnelChart extends ChartWidget
{
    protected static ?int $sort = 22;

    protected ?string $heading = 'Ruta de Validación — Camino Ideal';

    protected ?string $description = 'Mide cuántos apoyos alcanzaron al menos esta etapa; un apoyo confirmado por Registraduría o llamada también cuenta aquí, aunque nunca tuvo el estado literal Verificado en Censo.';

    protected ?string $pollingInterval = '120s';

    protected string $view = 'filament.widgets.react-chart';

    protected function getData(): array
    {
        $activeCampaign = CampaignContext::currentCampaign();

        $emptyPayload = fn (string $reason): array => [
            'labels' => [],
            'datasets' => [['label' => 'Apoyos', 'data' => []]],
            'emptyReason' => $reason,
        ];

        if (! $activeCampaign) {
            return $emptyPayload('no_campaign');
        }

        // D-01: strict 4-stage happy path, deliberately excludes VERIFIED_REGISTRADURIA/VERIFIED_CALL.
        // Each stage's status set is a strict subset of the previous stage's, so counts are
        // monotonically non-increasing by construction (cumulative "reached at least this point").
        $stages = [
            'Pendiente de Revisión' => [VoterStatus::PENDING_REVIEW, VoterStatus::VERIFIED_CENSUS, VoterStatus::CONFIRMED, VoterStatus::VOTED],
            'Verificado en Censo' => [VoterStatus::VERIFIED_CENSUS, VoterStatus::CONFIRMED, VoterStatus::VOTED],
            'Confirmado' => [VoterStatus::CONFIRMED, VoterStatus::VOTED],
            'Votó' => [VoterStatus::VOTED],
        ];

        $baseQuery = fn () => Voter::query()->where('campaign_id', $activeCampaign->id);

        $counts = [];
        foreach ($stages as $statusSet) {
            $counts[] = $baseQuery()->whereIn('status', array_map(fn (VoterStatus $s) => $s->value, $statusSet))->count();
        }

        if ($counts[0] === 0) {
            return $emptyPayload('no_happy_path_voters');
        }

        return [
            'labels' => array_keys($stages),
            'datasets' => [['label' => 'Apoyos', 'data' => $counts]],
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

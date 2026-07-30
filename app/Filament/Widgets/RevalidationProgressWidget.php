<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\RevalidationRun;
use App\Services\CampaignContext;
use Filament\Widgets\Concerns\CanPoll;
use Filament\Widgets\Widget;

/**
 * Non-blocking progress banner for the "Revalidar apoyos de un líder" background job
 * (App\Jobs\DispatchCensusRevalidation). Reads the latest RevalidationRun for the current
 * campaign; renders nothing when no run exists yet. wire:poll refreshes only this widget,
 * never the Apoyos table itself.
 */
class RevalidationProgressWidget extends Widget
{
    use CanPoll;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.revalidation-progress-widget';

    protected ?string $pollingInterval = '5s';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $campaignId = CampaignContext::currentCampaignId();

        $run = $campaignId
            ? RevalidationRun::query()
                ->where('campaign_id', $campaignId)
                ->latest('started_at')
                ->first()
            : null;

        return [
            'run' => $run,
        ];
    }
}

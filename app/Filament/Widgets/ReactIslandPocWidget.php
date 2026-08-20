<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

/**
 * Throwaway proof-of-concept widget for Phase 20 (React Island Infrastructure).
 * Proves the wire:poll -> dispatch('updateChartData') -> Alpine bridge ->
 * React root.render() cycle works end-to-end in a real browser, across a
 * real poll tick, without ever remounting the React root. Deliberately
 * minimal per D-01 — no MonoCharts visual composition. Superseded by real
 * chart widgets starting Phase 21 (MIGR-01/MIGR-02); safe to delete once
 * this phase's infra is verified and the first real widget migrates.
 */
class ReactIslandPocWidget extends ChartWidget
{
    protected string $view = 'filament.widgets.react-chart';

    protected ?string $pollingInterval = '10s';

    protected function getType(): string
    {
        return $this->getChartKind();
    }

    protected function getChartKind(): string
    {
        return 'poc';
    }

    protected function getData(): array
    {
        $second = now()->second;

        return [
            'points' => [
                ['label' => 'A', 'value' => $second],
                ['label' => 'B', 'value' => ($second + 7) % 60],
                ['label' => 'C', 'value' => ($second + 13) % 60],
            ],
        ];
    }
}

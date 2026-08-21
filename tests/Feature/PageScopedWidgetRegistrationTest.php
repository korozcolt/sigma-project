<?php

declare(strict_types=1);

use App\Filament\Widgets\CallCenterCallsSparklineWidget;
use App\Filament\Widgets\CallCenterStatsOverview;
use App\Filament\Widgets\CallCenterStatsWidget;
use App\Filament\Widgets\CallContactabilityFunnelChart;
use App\Filament\Widgets\CallHistoryTable;
use App\Filament\Widgets\CallQueueTable;
use App\Filament\Widgets\DiaDLiveVotingChart;
use App\Filament\Widgets\DiaDStatsOverview;
use App\Filament\Widgets\DiaDTerritorialProgressTable;
use App\Filament\Widgets\MessageDeliveryFunnelChart;
use App\Filament\Widgets\RevalidationProgressWidget;
use App\Filament\Widgets\SurveyResultsWidget;
use App\Filament\Widgets\SurveyScaleGaugeChart;
use App\Filament\Widgets\SurveyScaleHistogramChart;
use Livewire\Exceptions\ComponentNotFoundException;
use Livewire\Mechanisms\ComponentRegistry;

uses()->group('dashboard-widgets');

test('page-scoped widget resolves via its registered ComponentRegistry alias without throwing', function (string $widgetClass) {
    $registry = app(ComponentRegistry::class);
    $alias = $registry->getName($widgetClass);

    expect(fn () => $registry->new($alias))->not->toThrow(ComponentNotFoundException::class);
})->with([
    RevalidationProgressWidget::class,
    CallCenterStatsOverview::class,
    CallQueueTable::class,
    CallHistoryTable::class,
    DiaDStatsOverview::class,
    DiaDTerritorialProgressTable::class,
    DiaDLiveVotingChart::class,
    SurveyResultsWidget::class,
    CallCenterStatsWidget::class,
    CallCenterCallsSparklineWidget::class,
    CallContactabilityFunnelChart::class,
    MessageDeliveryFunnelChart::class,
    SurveyScaleGaugeChart::class,
    SurveyScaleHistogramChart::class,
]);

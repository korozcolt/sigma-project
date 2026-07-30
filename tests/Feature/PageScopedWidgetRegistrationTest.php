<?php

declare(strict_types=1);

use App\Filament\Widgets\CallCenterStatsOverview;
use App\Filament\Widgets\CallHistoryTable;
use App\Filament\Widgets\CallQueueTable;
use App\Filament\Widgets\DiaDStatsOverview;
use App\Filament\Widgets\DiaDTerritorialProgressTable;
use App\Filament\Widgets\RevalidationProgressWidget;
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
]);

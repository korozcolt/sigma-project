<?php

declare(strict_types=1);

use App\Filament\Widgets\JurisdictionSummaryOverview;
use App\Filament\Widgets\RejectionsCountersOverview;
use Filament\Facades\Filament;

test('admin panel registers navigation groups in the expected order, ending with Sistema', function () {
    $labels = collect(Filament::getPanel('admin')->getNavigationGroups())
        ->map(fn ($group) => $group->getLabel())
        ->toArray();

    expect($labels)->toBe([
        'Gestión',
        'Call Center',
        'Mensajería',
        'Jornada Electoral',
        'Configuración',
        'Sistema',
    ]);
});

test('admin panel registers the rejections counters and jurisdiction summary widgets', function () {
    $widgets = Filament::getPanel('admin')->getWidgets();

    expect($widgets)->toContain(RejectionsCountersOverview::class)
        ->and($widgets)->toContain(JurisdictionSummaryOverview::class);
});

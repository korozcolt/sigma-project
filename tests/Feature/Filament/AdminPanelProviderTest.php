<?php

declare(strict_types=1);

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

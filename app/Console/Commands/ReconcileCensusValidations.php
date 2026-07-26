<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\DispatchCensusRevalidation;
use Illuminate\Console\Command;

class ReconcileCensusValidations extends Command
{
    protected $signature = 'census:reconcile-validation';

    protected $description = 'Re-checks apoyos pendientes o no encontrados en el censo local y encola su validación individual';

    public function handle(): int
    {
        DispatchCensusRevalidation::dispatchSync();

        return self::SUCCESS;
    }
}

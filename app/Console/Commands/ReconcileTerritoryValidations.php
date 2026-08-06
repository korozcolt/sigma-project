<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ReconcileVoterTerritory;
use Illuminate\Console\Command;

class ReconcileTerritoryValidations extends Command
{
    protected $signature = 'census:reconcile-territory';

    protected $description = 'Revalida el alcance territorial de los apoyos y marca/revierte REJECTED_OUT_OF_SCOPE automáticamente';

    public function handle(): int
    {
        ReconcileVoterTerritory::dispatchSync();

        return self::SUCCESS;
    }
}

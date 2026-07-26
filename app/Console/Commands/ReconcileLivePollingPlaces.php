<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ReconcileFallbackPollingPlaces;
use Illuminate\Console\Command;

class ReconcileLivePollingPlaces extends Command
{
    protected $signature = 'census:reconcile-live';

    protected $description = 'Re-attempts live Registraduría lookup for fallback-sourced voters and upgrades them when the live source succeeds';

    public function handle(): int
    {
        ReconcileFallbackPollingPlaces::dispatchSync();

        return self::SUCCESS;
    }
}

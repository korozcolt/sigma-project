<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TwoCaptchaBalanceSnapshot;
use App\Services\TwoCaptchaService;
use Illuminate\Console\Command;

class SnapshotTwoCaptchaBalance extends Command
{
    protected $signature = 'balances:snapshot-2captcha';

    protected $description = 'Guarda un snapshot horario del saldo (USD) de la cuenta 2captcha';

    public function handle(TwoCaptchaService $service): int
    {
        $balance = $service->getBalance();

        if ($balance === null) {
            $this->warn('Saldo 2captcha no disponible; no se guarda snapshot.');

            return self::SUCCESS;
        }

        TwoCaptchaBalanceSnapshot::create([
            'balance' => $balance,
            'checked_at' => now(),
        ]);

        return self::SUCCESS;
    }
}

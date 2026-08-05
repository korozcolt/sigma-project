<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PollingPlaceSource;
use App\Models\Voter;
use App\Services\VoterValidationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillLiveStatusDesync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Historical backfill for the status/polling_place_source desync fixed in
     * ReconcileFallbackPollingPlaces (see .planning/debug/resolved/
     * status-polling-place-source-desync.md): two independent hourly cron jobs
     * (census:reconcile-live and census:reconcile-validation) updated
     * polling_place_source and status separately, so a voter could sit with
     * polling_place_source = LIVE while status stayed non-terminal for hours/days.
     *
     * This command operates PURELY on already-persisted local data — it never calls
     * PollingPlaceResolver::resolveAutomated()/resolveFromPermanentLookup() or any
     * live/2captcha adapter, so it costs nothing to run regardless of how many voters
     * it touches.
     *
     * @var string
     */
    protected $signature = 'census:backfill-live-status-desync {--dry-run : List affected apoyos without writing any changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza el status de apoyos cuyo polling_place_source ya quedó en LIVE pero cuyo status no se actualizó, sin realizar ninguna consulta en vivo/pagada';

    /**
     * Execute the console command.
     */
    public function handle(VoterValidationService $validationService): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $candidates = Voter::query()
            ->where('polling_place_source', PollingPlaceSource::LIVE->value)
            ->get();

        if ($dryRun) {
            $breakdown = $candidates->countBy(fn (Voter $voter) => $voter->status?->value ?? 'null');

            $this->info(sprintf(
                '%d apoyo(s) con polling_place_source = LIVE encontrados (dry-run, sin cambios).',
                $candidates->count()
            ));

            foreach ($breakdown as $status => $count) {
                $this->line("  - {$status}: {$count}");
            }

            return self::SUCCESS;
        }

        $updated = 0;

        DB::transaction(function () use ($candidates, $validationService, &$updated) {
            foreach ($candidates as $voter) {
                $previousStatus = $voter->status;

                $freshVoter = $validationService->updateVoterStatus($voter, found: true);

                if ($freshVoter->status !== $previousStatus) {
                    $updated++;
                }
            }
        });

        $this->info(sprintf(
            '%d de %d apoyo(s) con polling_place_source = LIVE fueron sincronizados de status.',
            $updated,
            $candidates->count()
        ));

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Voter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillPollingTableNumber extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Historical backfill for the bug fixed in PollingPlaceResolver::persist() (see
     * .planning/debug/resolved/polling-place-id-not-persisted-by-resolver.md):
     * persist() never wrote polling_table_number to the Voter even though every
     * resolve*() method already computed it via $result->tableNumber, so voters resolved
     * via the automated cascade (resolveAutomated(), used by ReconcileFallbackPollingPlaces
     * and VoterValidationService) ended up with polling_place_id set but
     * polling_table_number left NULL forever, the exact sibling of the already-fixed
     * polling_place_id bug.
     *
     * This command operates PURELY on already-persisted local data — for each affected
     * voter it re-derives polling_table_number either from the most recent
     * polling_place_resolutions audit row with a non-null table_number, or (when no such
     * history exists) from the single-mesa rule (the linked PollingPlace's max_tables===1).
     * It makes zero live/paid calls by construction, since both recovery paths are plain
     * local Eloquent relations already on Voter/PollingPlace.
     *
     * @var string
     */
    protected $signature = 'census:backfill-polling-table-number {--dry-run : List affected apoyos without writing any changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconstruye polling_table_number para apoyos cuyo puesto de votación ya está enlazado pero cuya mesa nunca quedó guardada, usando solo datos ya locales (historial de resoluciones o la regla de mesa única), sin realizar ninguna consulta en vivo/pagada';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $candidates = Voter::query()
            ->whereNotNull('polling_place_id')
            ->whereNull('polling_table_number')
            ->with('pollingPlace')
            ->get();

        $viaHistory = 0;
        $viaDefault = 0;
        $skipped = 0;

        $process = function () use ($candidates, $dryRun, &$viaHistory, &$viaDefault, &$skipped) {
            foreach ($candidates as $voter) {
                $fromHistory = $voter->pollingPlaceResolutions()
                    ->whereNotNull('table_number')
                    ->recent()
                    ->first();

                if ($fromHistory !== null) {
                    if (! $dryRun) {
                        $voter->update(['polling_table_number' => $fromHistory->table_number]);
                    }

                    $viaHistory++;

                    continue;
                }

                if ($voter->pollingPlace?->max_tables === 1) {
                    if (! $dryRun) {
                        $voter->update(['polling_table_number' => '1']);
                    }

                    $viaDefault++;

                    continue;
                }

                $skipped++;
            }
        };

        if ($dryRun) {
            $process();
        } else {
            DB::transaction($process);
        }

        $this->info(sprintf(
            '%d de %d apoyo(s) %s: %d desde el historial de resoluciones, %d por la regla de mesa única. %d no pudieron re-derivarse con datos locales.%s',
            $viaHistory + $viaDefault,
            $candidates->count(),
            $dryRun ? 'serían actualizados' : 'fueron actualizados',
            $viaHistory,
            $viaDefault,
            $skipped,
            $dryRun ? ' (dry-run, sin cambios)' : ''
        ));

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PollingPlaceSource;
use App\Models\Voter;
use App\Services\PollingPlaceResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillPollingPlaceId extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Historical backfill for the bug fixed in PollingPlaceResolver::persist() (see
     * .planning/debug/resolved/polling-place-id-not-persisted-by-resolver.md):
     * persist() never wrote polling_place_id to the Voter even though every resolve*()
     * method already computed it, so voters resolved via the automated cascade
     * (resolveAutomated(), used by ReconcileFallbackPollingPlaces and
     * VoterValidationService) ended up with polling_place_source set but
     * polling_place_id left NULL forever.
     *
     * This command operates PURELY on already-persisted local data — for each affected
     * voter it re-derives polling_place_id via the ONE local-read-only resolve*() method
     * matching that voter's existing polling_place_source (LIVE -> resolveFromPermanentLookup,
     * SNAPSHOT -> resolveFromNationalSnapshot, DB_RECONSTRUCTION -> resolveFromCampaignCensus).
     * It never calls resolveAutomated()/startLiveLookup()/isLiveReachable() or anything
     * touching a live/2captcha adapter, so it costs nothing to run regardless of how many
     * voters it touches. MANUAL-sourced voters are skipped — there is no local read that can
     * re-derive a manually-assigned polling place, and a MANUAL source with no FK could only
     * mean the FK was genuinely never set, not a resolver bug.
     *
     * @var string
     */
    protected $signature = 'census:backfill-polling-place-id {--dry-run : List affected apoyos without writing any changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconstruye polling_place_id para apoyos cuyo polling_place_source ya está resuelto pero cuyo puesto de votación nunca quedó enlazado, sin realizar ninguna consulta en vivo/pagada';

    /**
     * Execute the console command.
     */
    public function handle(PollingPlaceResolver $resolver): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $candidates = Voter::query()
            ->whereNotNull('polling_place_source')
            ->whereNull('polling_place_id')
            ->get();

        if ($dryRun) {
            $breakdown = $candidates->countBy(fn (Voter $voter) => $voter->polling_place_source?->value ?? 'null');

            $this->info(sprintf(
                '%d apoyo(s) con polling_place_source resuelto pero polling_place_id nulo encontrados (dry-run, sin cambios).',
                $candidates->count()
            ));

            foreach ($breakdown as $source => $count) {
                $this->line("  - {$source}: {$count}");
            }

            return self::SUCCESS;
        }

        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use ($candidates, $resolver, &$updated, &$skipped) {
            foreach ($candidates as $voter) {
                $result = match ($voter->polling_place_source) {
                    PollingPlaceSource::LIVE => $resolver->resolveFromPermanentLookup($voter->document_number),
                    PollingPlaceSource::SNAPSHOT => $resolver->resolveFromNationalSnapshot($voter->document_number),
                    PollingPlaceSource::DB_RECONSTRUCTION => $resolver->resolveFromCampaignCensus($voter->document_number),
                    default => null,
                };

                if ($result === null || $result->pollingPlaceId === null) {
                    $skipped++;

                    continue;
                }

                $voter->update(['polling_place_id' => $result->pollingPlaceId]);
                $updated++;
            }
        });

        $this->info(sprintf(
            '%d de %d apoyo(s) fueron enlazados a su puesto de votación. %d no pudieron re-derivarse con datos locales.',
            $updated,
            $candidates->count(),
            $skipped
        ));

        return self::SUCCESS;
    }
}

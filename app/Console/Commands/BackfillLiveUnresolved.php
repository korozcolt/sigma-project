<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PollingPlaceSource;
use App\Models\Municipality;
use App\Models\RegistraduriaLookup;
use App\Models\Voter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillLiveUnresolved extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Historical remediation for the bug fixed in PollingPlaceResolver::persist() (see
     * .planning/debug/resolved/apoyo-marcado-en-vivo-con-puesto-sin-resolver.md):
     * persist() used to write polling_place_source = LIVE and polling_place_resolved_at
     * unconditionally, even when resolveOrCreatePollingPlace() failed to match the
     * municipality and polling_place_id stayed null — leaving affected voters showing the
     * "En Vivo" badge with "Sin resolver" everywhere else, and permanently excluded from
     * ReconcileFallbackPollingPlaces's candidate query (which skips source = LIVE), so they
     * never got retried.
     *
     * This command reverts those voters to an unresolved state (clears
     * polling_place_source/polling_place_resolved_at, resets reconciliation_attempts and
     * reconciliation_exhausted_at) so census:reconcile-live's normal hourly cycle picks
     * them up again and re-resolves them for real — now with resolveOrCreatePollingPlace()'s
     * normalized municipality matching already fixed. It performs no live/2captcha call
     * itself; re-resolution happens on the next scheduled reconciliation run.
     *
     * @var string
     */
    protected $signature = 'census:backfill-live-unresolved {--dry-run : List affected apoyos without writing any changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Revierte a estado no resuelto los apoyos marcados polling_place_source=live cuyo polling_place_id nunca quedó enlazado, para que el ciclo normal de reconciliación los vuelva a resolver';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $candidates = Voter::query()
            ->where('polling_place_source', PollingPlaceSource::LIVE->value)
            ->whereNull('polling_place_id')
            ->get();

        if ($dryRun) {
            $this->info(sprintf(
                '%d apoyo(s) con polling_place_source = LIVE pero polling_place_id nulo encontrados (dry-run, sin cambios).',
                $candidates->count()
            ));

            // Reports, per voter, whether Municipality::findByFuzzyName()'s normalized
            // matching (the fix in this same debug session) would resolve the municipio
            // string already cached in registraduria_lookups — lets an operator see the
            // FULL list of real mismatch patterns (not just a handful of manually-inspected
            // examples) before deciding whether any residual failures need a catalog fix
            // (missing Municipality) vs. a further normalization improvement, without
            // writing anything.
            foreach ($candidates as $voter) {
                $lookup = RegistraduriaLookup::query()->where('document_number', $voter->document_number)->first();
                $rawMunicipio = $lookup->municipio ?? null;
                $match = filled($rawMunicipio) ? Municipality::findByFuzzyName($rawMunicipio) : null;

                $status = match (true) {
                    blank($rawMunicipio) => 'sin registraduria_lookups.municipio (no re-derivable localmente)',
                    $match !== null => "resolvería a \"{$match->name}\" (#{$match->id}) tras el fix de normalización",
                    default => "\"{$rawMunicipio}\" sigue sin match — requiere revisión manual",
                };

                $this->line("  - voter #{$voter->id} (documento {$voter->document_number}): {$status}");
            }

            return self::SUCCESS;
        }

        DB::transaction(function () use ($candidates) {
            foreach ($candidates as $voter) {
                $voter->update([
                    'polling_place_source' => null,
                    'polling_place_resolved_at' => null,
                    'reconciliation_attempts' => 0,
                    'reconciliation_exhausted_at' => null,
                ]);
            }
        });

        $this->info(sprintf(
            '%d apoyo(s) revertidos a estado no resuelto. El próximo ciclo de census:reconcile-live los volverá a tomar.',
            $candidates->count()
        ));

        return self::SUCCESS;
    }
}

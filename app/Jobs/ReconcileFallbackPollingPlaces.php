<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\PollingPlaceSource;
use App\Models\RegistraduriaLiveSession;
use App\Models\Voter;
use App\Services\PollingPlaceResolver;
use App\Services\VoterValidationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ReconcileFallbackPollingPlaces implements ShouldQueue
{
    use Queueable;

    private const MAX_VOTERS_PER_RUN = 50;

    private const MAX_ATTEMPTS_BEFORE_EXHAUSTION = 5;

    public function handle(PollingPlaceResolver $resolver, VoterValidationService $validationService): void
    {
        Log::info('census.reconcile.started');

        if (! $resolver->isLiveReachable()) {
            Log::warning('census.reconcile.skipped_unreachable');

            return;
        }

        $voters = Voter::query()
            ->where(function ($query) {
                $query->whereNull('polling_place_source')
                    ->orWhere('polling_place_source', '!=', PollingPlaceSource::LIVE->value);
            })
            ->whereNull('reconciliation_exhausted_at')
            ->orderBy('polling_place_resolved_at')
            ->limit(self::MAX_VOTERS_PER_RUN)
            ->get();

        $upgraded = 0;
        $failed = 0;
        $newlyExhausted = 0;
        $pendingCollection = 0;

        foreach ($voters as $voter) {
            $result = $resolver->resolveAutomated($voter->document_number, $voter, resolvedVia: 'reconciliation');

            if ($result !== null && $result->source === PollingPlaceSource::LIVE) {
                $voter->update([
                    'reconciliation_attempts' => 0,
                    'reconciliation_exhausted_at' => null,
                ]);

                // Reuse the already-fetched (already-paid-for) LIVE result to also sync
                // `status` in the same pass, closing the desync where polling_place_source
                // reached LIVE while status stayed behind (e.g. PENDING_REVIEW) because this
                // job and census:reconcile-validation are two independent, uncoordinated cron
                // jobs. No new resolver/live-adapter call is made here — updateVoterStatus()
                // only writes to voters/validation_history and no-ops via its own
                // NON_DOWNGRADABLE_STATUSES guard for voters already past this point.
                $validationService->updateVoterStatus($voter, found: true);

                $upgraded++;

                continue;
            }

            // A RegistraduriaLiveSession row means a real (already-paid-for) live attempt
            // for this document_number is still being collected in the background —
            // either PollingPlaceResolver just dispatched CollectRegistraduriaLookupResult
            // for THIS run's synchronous timeout, or another concurrent attempt (the
            // sibling census:reconcile-validation cron, or an interactive lookup) already
            // claimed it. Either way, this run's null/non-LIVE result was NOT a genuine
            // failure — don't burn a reconciliation attempt on it. The collector job (or
            // whichever process holds the claim) applies the bump itself once it reaches a
            // true terminal outcome or its collection window expires. See
            // .planning/debug/resolved/2captcha-duplicate-spend.md.
            if (RegistraduriaLiveSession::where('document_number', $voter->document_number)->exists()) {
                $pendingCollection++;
                Log::info('census.reconcile.voter_pending_collection', ['voter_id' => $voter->id]);

                continue;
            }

            $attempts = $voter->reconciliation_attempts + 1;
            $isExhausted = $attempts >= self::MAX_ATTEMPTS_BEFORE_EXHAUSTION;

            $voter->update([
                'reconciliation_attempts' => $attempts,
                'reconciliation_exhausted_at' => $isExhausted ? now() : null,
            ]);

            $failed++;

            if ($isExhausted) {
                $newlyExhausted++;
                Log::info('census.reconcile.voter_exhausted', ['voter_id' => $voter->id]);
            }
        }

        Log::info('census.reconcile.completed', [
            'considered' => $voters->count(),
            'upgraded' => $upgraded,
            'failed' => $failed,
            'newly_exhausted' => $newlyExhausted,
            'pending_collection' => $pendingCollection,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('census.reconcile.failed', ['error' => $exception->getMessage()]);
    }
}

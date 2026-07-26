<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\PollingPlaceSource;
use App\Models\Voter;
use App\Services\PollingPlaceResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ReconcileFallbackPollingPlaces implements ShouldQueue
{
    use Queueable;

    private const int MAX_VOTERS_PER_RUN = 50;

    private const int MAX_ATTEMPTS_BEFORE_EXHAUSTION = 5;

    public function handle(PollingPlaceResolver $resolver): void
    {
        Log::info('census.reconcile.started');

        if (! $resolver->isLiveReachable()) {
            Log::warning('census.reconcile.skipped_unreachable');

            return;
        }

        $voters = Voter::query()
            ->whereNotNull('polling_place_source')
            ->where('polling_place_source', '!=', PollingPlaceSource::LIVE->value)
            ->whereNull('reconciliation_exhausted_at')
            ->orderBy('polling_place_resolved_at')
            ->limit(self::MAX_VOTERS_PER_RUN)
            ->get();

        $upgraded = 0;
        $failed = 0;
        $newlyExhausted = 0;

        foreach ($voters as $voter) {
            $result = $resolver->resolveAutomated($voter->document_number, $voter, resolvedVia: 'reconciliation');

            if ($result !== null && $result->source === PollingPlaceSource::LIVE) {
                $voter->update([
                    'reconciliation_attempts' => 0,
                    'reconciliation_exhausted_at' => null,
                ]);
                $upgraded++;

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
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('census.reconcile.failed', ['error' => $exception->getMessage()]);
    }
}

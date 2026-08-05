<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\VoterStatus;
use App\Models\RevalidationRun;
use App\Models\Voter;
use App\Services\VoterValidationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DispatchCensusRevalidation implements ShouldQueue
{
    use Queueable;

    private const MAX_VOTERS_PER_RUN = 50;

    private const MAX_ATTEMPTS_BEFORE_EXHAUSTION = 5;

    public function __construct(
        public ?int $leaderId = null,
        public ?int $campaignId = null,
    ) {}

    /**
     * Process the widened voter selection inline (this job is already ShouldQueue, so the
     * loop runs off the HTTP request = non-blocking to the UI). A single pass over
     * VoterValidationService::validateAndUpdate() resolves BOTH census status AND
     * polling_place_source for each voter, since it now delegates to
     * PollingPlaceResolver::resolveAutomated() internally.
     *
     * Reuses Voter's `reconciliation_attempts`/`reconciliation_exhausted_at` columns (added by
     * migration 2026_07_26_120000_add_reconciliation_fields_to_voters_table.php for the sibling
     * ReconcileFallbackPollingPlaces job) rather than adding a second, parallel counter. Both
     * jobs ultimately drive the same underlying PollingPlaceResolver automated cascade for the
     * same document_number, so "attempts to resolve this voter automatically" is a single shared
     * concept regardless of which hourly job triggered it. Sharing the counter also means a
     * voter that's genuinely unresolvable stops being double-processed (once per job, per hour)
     * once exhausted.
     */
    public function handle(): void
    {
        $voters = Voter::query()
            ->where(function ($query) {
                $query->whereIn('status', [
                    VoterStatus::PENDING_REVIEW->value,
                    VoterStatus::CENSUS_NOT_FOUND->value,
                    VoterStatus::REJECTED_CENSUS->value,
                ])->orWhereNull('polling_place_source');
            })
            ->when($this->leaderId, fn ($query) => $query->where('registered_by', $this->leaderId))
            ->when($this->campaignId, fn ($query) => $query->where('campaign_id', $this->campaignId))
            ->whereNull('reconciliation_exhausted_at')
            ->limit(self::MAX_VOTERS_PER_RUN)
            ->get();

        $run = RevalidationRun::create([
            'campaign_id' => $this->campaignId,
            'leader_id' => $this->leaderId,
            'started_at' => now(),
            'total' => $voters->count(),
        ]);

        $validationService = app(VoterValidationService::class);

        $processed = 0;
        $changed = 0;

        foreach ($voters as $voter) {
            $previousStatus = $voter->status;

            $result = $validationService->validateAndUpdate($voter);

            $processed++;

            if ($result['voter']->status !== $previousStatus) {
                $changed++;
            }

            $this->updateReconciliationTracking($result['voter'], $result['found']);
        }

        $run->update([
            'processed' => $processed,
            'changed' => $changed,
            'finished_at' => now(),
        ]);

        Log::info('census.revalidation.dispatched', [
            'leader_id' => $this->leaderId,
            'campaign_id' => $this->campaignId,
            'count' => $voters->count(),
            'changed' => $changed,
        ]);
    }

    /**
     * Reset the shared attempts/exhaustion counter on a found resolution, or increment it (and
     * mark the voter exhausted after MAX_ATTEMPTS_BEFORE_EXHAUSTION consecutive failures) on a
     * not-found resolution. Mirrors ReconcileFallbackPollingPlaces's identical pattern.
     */
    private function updateReconciliationTracking(Voter $voter, bool $found): void
    {
        if ($found) {
            $voter->update([
                'reconciliation_attempts' => 0,
                'reconciliation_exhausted_at' => null,
            ]);

            return;
        }

        $attempts = $voter->reconciliation_attempts + 1;
        $isExhausted = $attempts >= self::MAX_ATTEMPTS_BEFORE_EXHAUSTION;

        $voter->update([
            'reconciliation_attempts' => $attempts,
            'reconciliation_exhausted_at' => $isExhausted ? now() : null,
        ]);

        if ($isExhausted) {
            Log::info('census.revalidation.voter_exhausted', ['voter_id' => $voter->id]);
        }
    }
}

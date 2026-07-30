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
}

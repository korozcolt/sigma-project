<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\VoterStatus;
use App\Models\Voter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DispatchCensusRevalidation implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ?int $leaderId = null,
    ) {}

    public function handle(): void
    {
        $voters = Voter::query()
            ->whereIn('status', [VoterStatus::PENDING_REVIEW->value, VoterStatus::CENSUS_NOT_FOUND->value])
            ->when($this->leaderId, fn ($query) => $query->where('registered_by', $this->leaderId))
            ->get();

        foreach ($voters as $voter) {
            ValidateVoterAgainstCensus::dispatch($voter);
        }

        Log::info('census.revalidation.dispatched', [
            'leader_id' => $this->leaderId,
            'count' => $voters->count(),
        ]);
    }
}

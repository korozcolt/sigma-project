<?php

namespace App\Jobs;

use App\Models\Voter;
use App\Services\VoterValidationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ValidateVoterAgainstCensus implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Voter $voter
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(VoterValidationService $validationService): void
    {
        $validationService->validateAndUpdate($this->voter);
    }
}

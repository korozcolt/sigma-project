<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\VoterStatus;
use App\Models\ElectionEvent;
use App\Models\ValidationHistory;
use App\Models\Voter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class FinalizeElectionEvent implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $electionEventId,
        public int $validatedByUserId,
    ) {
        //
    }

    public function handle(): void
    {
        Log::info('election_event.finalize.started', ['election_event_id' => $this->electionEventId]);

        $event = ElectionEvent::find($this->electionEventId);

        if (! $event) {
            Log::warning('election_event.finalize.skipped_missing_event', ['election_event_id' => $this->electionEventId]);

            return;
        }

        $eventId = $event->id;
        $closedCount = 0;

        Voter::query()
            ->where('campaign_id', $event->campaign_id)
            ->whereIn('status', [
                VoterStatus::VERIFIED_CALL->value,
                VoterStatus::CONFIRMED->value,
            ])
            ->whereDoesntHave('voteRecords', function ($query) use ($eventId) {
                $query->where('election_event_id', $eventId);
            })
            ->chunkById(500, function ($voters) use ($event, &$closedCount) {
                foreach ($voters as $voter) {
                    $previous = $voter->status;

                    $voter->update([
                        'status' => VoterStatus::DID_NOT_VOTE,
                        'voted_at' => null,
                    ]);

                    ValidationHistory::create([
                        'voter_id' => $voter->id,
                        'previous_status' => $previous,
                        'new_status' => VoterStatus::DID_NOT_VOTE,
                        'validated_by' => $this->validatedByUserId,
                        'validation_type' => 'election',
                        'notes' => "Cierre automático de evento: {$event->name} (sin registro Día D)",
                    ]);

                    $closedCount++;
                }
            });

        Log::info('election_event.finalize.completed', [
            'election_event_id' => $this->electionEventId,
            'voters_closed' => $closedCount,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('election_event.finalize.failed', [
            'election_event_id' => $this->electionEventId,
            'validated_by_user_id' => $this->validatedByUserId,
            'error' => $exception->getMessage(),
        ]);
    }
}

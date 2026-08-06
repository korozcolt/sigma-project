<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\VoterStatus;
use App\Models\Campaign;
use App\Models\ValidationHistory;
use App\Models\Voter;
use App\Services\VoterTerritoryScope;
use App\Services\VoterValidationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ReconcileVoterTerritory implements ShouldQueue
{
    use Queueable;

    private const MAX_VOTERS_PER_RUN = 50;

    /**
     * Marks/reverts voters' `REJECTED_OUT_OF_SCOPE` status based on whether they currently
     * fall within their campaign's defined territory (municipality/department). Mirrors the
     * sibling census reconciliation jobs' structure (bounded per-run budget, Log::info summary).
     */
    public function handle(VoterTerritoryScope $territoryScope, VoterValidationService $validationService): void
    {
        $remainingBudget = self::MAX_VOTERS_PER_RUN;
        $markedOutOfScope = 0;
        $revertedToPending = 0;

        $applicableCampaigns = Campaign::query()->get()
            ->filter(fn (Campaign $campaign): bool => $territoryScope->isTerritoryDefined($campaign));

        foreach ($applicableCampaigns as $campaign) {
            if ($remainingBudget <= 0) {
                break;
            }

            // inRandomOrder(), not orderBy(id)/no-order: with more voters per campaign than
            // MAX_VOTERS_PER_RUN (the common case — e.g. sigma-betha has ~1100), a deterministic
            // order would select the exact same first N voters every hourly run forever, never
            // reaching the rest. Random sampling gives every voter a chance each run, so coverage
            // approaches completeness over enough runs instead of permanently stalling.
            $voters = Voter::query()
                ->where('campaign_id', $campaign->id)
                ->with('municipality')
                ->inRandomOrder()
                ->limit($remainingBudget)
                ->get();

            $remainingBudget -= $voters->count();

            foreach ($voters as $voter) {
                $withinScope = $territoryScope->isWithinCampaignScope($voter, $campaign);

                if (! $withinScope
                    && $voter->status !== VoterStatus::REJECTED_OUT_OF_SCOPE
                    && ! $validationService->isProtectedStatus($voter->status)
                ) {
                    $previousStatus = $voter->status;

                    $voter->update(['status' => VoterStatus::REJECTED_OUT_OF_SCOPE]);

                    ValidationHistory::create([
                        'voter_id' => $voter->id,
                        'previous_status' => $previousStatus,
                        'new_status' => VoterStatus::REJECTED_OUT_OF_SCOPE,
                        'validated_by' => null,
                        'validation_type' => 'territory',
                        'notes' => 'Rechazado automáticamente: el apoyo quedó fuera del alcance territorial (municipio/departamento) definido para la campaña',
                    ]);

                    $markedOutOfScope++;

                    continue;
                }

                if ($withinScope && $voter->status === VoterStatus::REJECTED_OUT_OF_SCOPE) {
                    $previousStatus = $voter->status;

                    $voter->update(['status' => VoterStatus::PENDING_REVIEW]);

                    ValidationHistory::create([
                        'voter_id' => $voter->id,
                        'previous_status' => $previousStatus,
                        'new_status' => VoterStatus::PENDING_REVIEW,
                        'validated_by' => null,
                        'validation_type' => 'territory',
                        'notes' => 'Revertido a pendiente de revisión: el apoyo volvió a estar dentro del alcance territorial de la campaña',
                    ]);

                    $revertedToPending++;
                }
            }
        }

        Log::info('census.territory.completed', [
            'marked_out_of_scope' => $markedOutOfScope,
            'reverted_to_pending' => $revertedToPending,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('census.territory.failed', ['error' => $exception->getMessage()]);
    }
}

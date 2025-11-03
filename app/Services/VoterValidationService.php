<?php

namespace App\Services;

use App\Enums\VoterStatus;
use App\Models\CensusRecord;
use App\Models\Voter;
use Illuminate\Support\Facades\DB;

class VoterValidationService
{
    /**
     * Validate a voter against the census.
     *
     * @return array{found: bool, match: ?CensusRecord, confidence: string}
     */
    public function validateAgainstCensus(Voter $voter): array
    {
        $censusRecord = $this->findInCensus($voter);

        if ($censusRecord) {
            return [
                'found' => true,
                'match' => $censusRecord,
                'confidence' => 'high',
            ];
        }

        return [
            'found' => false,
            'match' => null,
            'confidence' => 'none',
        ];
    }

    /**
     * Find a voter in the census by document number.
     */
    protected function findInCensus(Voter $voter): ?CensusRecord
    {
        return CensusRecord::where('campaign_id', $voter->campaign_id)
            ->where('document_number', $voter->document_number)
            ->first();
    }

    /**
     * Update voter status based on census validation.
     */
    public function updateVoterStatus(Voter $voter, bool $found): Voter
    {
        if ($found) {
            $voter->update([
                'status' => VoterStatus::VERIFIED_CENSUS,
                'census_validated_at' => now(),
            ]);
        } else {
            $voter->update([
                'status' => VoterStatus::REJECTED_CENSUS,
                'notes' => 'No se encontró en el censo electoral',
            ]);
        }

        return $voter->fresh();
    }

    /**
     * Validate and update a single voter.
     *
     * @return array{voter: Voter, found: bool, match: ?CensusRecord}
     */
    public function validateAndUpdate(Voter $voter): array
    {
        $result = $this->validateAgainstCensus($voter);

        $updatedVoter = $this->updateVoterStatus($voter, $result['found']);

        return [
            'voter' => $updatedVoter,
            'found' => $result['found'],
            'match' => $result['match'],
        ];
    }

    /**
     * Validate all pending voters for a campaign.
     *
     * @return array{validated: int, found: int, rejected: int}
     */
    public function validatePendingVoters(int $campaignId): array
    {
        $pendingVoters = Voter::where('campaign_id', $campaignId)
            ->where('status', VoterStatus::PENDING_REVIEW)
            ->get();

        $validated = 0;
        $found = 0;
        $rejected = 0;

        DB::beginTransaction();

        try {
            foreach ($pendingVoters as $voter) {
                $result = $this->validateAgainstCensus($voter);

                $this->updateVoterStatus($voter, $result['found']);

                $validated++;

                if ($result['found']) {
                    $found++;
                } else {
                    $rejected++;
                }
            }

            DB::commit();

            return [
                'validated' => $validated,
                'found' => $found,
                'rejected' => $rejected,
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            throw $e;
        }
    }

    /**
     * Check if a document number exists in the census for a campaign.
     */
    public function documentExistsInCensus(int $campaignId, string $documentNumber): bool
    {
        return CensusRecord::where('campaign_id', $campaignId)
            ->where('document_number', $documentNumber)
            ->exists();
    }

    /**
     * Get census information for a voter.
     */
    public function getCensusInfo(Voter $voter): ?CensusRecord
    {
        return $this->findInCensus($voter);
    }
}

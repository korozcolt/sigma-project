<?php

namespace App\Services;

use App\Enums\VoterStatus;
use App\Models\CensusRecord;
use App\Models\NationalCensusRecord;
use App\Models\NationalIdentityRecord;
use App\Models\RegistraduriaLookup;
use App\Models\ValidationHistory;
use App\Models\Voter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VoterValidationService
{
    /**
     * Statuses that represent a stronger, post-verification/Day-D operational state.
     * Census validation must never clobber these, regardless of found/not-found outcome.
     *
     * @var array<int, VoterStatus>
     */
    private const NON_DOWNGRADABLE_STATUSES = [
        VoterStatus::VERIFIED_REGISTRADURIA,
        VoterStatus::VERIFIED_CALL,
        VoterStatus::CONFIRMED,
        VoterStatus::VOTED,
        VoterStatus::DID_NOT_VOTE,
        VoterStatus::DUPLICATE,
        VoterStatus::CORRECTION_REQUIRED,
    ];

    public function __construct(
        private readonly PollingPlaceResolver $resolver = new PollingPlaceResolver([]),
    ) {}

    /**
     * Validate a voter against the census.
     *
     * Delegates to the SAME automated cascade PollingPlaceResolver already uses for
     * polling-place resolution (permanent registraduria_lookups cache -> live adapters ->
     * national census snapshot), so a single pass resolves BOTH census status AND
     * polling_place_source. A cedula present in national_identity_records also counts as
     * census-found even when no polling place is resolvable. The orphaned per-campaign
     * `census_records` table is intentionally NOT consulted here (see root-cause writeup
     * at .planning/debug/apoyos-mass-rejected-census-betha.md).
     *
     * @return array{found: bool, match: ?CensusRecord, confidence: string}
     */
    public function validateAgainstCensus(Voter $voter): array
    {
        $result = $this->resolver->resolveAutomated(
            $voter->document_number,
            $voter,
            resolvedVia: 'census_validation'
        );

        $found = $result !== null
            || NationalIdentityRecord::query()->where('cedula', $voter->document_number)->exists();

        return [
            'found' => $found,
            'match' => null,
            'confidence' => $found ? 'high' : 'none',
        ];
    }

    /**
     * Find a voter in the census by document number.
     *
     * @deprecated Vestigial — the orphaned per-campaign census_records table is no longer
     * the source of truth for census validation (see validateAgainstCensus()). Left in
     * place only for getCensusInfo()'s existing callers/tests; not consulted by the
     * validation flow itself.
     */
    protected function findInCensus(Voter $voter): ?CensusRecord
    {
        return CensusRecord::where('campaign_id', $voter->campaign_id)
            ->where('document_number', $voter->document_number)
            ->first();
    }

    /**
     * Update voter status based on census validation.
     *
     * No-downgrade guard: if the voter is already in one of the NON_DOWNGRADABLE_STATUSES
     * (stronger, post-verification/Day-D states), leave it untouched — no status mutation,
     * no ValidationHistory row — regardless of the found/not-found result.
     */
    /**
     * Whether a status represents a stronger, post-verification/Day-D operational state that
     * no automated validation (census OR territory) should ever downgrade. Public so other
     * automated-validation consumers (e.g. ReconcileVoterTerritory) can reuse the same guard
     * without duplicating the protected-status list.
     */
    public function isProtectedStatus(?VoterStatus $status): bool
    {
        return $status !== null && in_array($status, self::NON_DOWNGRADABLE_STATUSES, true);
    }

    public function updateVoterStatus(Voter $voter, bool $found): Voter
    {
        $previousStatus = $voter->status;

        if ($this->isProtectedStatus($previousStatus)) {
            return $voter->fresh();
        }

        if ($found) {
            $voter->update([
                'status' => VoterStatus::VERIFIED_CENSUS,
                'census_validated_at' => now(),
            ]);
        } else {
            $voter->update([
                'status' => VoterStatus::CENSUS_NOT_FOUND,
                'notes' => 'No se encontró en el censo electoral ni en los registros de identidad nacional; queda pendiente de reconciliación',
            ]);
        }

        ValidationHistory::create([
            'voter_id' => $voter->id,
            'previous_status' => $previousStatus,
            'new_status' => $voter->status,
            'validated_by' => Auth::id(),
            'validation_type' => 'census',
            'notes' => $found
                ? 'Validado exitosamente contra el censo electoral'
                : 'No se encontró en el censo electoral ni en los registros de identidad nacional',
        ]);

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
            ->where('status', VoterStatus::PENDING_REVIEW->value)
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
     *
     * Stops consulting the orphaned per-campaign `census_records` table — returns true if
     * the cedula is in the permanent Registraduría lookup cache, the national census
     * snapshot, or the national identity roll.
     */
    public function documentExistsInCensus(int $campaignId, string $documentNumber): bool
    {
        return RegistraduriaLookup::query()->where('document_number', $documentNumber)->exists()
            || NationalCensusRecord::query()->where('document_number', $documentNumber)->exists()
            || NationalIdentityRecord::query()->where('cedula', $documentNumber)->exists();
    }

    /**
     * Get census information for a voter.
     *
     * @deprecated Vestigial — reads from the orphaned per-campaign census_records table,
     * no longer the source of truth for census validation (see validateAgainstCensus()).
     */
    public function getCensusInfo(Voter $voter): ?CensusRecord
    {
        return $this->findInCensus($voter);
    }
}

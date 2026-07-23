<?php

namespace App\Services;

use App\Models\Voter;
use Illuminate\Support\Facades\DB;

class VoterDuplicateAssignmentService
{
    public function nextSequenceFor(string $documentNumber): int
    {
        return DB::transaction(function () use ($documentNumber) {
            // document_number uniqueness (and therefore duplicate_sequence assignment) is
            // enforced globally across campaigns at the database level (see the
            // 2026_01_19_000001_make_voter_document_number_globally_unique migration), so
            // this lookup must bypass the campaign-scoped global scope to avoid colliding
            // with the unique(document_number, duplicate_sequence) constraint when a
            // duplicate is created under a different campaign context.
            $maxSequence = Voter::withoutGlobalScopes()
                ->withTrashed()
                ->where('document_number', $documentNumber)
                ->lockForUpdate()
                ->max('duplicate_sequence');

            return $maxSequence === null ? 0 : ((int) $maxSequence) + 1;
        });
    }
}

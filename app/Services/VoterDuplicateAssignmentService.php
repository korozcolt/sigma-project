<?php

namespace App\Services;

use App\Models\Voter;
use Illuminate\Support\Facades\DB;

class VoterDuplicateAssignmentService
{
    public function nextSequenceFor(string $documentNumber): int
    {
        return DB::transaction(function () use ($documentNumber) {
            $maxSequence = Voter::withTrashed()
                ->where('document_number', $documentNumber)
                ->lockForUpdate()
                ->max('duplicate_sequence');

            return $maxSequence === null ? 0 : ((int) $maxSequence) + 1;
        });
    }
}

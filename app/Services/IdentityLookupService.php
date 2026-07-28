<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\NationalIdentityRecord;

class IdentityLookupService
{
    /**
     * Single shared lookup used by every document_number -> name autofill touch point
     * (Coordinador, Líder, and Apoyo creation forms, both Filament and Volt).
     */
    public function findByDocumentNumber(string $documentNumber): ?NationalIdentityRecord
    {
        return NationalIdentityRecord::query()
            ->where('cedula', $documentNumber)
            ->first();
    }
}

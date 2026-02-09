<?php

namespace App\Services;

use App\Models\Voter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class VoterDuplicateReport
{
    /**
     * @return array<int, string>
     */
    public function parseDocumentNumbers(string $path, string $disk = 'local'): array
    {
        $fullPath = Storage::disk($disk)->path($path);

        if (! is_file($fullPath)) {
            return [];
        }

        $handle = fopen($fullPath, 'r');
        if (! $handle) {
            return [];
        }

        $documents = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (! is_array($row) || count($row) === 0) {
                continue;
            }

            $value = trim((string) ($row[0] ?? ''));
            if ($value === '') {
                continue;
            }

            $normalized = preg_replace('/\\D+/', '', $value) ?? '';
            if ($normalized === '') {
                continue;
            }

            if ($this->isHeaderValue($value, $normalized)) {
                continue;
            }

            $documents[$normalized] = $normalized;
        }

        fclose($handle);

        return array_values($documents);
    }

    /**
     * @param array<int, string> $documentNumbers
     * @return array<int, array<int, string>>
     */
    public function buildRows(
        array $documentNumbers,
        int $campaignId,
        bool $includeFound = true,
        bool $includeMissing = false
    ): array
    {
        if (empty($documentNumbers)) {
            return [];
        }

        $rows = [];
        $foundDocuments = [];

        if ($includeFound) {
            Voter::query()
                ->whereIn('document_number', $documentNumbers)
                ->where('campaign_id', $campaignId)
                ->with(['registeredBy', 'campaign'])
                ->orderBy('document_number')
                ->chunk(1000, function (Collection $voters) use (&$rows, &$foundDocuments): void {
                    foreach ($voters as $voter) {
                        $document = (string) $voter->document_number;
                        $foundDocuments[$document] = true;
                        $rows[] = [
                            $document,
                            $voter->registeredBy?->name ?? 'N/A',
                            $voter->campaign?->name ?? 'N/A',
                        ];
                    }
                });
        }

        if ($includeMissing) {
            foreach ($documentNumbers as $document) {
                if (isset($foundDocuments[$document])) {
                    continue;
                }

                $rows[] = [
                    (string) $document,
                    'NO ENCONTRADO',
                    'NO ENCONTRADO',
                ];
            }
        }

        return $rows;
    }

    private function isHeaderValue(string $raw, string $normalized): bool
    {
        $rawLower = mb_strtolower(trim($raw));

        return in_array($rawLower, ['cedula', 'cédula', 'documento', 'documento_id', 'document_number'], true)
            || $normalized === '';
    }
}

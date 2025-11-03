<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CensusRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CensusImporter
{
    /**
     * Import census records from an array of data.
     *
     * @return array{imported: int, failed: int, errors: array}
     *
     * @throws ValidationException
     */
    public function import(int $campaignId, array $records): array
    {
        $campaign = Campaign::findOrFail($campaignId);

        $imported = 0;
        $failed = 0;
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($records as $index => $record) {
                try {
                    $this->validateRecord($record);

                    CensusRecord::create([
                        'campaign_id' => $campaign->id,
                        'document_number' => $record['document_number'],
                        'full_name' => $record['full_name'],
                        'municipality_code' => $record['municipality_code'],
                        'polling_station' => $record['polling_station'] ?? null,
                        'table_number' => $record['table_number'] ?? null,
                        'imported_at' => now(),
                    ]);

                    $imported++;
                } catch (\Exception $e) {
                    $failed++;
                    $errors[] = [
                        'row' => $index + 1,
                        'error' => $e->getMessage(),
                        'data' => $record,
                    ];
                }
            }

            DB::commit();

            return [
                'imported' => $imported,
                'failed' => $failed,
                'errors' => $errors,
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            throw $e;
        }
    }

    /**
     * Import census records in batches for better performance.
     *
     * @return array{imported: int, failed: int, errors: array}
     */
    public function importInBatches(int $campaignId, array $records, int $batchSize = 500): array
    {
        $campaign = Campaign::findOrFail($campaignId);

        $imported = 0;
        $failed = 0;
        $errors = [];

        $chunks = Collection::make($records)->chunk($batchSize);

        foreach ($chunks as $chunk) {
            $batch = [];

            foreach ($chunk as $index => $record) {
                try {
                    $this->validateRecord($record);

                    $batch[] = [
                        'campaign_id' => $campaign->id,
                        'document_number' => $record['document_number'],
                        'full_name' => $record['full_name'],
                        'municipality_code' => $record['municipality_code'],
                        'polling_station' => $record['polling_station'] ?? null,
                        'table_number' => $record['table_number'] ?? null,
                        'imported_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                } catch (\Exception $e) {
                    $failed++;
                    $errors[] = [
                        'row' => $index + 1,
                        'error' => $e->getMessage(),
                        'data' => $record,
                    ];
                }
            }

            if (! empty($batch)) {
                try {
                    CensusRecord::insert($batch);
                    $imported += count($batch);
                } catch (\Exception $e) {
                    $failed += count($batch);

                    foreach ($batch as $index => $item) {
                        $errors[] = [
                            'row' => $index + 1,
                            'error' => $e->getMessage(),
                            'data' => $item,
                        ];
                    }
                }
            }
        }

        return [
            'imported' => $imported,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * Validate a census record.
     *
     *
     * @throws ValidationException
     */
    protected function validateRecord(array $record): void
    {
        $validator = Validator::make($record, [
            'document_number' => 'required|string|max:255',
            'full_name' => 'required|string|max:255',
            'municipality_code' => 'required|string|max:255',
            'polling_station' => 'nullable|string|max:255',
            'table_number' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Clear all census records for a campaign.
     */
    public function clearCensus(int $campaignId): int
    {
        return CensusRecord::where('campaign_id', $campaignId)->delete();
    }
}

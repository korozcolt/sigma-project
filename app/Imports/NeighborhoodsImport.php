<?php

namespace App\Imports;

use App\Models\Neighborhood;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class NeighborhoodsImport implements SkipsEmptyRows, ToModel, WithCustomCsvSettings, WithCustomValueBinder, WithHeadingRow
{
    public function __construct(
        private int $municipalityId,
        private ?int $campaignId = null
    ) {}

    /**
     * Force every cell to be read as a plain string, disabling
     * PhpSpreadsheet's automatic date/number auto-detection. Without this,
     * barrio names that look like Spanish "day de month" patterns
     * (e.g. "20 De Julio I", "7 De Agosto") can be auto-parsed as dates
     * and truncated down to just the leading day number.
     */
    public function bindValue(Cell $cell, mixed $value): bool
    {
        $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

        return true;
    }

    /**
     * Force the comma delimiter explicitly for CSV imports. Without this,
     * PhpSpreadsheet's delimiter auto-detection can misfire on a
     * single-column barrio CSV (no commas anywhere in the file) and pick
     * a space as the "detected" delimiter instead, splitting names like
     * "20 De Julio I" into multiple bogus columns.
     *
     * @return array<string, string>
     */
    public function getCsvSettings(): array
    {
        return [
            'delimiter' => ',',
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        $neighborhoodName = $row['barrio'] ?? $row['nombre'] ?? null;

        if (empty($neighborhoodName)) {
            return null;
        }

        $neighborhoodName = Str::title(trim($neighborhoodName));

        return new Neighborhood([
            'municipality_id' => $this->municipalityId,
            'campaign_id' => $this->campaignId,
            'name' => $neighborhoodName,
            'is_global' => $this->campaignId === null ? 1 : 0,
        ]);
    }
}

<?php

namespace App\Imports;

use App\Models\Neighborhood;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class NeighborhoodsImport implements SkipsEmptyRows, ToModel, WithHeadingRow
{
    public function __construct(
        private int $municipalityId,
        private ?int $campaignId = null
    ) {}

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

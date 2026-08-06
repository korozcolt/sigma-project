<?php

namespace App\Exports;

use App\Models\Campaign;
use App\Models\Voter;
use App\Services\VoterTerritoryScope;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class JurisdictionExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    public function __construct(protected ?Campaign $campaign = null) {}

    public function query(): Builder
    {
        return Voter::query()
            ->when(
                $this->campaign,
                fn (Builder $query) => $query->where('campaign_id', $this->campaign->id),
                fn (Builder $query) => $query->whereRaw('1 = 0')
            )
            ->with('municipality');
    }

    public function headings(): array
    {
        return ['Apoyo', 'Municipio', 'Jurisdicción'];
    }

    public function map($voter): array
    {
        return [
            $voter->full_name,
            $voter->municipality?->name ?? 'N/A',
            $this->jurisdiccionFor($voter),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '3B82F6'],
                ],
            ],
        ];
    }

    private function jurisdiccionFor(Voter $voter): string
    {
        if (! $this->campaign) {
            return 'N/A';
        }

        $territoryScope = new VoterTerritoryScope;

        if (! $territoryScope->isTerritoryDefined($this->campaign)) {
            return 'N/A';
        }

        return $territoryScope->isWithinCampaignScope($voter, $this->campaign) ? 'Dentro' : 'Fuera';
    }
}

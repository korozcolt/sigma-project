<?php

namespace App\Exports;

use App\Enums\VoterStatus;
use App\Models\PollingPlace;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TopPollingPlacesExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    public function __construct(protected ?int $campaignId = null) {}

    public function query(): Builder
    {
        return PollingPlace::query()
            ->when($this->campaignId, function (Builder $query) {
                $query->whereHas('voters', fn ($q) => $q->where('campaign_id', $this->campaignId)
                    ->where('status', '!=', VoterStatus::DUPLICATE->value))
                    ->withCount(['voters as apoyos_count' => fn ($q) => $q->where('campaign_id', $this->campaignId)
                        ->where('status', '!=', VoterStatus::DUPLICATE->value)])
                    ->orderByDesc('apoyos_count');
            }, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->with('municipality');
    }

    public function headings(): array
    {
        return [
            'Puesto de Votación',
            'Municipio',
            'Apoyos Válidos',
        ];
    }

    public function map($pollingPlace): array
    {
        return [
            $pollingPlace->name,
            $pollingPlace->municipality?->name ?? 'N/A',
            $pollingPlace->apoyos_count,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '10B981'],
                ],
            ],
        ];
    }
}

<?php

namespace App\Exports;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LeadersExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    protected ?array $campaignIds = null;

    protected ?array $municipalityIds = null;

    protected ?Builder $queryBuilder = null;

    public function __construct(
        array|int|null $campaignId = null,
        array|int|null $municipalityId = null,
        ?Builder $queryBuilder = null
    ) {
        $this->campaignIds = is_null($campaignId) ? null : (is_array($campaignId) ? $campaignId : [$campaignId]);
        $this->municipalityIds = is_null($municipalityId) ? null : (is_array($municipalityId) ? $municipalityId : [$municipalityId]);
        $this->queryBuilder = $queryBuilder;
    }

    public function query(): Builder
    {
        $builder = $this->queryBuilder ? (clone $this->queryBuilder) : User::query();

        $builder->with(['municipality', 'neighborhood']);

        if (! $this->queryBuilder) {
            $builder
                ->when($this->campaignIds, fn ($q) => $q->whereHas('campaigns', fn ($qq) => $qq->whereIn('campaigns.id', $this->campaignIds)))
                ->when($this->municipalityIds, fn ($q) => $q->whereIn('municipality_id', $this->municipalityIds))
                ->role('leader');
        }

        return $builder;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Nombre',
            'Email',
            'Teléfono',
            'Municipio',
            'Barrio',
            'Votantes Registrados',
            'Fecha de Creación',
        ];
    }

    public function map($leader): array
    {
        // Ensure voters count is available (either as relation count or 0)
        $votersCount = $leader->voters_count ?? $leader->registeredVoters()->count();

        return [
            $leader->id,
            $leader->name,
            $leader->email,
            $leader->phone,
            $leader->municipality?->name ?? 'N/A',
            $leader->neighborhood?->name ?? 'N/A',
            $votersCount,
            $leader->created_at?->format('d/m/Y H:i'),
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

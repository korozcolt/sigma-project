<?php

namespace App\Exports;

use App\Models\Voter;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class VotersExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    protected ?array $campaignIds = null;

    protected ?array $municipalityIds = null;

    protected ?array $neighborhoodIds = null;

    protected ?array $statuses = null;

    protected ?array $registeredByIds = null;

    protected ?string $createdFrom = null;

    protected ?string $createdUntil = null;

    protected ?Builder $queryBuilder = null;

    public function __construct(
        array|int|null $campaignId = null,
        array|int|null $municipalityId = null,
        array|int|null $neighborhoodId = null,
        array|string|null $status = null,
        array|int|null $registeredBy = null,
        ?Builder $queryBuilder = null,
        ?string $createdFrom = null,
        ?string $createdUntil = null
    ) {
        $this->campaignIds = is_null($campaignId) ? null : (is_array($campaignId) ? $campaignId : [$campaignId]);
        $this->municipalityIds = is_null($municipalityId) ? null : (is_array($municipalityId) ? $municipalityId : [$municipalityId]);
        $this->neighborhoodIds = is_null($neighborhoodId) ? null : (is_array($neighborhoodId) ? $neighborhoodId : [$neighborhoodId]);
        // Normalize statuses: accept both BackedEnums and scalar values (strings)
        $this->statuses = is_null($status) ? null : (is_array($status) ? $status : [$status]);

        if ($this->statuses) {
            $this->statuses = array_map(fn ($s) => (is_object($s) && property_exists($s, 'value')) ? $s->value : $s, $this->statuses);
        }
        $this->registeredByIds = is_null($registeredBy) ? null : (is_array($registeredBy) ? $registeredBy : [$registeredBy]);
        $this->queryBuilder = $queryBuilder;
        $this->createdFrom = $createdFrom;
        $this->createdUntil = $createdUntil;
    }

    public function query(): Builder
    {
        $builder = $this->queryBuilder ? (clone $this->queryBuilder) : Voter::query();

        $builder->with(['municipality', 'neighborhood', 'registeredBy', 'campaign']);

        if (! $this->queryBuilder) {
            $builder
                ->when($this->campaignIds, fn ($q) => $q->whereIn('campaign_id', $this->campaignIds))
                ->when($this->municipalityIds, fn ($q) => $q->whereIn('municipality_id', $this->municipalityIds))
                ->when($this->neighborhoodIds, fn ($q) => $q->whereIn('neighborhood_id', $this->neighborhoodIds))
                ->when($this->statuses, fn ($q) => $q->whereIn('status', $this->statuses))
                ->when($this->registeredByIds, fn ($q) => $q->whereIn('registered_by', $this->registeredByIds))
                ->when($this->createdFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->createdFrom))
                ->when($this->createdUntil, fn ($q) => $q->whereDate('created_at', '<=', $this->createdUntil))
                ->orderBy('created_at', 'desc');
        }

        return $builder;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Campaña',
            'Documento',
            'Nombre Completo',
            'Teléfono',
            'Teléfono Secundario',
            'Email',
            'Municipio',
            'Barrio',
            'Dirección',
            'Fecha de Nacimiento',
            'Estado',
            'Registrado Por',
            'Fecha de Registro',
            'Validado en Censo',
            'Verificado por Llamada',
            'Confirmado',
        ];
    }

    public function map($voter): array
    {
        return [
            $voter->id,
            $voter->campaign?->name ?? 'N/A',
            $voter->document_number,
            $voter->full_name,
            $voter->phone,
            $voter->secondary_phone,
            $voter->email,
            $voter->municipality?->name ?? 'N/A',
            $voter->neighborhood?->name ?? 'N/A',
            $voter->address,
            $voter->birth_date?->format('d/m/Y'),
            $voter->status->getLabel(),
            $voter->registeredBy?->name ?? 'N/A',
            $voter->created_at->format('d/m/Y H:i'),
            $voter->census_validated_at?->format('d/m/Y H:i') ?? 'No',
            $voter->call_verified_at?->format('d/m/Y H:i') ?? 'No',
            $voter->confirmed_at?->format('d/m/Y H:i') ?? 'No',
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
}

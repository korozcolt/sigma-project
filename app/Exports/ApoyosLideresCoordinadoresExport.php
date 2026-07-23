<?php

namespace App\Exports;

use App\Enums\UserRole;
use App\Models\User;
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

class ApoyosLideresCoordinadoresExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    public function __construct(protected ?int $campaignId = null) {}

    public function query(): Builder
    {
        return Voter::query()
            ->when($this->campaignId, fn (Builder $query) => $query->where('campaign_id', $this->campaignId))
            ->with(['registeredBy.coordinator', 'municipality']);
    }

    public function headings(): array
    {
        return [
            'ID', 'Documento', 'Nombre Completo', 'Teléfono', 'Email',
            'Municipio', 'Dirección', 'Estado', 'Fecha de Registro',
            'Líder Nombre', 'Líder Teléfono', 'Líder Email',
            'Coordinador Nombre', 'Coordinador Teléfono', 'Coordinador Email',
        ];
    }

    public function map($voter): array
    {
        $registrador = $voter->registeredBy;
        $lider = $registrador?->hasRole(UserRole::LEADER->value) ? $registrador : null;
        $coordinador = $this->coordinadorFor($registrador, $lider);

        return [
            $voter->id,
            $voter->document_number,
            $voter->full_name,
            $voter->phone,
            $voter->email,
            $voter->municipality?->name ?? 'N/A',
            $voter->address,
            $voter->status->getLabel(),
            $voter->created_at->format('d/m/Y H:i'),
            $lider?->name ?? 'N/A',
            $lider?->phone ?? 'N/A',
            $lider?->email ?? 'N/A',
            $coordinador?->name ?? 'N/A',
            $coordinador?->phone ?? 'N/A',
            $coordinador?->email ?? 'N/A',
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

    private function coordinadorFor(?User $registrador, ?User $lider): ?User
    {
        if ($registrador?->hasRole(UserRole::COORDINATOR->value)) {
            return $registrador;
        }

        return $lider?->coordinator;
    }
}

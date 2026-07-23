<?php

namespace App\Exports;

use App\Enums\UserRole;
use App\Enums\VoterStatus;
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

class TopCoordinatorsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    public function __construct(protected ?int $campaignId = null) {}

    public function query(): Builder
    {
        return User::query()
            ->role(UserRole::COORDINATOR->value)
            ->when($this->campaignId, function (Builder $query) {
                $query->whereHas('campaigns', fn ($q) => $q->where('campaigns.id', $this->campaignId))
                    ->withCount(['leaders as leaders_count'])
                    ->withCount(['leaders as apoyos_equipo_count' => function (Builder $q) {
                        $q->join('voters', 'voters.registered_by', '=', $q->qualifyColumn('id'))
                            ->where('voters.campaign_id', $this->campaignId)
                            ->where('voters.status', '!=', VoterStatus::DUPLICATE->value);
                    }])
                    ->orderByDesc('apoyos_equipo_count');
            }, fn (Builder $query) => $query->whereRaw('1 = 0'));
    }

    public function headings(): array
    {
        return [
            'Coordinador',
            'Email',
            'Teléfono',
            'Municipio',
            'Líderes Asignados',
            'Apoyos del Equipo',
        ];
    }

    public function map($coordinator): array
    {
        return [
            $coordinator->name,
            $coordinator->email,
            $coordinator->phone,
            $coordinator->municipality?->name ?? 'N/A',
            $coordinator->leaders_count,
            $coordinator->apoyos_equipo_count,
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

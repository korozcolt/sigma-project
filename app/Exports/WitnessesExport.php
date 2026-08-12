<?php

namespace App\Exports;

use App\Models\User;
use App\Services\MetadataAssignmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class WitnessesExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    protected ?Builder $queryBuilder = null;

    protected Collection $activeMetadataKeys;

    public function __construct(?Builder $queryBuilder = null)
    {
        $this->queryBuilder = $queryBuilder;
        $this->activeMetadataKeys = app(MetadataAssignmentService::class)->activeKeys();
    }

    public function query(): Builder
    {
        $builder = $this->queryBuilder ? (clone $this->queryBuilder) : User::query();

        $builder->with(['municipality', 'neighborhood']);

        if (! $this->queryBuilder) {
            $builder->witnesses();
        }

        return app(MetadataAssignmentService::class)->withCurrentValueSelects($builder, $this->activeMetadataKeys);
    }

    public function headings(): array
    {
        return [
            'ID',
            'Nombre',
            'Email',
            'Teléfono',
            'Municipio',
            'Mesa Asignada',
            'Pago (COP)',
            'Fecha de Creación',
            ...$this->activeMetadataKeys->pluck('label')->all(),
        ];
    }

    public function map($user): array
    {
        return [
            $user->id,
            $user->name,
            $user->email,
            $user->phone,
            $user->municipality?->name ?? 'N/A',
            $user->witness_assigned_station ?? 'N/A',
            $user->witness_payment_amount ?? '0.00',
            $user->created_at?->format('d/m/Y H:i'),
            ...$this->activeMetadataKeys->map(fn ($key) => $user->{"metadata_{$key->id}"} ?? '')->all(),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '7C3AED'],
                ],
            ],
        ];
    }
}

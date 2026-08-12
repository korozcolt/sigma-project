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

class AnnotatorsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
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
            $builder->voteRecorders();
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
            'Apoyos Registrados',
            'Fecha de Creación',
            ...$this->activeMetadataKeys->pluck('label')->all(),
        ];
    }

    public function map($user): array
    {
        $votersCount = $user->registeredVoters_count ?? $user->registeredVoters()->count();

        return [
            $user->id,
            $user->name,
            $user->email,
            $user->phone,
            $user->municipality?->name ?? 'N/A',
            $votersCount,
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
                    'startColor' => ['rgb' => 'EF4444'],
                ],
            ],
        ];
    }
}

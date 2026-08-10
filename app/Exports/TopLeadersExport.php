<?php

namespace App\Exports;

use App\Enums\UserRole;
use App\Enums\VoterStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TopLeadersExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    public function __construct(protected ?int $campaignId = null) {}

    public function query(): Builder
    {
        return User::query()
            ->when($this->campaignId, function (Builder $query) {
                $query->whereHas('campaigns', fn ($q) => $q->where('campaigns.id', $this->campaignId))
                    ->whereHas('registeredVoters', fn ($q) => $q->where('campaign_id', $this->campaignId)
                        ->where('status', '!=', VoterStatus::DUPLICATE->value))
                    ->withCount(['registeredVoters' => fn ($q) => $q->where('campaign_id', $this->campaignId)
                        ->where('status', '!=', VoterStatus::DUPLICATE->value)])
                    ->orderByDesc('registered_voters_count');
            }, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when(
                Auth::user()?->hasAnyRole([UserRole::COORDINATOR->value, UserRole::AREA_COORDINATOR->value]),
                fn (Builder $query) => $query->whereIn('coordinator_user_id', Auth::user()->teamCoordinatorUserIds())
            );
    }

    public function headings(): array
    {
        return [
            'Líder',
            'Email',
            'Teléfono',
            'Municipio',
            'Apoyos Válidos',
        ];
    }

    public function map($leader): array
    {
        return [
            $leader->name,
            $leader->email,
            $leader->phone,
            $leader->municipality?->name ?? 'N/A',
            $leader->registered_voters_count,
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

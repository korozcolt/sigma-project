<?php

namespace App\Exports;

use App\Enums\CallResult;
use App\Enums\VoterStatus;
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

class RejectionsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    public function __construct(protected ?int $campaignId = null) {}

    public function query(): Builder
    {
        $rejectionCallResults = [
            CallResult::REJECTED->value,
            CallResult::INVALID_NUMBER->value,
            CallResult::NOT_INTERESTED->value,
        ];

        return Voter::query()
            ->when($this->campaignId, fn ($q) => $q->where('campaign_id', $this->campaignId), fn ($q) => $q->whereRaw('1 = 0'))
            ->where(function (Builder $q) use ($rejectionCallResults) {
                $q->whereIn('status', [VoterStatus::REJECTED_CENSUS->value, VoterStatus::CORRECTION_REQUIRED->value])
                    ->orWhereHas('verificationCalls', fn ($q2) => $q2->whereIn('call_result', $rejectionCallResults));
            })
            ->with(['registeredBy', 'verificationCalls' => fn ($q) => $q->whereIn('call_result', $rejectionCallResults)->latest('call_date')]);
    }

    public function headings(): array
    {
        return ['Documento', 'Apoyo', 'Teléfono', 'Estado', 'Motivo del Rechazo', 'Líder'];
    }

    public function map($voter): array
    {
        $reasons = [];

        if (in_array($voter->status, [VoterStatus::REJECTED_CENSUS, VoterStatus::CORRECTION_REQUIRED], true)) {
            $reasons[] = $voter->status->getLabel();
        }

        foreach ($voter->verificationCalls as $call) {
            $reasons[] = $call->call_result->getLabel();
        }

        $motivo = implode(' / ', array_unique($reasons)) ?: 'N/A';

        return [
            $voter->display_suffix,
            $voter->full_name,
            $voter->phone,
            $voter->status->getLabel(),
            $motivo,
            $voter->registeredBy?->name ?? 'N/A',
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

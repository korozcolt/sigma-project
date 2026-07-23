<?php

namespace App\Exports;

use App\Enums\VoterStatus;
use App\Models\Voter;
use App\Services\CampaignContext;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * INTENTIONAL EXCEPTION TO CAMPAIGN ISOLATION (D-06).
 *
 * Mirrors DuplicatesReportTable — this export is never scoped by a passed-in
 * campaign filter, it always resolves CampaignContext::currentCampaign() itself,
 * because a disputed cédula's siblings can legitimately live under a different
 * campaign than the one currently active. See DuplicatesReportTable's class-level
 * PHPDoc for the full rationale. Do not add a campaign_id constructor parameter.
 */
class DuplicatesExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    public function query(): Builder
    {
        $activeCampaign = CampaignContext::currentCampaign();

        $documentNumbers = $activeCampaign
            ? Voter::withoutGlobalScopes()
                ->withTrashed()
                ->select('document_number')
                ->groupBy('document_number')
                ->havingRaw('COUNT(*) > 1')
                ->havingRaw('SUM(CASE WHEN campaign_id = ? THEN 1 ELSE 0 END) > 0', [$activeCampaign->id])
                ->pluck('document_number')
            : collect();

        return Voter::withoutGlobalScopes()
            ->withTrashed()
            ->when($documentNumbers->isNotEmpty(), fn ($q) => $q->whereIn('document_number', $documentNumbers), fn ($q) => $q->whereRaw('1 = 0'))
            ->with(['registeredBy.coordinator', 'campaign'])
            ->orderBy('document_number')
            ->orderBy('duplicate_sequence');
    }

    public function headings(): array
    {
        return ['Cédula-Secuencia', 'Apoyo', 'Campaña', 'Estado', 'Registrado por', 'Fecha'];
    }

    public function map($voter): array
    {
        $esDuenoActual = $voter->status !== VoterStatus::DUPLICATE;

        return [
            $voter->display_suffix,
            $voter->full_name,
            $voter->campaign?->name ?? 'N/A',
            $voter->status->getLabel().($esDuenoActual ? ' (dueño actual)' : ''),
            $voter->registeredBy?->name ?? 'N/A',
            $voter->created_at?->format('d/m/Y H:i'),
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

<?php

namespace App\Filament\Widgets;

use App\Enums\CallResult;
use App\Enums\VoterStatus;
use App\Exports\RejectionsExport;
use App\Models\Voter;
use App\Services\CampaignContext;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class RejectionsReportTable extends TableWidget
{
    protected static ?int $sort = 6;

    protected static ?string $heading = 'Informe de Rechazos';

    protected static ?string $description = 'Apoyos rechazados en censo o durante la llamada de verificación';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $activeCampaign = CampaignContext::currentCampaign();

        $rejectionCallResults = [
            CallResult::REJECTED->value,
            CallResult::INVALID_NUMBER->value,
            CallResult::NOT_INTERESTED->value,
        ];

        return $table
            ->query(fn (): Builder => Voter::query()
                ->when($activeCampaign, fn ($q) => $q->where('campaign_id', $activeCampaign->id), fn ($q) => $q->whereRaw('1 = 0'))
                ->where(function (Builder $q) use ($rejectionCallResults) {
                    $q->whereIn('status', [VoterStatus::REJECTED_CENSUS->value, VoterStatus::CORRECTION_REQUIRED->value])
                        ->orWhereHas('verificationCalls', fn ($q2) => $q2->whereIn('call_result', $rejectionCallResults));
                })
                ->with(['registeredBy', 'verificationCalls' => fn ($q) => $q->whereIn('call_result', $rejectionCallResults)->latest('call_date')]))
            ->columns([
                TextColumn::make('display_suffix')
                    ->label('Documento'),

                TextColumn::make('full_name')
                    ->label('Apoyo'),

                TextColumn::make('phone')
                    ->label('Teléfono'),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge(),

                TextColumn::make('motivo')
                    ->label('Motivo del Rechazo')
                    ->state(function (Voter $record): string {
                        $reasons = [];

                        if (in_array($record->status, [VoterStatus::REJECTED_CENSUS, VoterStatus::CORRECTION_REQUIRED], true)) {
                            $reasons[] = $record->status->getLabel();
                        }

                        foreach ($record->verificationCalls as $call) {
                            $reasons[] = $call->call_result->getLabel();
                        }

                        return implode(' / ', array_unique($reasons)) ?: 'N/A';
                    }),

                TextColumn::make('registeredBy.name')
                    ->label('Líder'),
            ])
            ->headerActions([
                Action::make('export')
                    ->label('Exportar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn () => (new RejectionsExport($activeCampaign?->id))->download('informe-rechazos.xlsx')),
            ]);
    }
}

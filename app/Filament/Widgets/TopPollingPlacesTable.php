<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Enums\VoterStatus;
use App\Exports\TopPollingPlacesExport;
use App\Filament\Resources\Voters\VoterResource;
use App\Models\PollingPlace;
use App\Models\Voter;
use App\Services\CampaignContext;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use stdClass;
use Throwable;

class TopPollingPlacesTable extends TableWidget
{
    protected static ?int $sort = 5;

    protected static ?string $heading = 'Ranking de Puestos de Votación';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $activeCampaign = CampaignContext::currentCampaign();

        return $table
            ->query(fn (): Builder => PollingPlace::query()
                ->when($activeCampaign, function (Builder $query) use ($activeCampaign) {
                    $query->whereHas('voters', fn ($q) => $q->where('campaign_id', $activeCampaign->id)
                        ->where('status', '!=', VoterStatus::DUPLICATE->value))
                        ->withCount(['voters as apoyos_count' => fn ($q) => $q->where('campaign_id', $activeCampaign->id)
                            ->where('status', '!=', VoterStatus::DUPLICATE->value)])
                        ->orderByDesc('apoyos_count');
                }, fn (Builder $query) => $query->whereRaw('1 = 0'))
                ->with('municipality')
                ->limit(10))
            ->columns([
                TextColumn::make('ranking')
                    ->label('#')
                    ->state(fn ($livewire, $rowLoop): int => static::resolveAbsolutePosition($livewire, $rowLoop))
                    ->badge()
                    ->color(fn ($livewire, $rowLoop) => match (static::resolveAbsolutePosition($livewire, $rowLoop)) {
                        1 => 'warning',
                        2 => 'gray',
                        3 => 'orange',
                        default => 'primary',
                    })
                    ->icon(fn ($livewire, $rowLoop) => match (static::resolveAbsolutePosition($livewire, $rowLoop)) {
                        1 => 'heroicon-m-trophy',
                        2, 3 => 'heroicon-m-star',
                        default => null,
                    }),

                TextColumn::make('name')
                    ->label('Puesto de Votación')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('municipality.name')
                    ->label('Municipio'),

                TextColumn::make('apoyos_count')
                    ->label('Apoyos Válidos')
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 100 => 'success',
                        $state >= 50 => 'info',
                        $state >= 25 => 'warning',
                        default => 'gray',
                    }),
            ])
            ->headerActions([
                Action::make('export')
                    ->label('Exportar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (): bool => ! (auth()->user()?->hasRole(UserRole::REPORTS_VIEWER->value) ?? false))
                    ->action(fn () => (new TopPollingPlacesExport($activeCampaign?->id))->download('ranking-puestos-votacion.xlsx')),
            ])
            ->recordUrl(fn (PollingPlace $record) => VoterResource::getUrl('index', [
                'tableFilters' => [
                    'polling_place_id' => ['values' => [$record->id]],
                ],
            ]));
    }

    /**
     * Resolve the row's absolute position across all pages (not the
     * per-page-relative `$rowLoop->iteration`), so the leaderboard
     * trophy/star badge only ever applies to the true overall #1/#2/#3,
     * regardless of which page is currently displayed.
     *
     * Public so it can be exercised directly in tests: Filament's
     * `assertTableColumnStateSet()` test helper reads the column's cached
     * `$rowLoop`, which is mutated in place to the LAST rendered row of the
     * page (not the row matching the asserted record), so this method needs
     * to be independently testable with a hand-built loop object.
     */
    public static function resolveAbsolutePosition(mixed $livewire, ?stdClass $rowLoop): int
    {
        $iteration = $rowLoop?->iteration ?? 1;

        try {
            $page = (int) $livewire->getTablePage();
            $perPage = $livewire->getTableRecordsPerPage();

            if (! is_numeric($perPage) || $page < 1 || (int) $perPage < 1) {
                return $iteration;
            }

            return (($page - 1) * (int) $perPage) + $iteration;
        } catch (Throwable) {
            return $iteration;
        }
    }

    protected function getTableDescription(): ?string
    {
        $activeCampaign = CampaignContext::currentCampaign();

        if (! $activeCampaign) {
            return null;
        }

        $sinPuesto = Voter::query()
            ->where('campaign_id', $activeCampaign->id)
            ->where('status', '!=', VoterStatus::DUPLICATE->value)
            ->whereNull('polling_place_id')
            ->count();

        return "{$sinPuesto} apoyos válidos sin puesto de votación asignado";
    }
}

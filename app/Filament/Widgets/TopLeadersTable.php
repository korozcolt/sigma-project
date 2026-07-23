<?php

namespace App\Filament\Widgets;

use App\Enums\VoterStatus;
use App\Exports\TopLeadersExport;
use App\Models\User;
use App\Services\CampaignContext;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class TopLeadersTable extends TableWidget
{
    protected static ?int $sort = 3;

    protected static ?string $heading = 'Ranking de Líderes';

    protected static ?string $description = 'Top 10 líderes por apoyos registrados en la campaña';

    protected ?string $pollingInterval = '120s';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $activeCampaign = CampaignContext::currentCampaign();

        return $table
            ->query(
                fn (): Builder => User::query()
                    ->when($activeCampaign, function ($query) use ($activeCampaign) {
                        $query->whereHas('campaigns', fn ($q) => $q->where('campaigns.id', $activeCampaign->id))
                            ->whereHas('registeredVoters', fn ($q) => $q->where('campaign_id', $activeCampaign->id)
                                ->where('status', '!=', VoterStatus::DUPLICATE->value))
                            ->withCount(['registeredVoters' => fn ($q) => $q->where('campaign_id', $activeCampaign->id)
                                ->where('status', '!=', VoterStatus::DUPLICATE->value)])
                            ->orderByDesc('registered_voters_count');
                    })
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('ranking')
                    ->label('#')
                    ->state(fn ($rowLoop) => $rowLoop->iteration)
                    ->badge()
                    ->color(fn ($rowLoop) => match ($rowLoop->iteration) {
                        1 => 'warning',
                        2 => 'gray',
                        3 => 'orange',
                        default => 'primary',
                    })
                    ->icon(fn ($rowLoop) => match ($rowLoop->iteration) {
                        1 => 'heroicon-m-trophy',
                        2 => 'heroicon-m-star',
                        3 => 'heroicon-m-star',
                        default => null,
                    }),

                TextColumn::make('name')
                    ->label('Líder')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->icon('heroicon-m-envelope')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->icon('heroicon-m-phone')
                    ->toggleable(),

                TextColumn::make('municipality.name')
                    ->label('Municipio')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('registered_voters_count')
                    ->label('Apoyos')
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 100 => 'success',
                        $state >= 50 => 'info',
                        $state >= 25 => 'warning',
                        default => 'gray',
                    })
                    ->icon('heroicon-m-user-group'),
            ])
            ->headerActions([
                Action::make('export')
                    ->label('Exportar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn () => (new TopLeadersExport($activeCampaign?->id))->download('ranking-lideres.xlsx')),
            ])
            ->paginated(false);
    }
}

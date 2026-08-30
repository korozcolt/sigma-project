<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Enums\VoterStatus;
use App\Exports\TopLeadersExport;
use App\Filament\Resources\Voters\VoterResource;
use App\Models\User;
use App\Services\CampaignContext;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

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
        $user = Auth::user();

        return $table
            ->query(
                fn (): Builder => User::query()
                    ->with('coordinator')
                    ->when($activeCampaign, function ($query) use ($activeCampaign) {
                        $query->whereHas('campaigns', fn ($q) => $q->where('campaigns.id', $activeCampaign->id))
                            ->whereHas('registeredVoters', fn ($q) => $q->where('campaign_id', $activeCampaign->id)
                                ->where('status', '!=', VoterStatus::DUPLICATE->value))
                            ->withCount(['registeredVoters' => fn ($q) => $q->where('campaign_id', $activeCampaign->id)
                                ->where('status', '!=', VoterStatus::DUPLICATE->value)])
                            ->orderByDesc('registered_voters_count');
                    })
                    ->when(
                        $user?->hasAnyRole([UserRole::COORDINATOR->value, UserRole::AREA_COORDINATOR->value]),
                        fn ($query) => $query->whereIn('coordinator_user_id', $user->teamCoordinatorUserIds())
                    )
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

                TextColumn::make('coordinator.name')
                    ->label('Coordinador')
                    ->toggleable(),

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
                    ->visible(fn (): bool => ! (auth()->user()?->hasRole(UserRole::REPORTS_VIEWER->value) ?? false))
                    ->action(fn () => (new TopLeadersExport($activeCampaign?->id))->download('ranking-lideres.xlsx')),

                Action::make('exportTeam')
                    ->label('Exportar Equipo Completo')
                    ->icon('heroicon-o-document-arrow-down')
                    ->visible(fn (): bool => ! (auth()->user()?->hasRole(UserRole::REPORTS_VIEWER->value) ?? false))
                    ->url(fn (): string => route('coordinator.leaders.export')),
            ])
            ->recordUrl(fn (User $record) => $this->voterResourceUrl('index', [
                'tableFilters' => [
                    'registered_by' => ['values' => [$record->id]],
                ],
            ]))
            ->paginated(false);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function voterResourceUrl(string $name, array $parameters = []): ?string
    {
        $panelId = Filament::getCurrentOrDefaultPanel()?->getId();

        if (! $panelId || ! Route::has("filament.{$panelId}.resources.voters.{$name}")) {
            return null;
        }

        return VoterResource::getUrl($name, $parameters);
    }
}

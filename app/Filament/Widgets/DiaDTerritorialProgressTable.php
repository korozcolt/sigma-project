<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Enums\VoterStatus;
use App\Models\Municipality;
use App\Services\CampaignContext;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class DiaDTerritorialProgressTable extends TableWidget
{
    protected static ?string $heading = 'Participación por Municipio — Día D';

    protected ?string $pollingInterval = '15s';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $activeCampaign = CampaignContext::currentCampaign();

        return $table
            ->query(fn (): Builder => Municipality::query()
                ->when($activeCampaign, function (Builder $query) use ($activeCampaign) {
                    $query->whereHas('voters', fn ($q) => $this->applyVoterScope($q->where('campaign_id', $activeCampaign->id)))
                        ->withCount([
                            'voters as total' => fn ($q) => $this->applyVoterScope($q->where('campaign_id', $activeCampaign->id)),
                            'voters as voted_count' => fn ($q) => $this->applyVoterScope($q->where('campaign_id', $activeCampaign->id))
                                ->where('status', VoterStatus::VOTED->value),
                            'voters as did_not_vote_count' => fn ($q) => $this->applyVoterScope($q->where('campaign_id', $activeCampaign->id))
                                ->where('status', VoterStatus::DID_NOT_VOTE->value),
                        ])
                        ->orderByDesc('total');
                }, fn (Builder $query) => $query->whereRaw('1 = 0')))
            ->columns([
                TextColumn::make('name')->label('Municipio')->weight('bold'),
                TextColumn::make('total')->label('Total Apoyos')->sortable(),
                TextColumn::make('voted_count')->label('Votaron')->badge()->color('success'),
                TextColumn::make('did_not_vote_count')->label('No Votaron')->badge()->color('danger'),
                TextColumn::make('participation')
                    ->label('% Participación')
                    ->state(fn (Municipality $record): string => $record->total > 0
                        ? round(($record->voted_count / $record->total) * 100, 1).'%'
                        : '0%'),
            ])
            ->paginated(false);
    }

    private function applyVoterScope(Builder $query): Builder
    {
        $user = Auth::user();

        if ($user?->hasRole(UserRole::LEADER->value)) {
            return $query->where('registered_by', $user->id);
        }

        if ($user?->hasRole(UserRole::COORDINATOR->value)) {
            return $query->whereIn('registered_by', $user->leaders()->pluck('id'));
        }

        return $query;
    }
}

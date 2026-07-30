<?php

namespace App\Filament\Resources\Voters\Tables;

use App\Enums\PollingPlaceSource;
use App\Enums\UserRole;
use App\Enums\VoterStatus;
use App\Jobs\DispatchCensusRevalidation;
use App\Models\User;
use App\Models\Voter;
use App\Services\CampaignContext;
use App\Services\VoterValidationService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VotersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label('Nombre Completo')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['first_name', 'last_name'])
                    ->weight('bold')
                    ->description(function (Voter $record): ?string {
                        if ($record->isSystemUser()) {
                            $roles = $record->user->roles->pluck('name')->map(function ($roleName) {
                                $userRole = UserRole::tryFrom($roleName);
                                if (! $userRole) {
                                    return $roleName;
                                }

                                $emoji = match ($userRole) {
                                    UserRole::SUPER_ADMIN => '👑',
                                    UserRole::ADMIN_CAMPAIGN => '🎯',
                                    UserRole::COORDINATOR => '📍',
                                    UserRole::LEADER => '⭐',
                                    UserRole::REVIEWER => '📞',
                                };

                                return $emoji.' '.$userRole->getLabel();
                            })->join(', ');

                            return $roles;
                        }

                        return null;
                    }),

                TextColumn::make('document_number')
                    ->label('Documento')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Documento copiado'),

                TextColumn::make('duplicate_sequence')
                    ->label('Secuencia Duplicado')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable()
                    ->icon('heroicon-m-phone')
                    ->toggleable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->copyable(),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (VoterStatus $state): string => match ($state) {
                        VoterStatus::PENDING_REVIEW => 'gray',
                        VoterStatus::REJECTED_CENSUS => 'danger',
                        VoterStatus::CENSUS_NOT_FOUND => 'warning',
                        VoterStatus::VERIFIED_CENSUS => 'info',
                        VoterStatus::VERIFIED_REGISTRADURIA => 'success',
                        VoterStatus::CORRECTION_REQUIRED => 'warning',
                        VoterStatus::VERIFIED_CALL => 'success',
                        VoterStatus::CONFIRMED => 'success',
                        VoterStatus::VOTED => 'primary',
                        VoterStatus::DID_NOT_VOTE => 'gray',
                        VoterStatus::DUPLICATE => 'warning',
                    })
                    ->sortable(),

                TextColumn::make('polling_place_source')
                    ->label('Fuente del Puesto de Votación')
                    ->badge()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('campaign.name')
                    ->label('Campaña')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('municipality.name')
                    ->label('Municipio')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('neighborhood.name')
                    ->label('Barrio')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('registeredBy.name')
                    ->label('Registrado por')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('birth_date')
                    ->label('Fecha de Nacimiento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('census_validated_at')
                    ->label('Validado Censo')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('polling_place_resolved_at')
                    ->label('Actualizado el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('call_verified_at')
                    ->label('Verificado Llamada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('confirmed_at')
                    ->label('Confirmado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('voted_at')
                    ->label('Votó')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('campaign_id')
                    ->label('Campaña')
                    ->relationship('campaign', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple()
                    ->visible(fn (): bool => CampaignContext::isSuperAdmin()),

                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(VoterStatus::class)
                    ->multiple()
                    ->preload(),

                SelectFilter::make('polling_place_source')
                    ->label('Fuente del Puesto de Votación')
                    ->options(PollingPlaceSource::class)
                    ->multiple()
                    ->preload(),

                SelectFilter::make('municipality_id')
                    ->label('Municipio')
                    ->relationship('municipality', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                SelectFilter::make('polling_place_id')
                    ->label('Puesto de Votación')
                    ->relationship('pollingPlace', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                SelectFilter::make('neighborhood_id')
                    ->label('Barrio')
                    ->relationship('neighborhood', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                SelectFilter::make('registered_by')
                    ->label('Registrado por')
                    ->relationship('registeredBy', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                Filter::make('contact_state')
                    ->label('Estado de Contacto')
                    ->form([
                        Select::make('value')
                            ->label('Estado de Contacto')
                            ->options([
                                'has_open_call' => 'Con llamada pendiente',
                                'survey_completed' => 'Encuesta completada',
                                'no_contact' => 'Sin ningún contacto registrado',
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'has_open_call' => $query->whereHas('callAssignments', fn ($q) => $q->whereIn('status', ['pending', 'in_progress'])),
                            'survey_completed' => $query->whereHas('surveyResponses'),
                            'no_contact' => $query->whereDoesntHave('callAssignments')->whereDoesntHave('surveyResponses'),
                            default => $query,
                        };
                    })
                    ->indicateUsing(fn (array $data): ?string => match ($data['value'] ?? null) {
                        'has_open_call' => 'Con llamada pendiente',
                        'survey_completed' => 'Encuesta completada',
                        'no_contact' => 'Sin contacto',
                        default => null,
                    }),

                TrashedFilter::make(),
            ])
            ->headerActions([
                Action::make('revalidateLeaderVoters')
                    ->label('Revalidar apoyos de un líder')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->visible(fn (): bool => auth()->user()?->hasAnyRole([
                        UserRole::SUPER_ADMIN->value,
                        UserRole::ADMIN_CAMPAIGN->value,
                        UserRole::REVIEWER->value,
                    ]) ?? false)
                    ->form([
                        Select::make('leader_id')
                            ->label('Líder')
                            ->options(fn (): array => User::query()
                                ->role(UserRole::LEADER->value)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        DispatchCensusRevalidation::dispatch(
                            leaderId: (int) $data['leader_id'],
                            campaignId: CampaignContext::currentCampaignId(),
                        );

                        Notification::make()
                            ->title('Revalidación en background iniciada')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('validateCensus')
                    ->label('Validar contra Censo')
                    ->icon('heroicon-o-shield-check')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalDescription('Se comparará el documento del apoyo contra los registros del censo electoral de esta campaña.')
                    ->visible(fn (Voter $record): bool => $record->status !== VoterStatus::DUPLICATE
                        && ! (auth()->user()?->hasRole(UserRole::REPORTS_VIEWER->value) ?? false))
                    ->action(function (Voter $record): void {
                        app(VoterValidationService::class)->validateAndUpdate($record);

                        Notification::make()
                            ->title('Validación contra censo completada')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->persistColumnSearchesInSession();
    }
}

<?php

namespace App\Filament\Resources\Voters\Pages;

use App\Enums\UserRole;
use App\Enums\VoterStatus;
use App\Filament\Resources\Voters\Concerns\HasRegistraduriaPolling;
use App\Filament\Resources\Voters\VoterResource;
use App\Models\ValidationHistory;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditVoter extends EditRecord
{
    use HasRegistraduriaPolling;

    protected static string $resource = VoterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reassignDuplicateOwner')
                ->label('Reasignar dueño de duplicado')
                ->icon('heroicon-o-arrows-right-left')
                ->color('warning')
                ->visible(fn (): bool => $this->record->status === VoterStatus::DUPLICATE
                    && (auth()->user()?->hasAnyRole([UserRole::ADMIN_CAMPAIGN->value, UserRole::SUPER_ADMIN->value]) ?? false))
                ->form([
                    Textarea::make('notes')
                        ->label('Motivo de la reasignación')
                        ->required()
                        ->rows(3)
                        ->helperText('Explica por qué este apoyo deja de considerarse duplicado (obligatorio para auditoría, D-03).'),
                ])
                ->action(function (array $data): void {
                    $previousStatus = $this->record->status;

                    $this->record->update([
                        'status' => VoterStatus::PENDING_REVIEW,
                    ]);

                    ValidationHistory::create([
                        'voter_id' => $this->record->id,
                        'previous_status' => $previousStatus,
                        'new_status' => VoterStatus::PENDING_REVIEW,
                        'validated_by' => auth()->id(),
                        'validation_type' => 'duplicate_reassignment',
                        'notes' => $data['notes'],
                    ]);

                    Notification::make()
                        ->title('Duplicado reasignado correctamente')
                        ->success()
                        ->send();
                }),
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->getResource()::getUrl('index');
    }
}

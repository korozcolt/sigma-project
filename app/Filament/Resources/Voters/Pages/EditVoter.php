<?php

namespace App\Filament\Resources\Voters\Pages;

use App\Enums\UserRole;
use App\Enums\VoterStatus;
use App\Filament\Resources\Voters\Concerns\HasRegistraduriaPolling;
use App\Filament\Resources\Voters\VoterResource;
use App\Models\ValidationHistory;
use App\Models\Voter;
use App\Services\VoterDuplicateAssignmentService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
                ->form(function (): array {
                    $siblings = app(VoterDuplicateAssignmentService::class)->siblingsFor($this->record->document_number);
                    $ownerIds = $siblings->pluck('registered_by')->unique()->all();

                    return [
                        Select::make('new_owner_user_id')
                            ->label('Líder/Coordinador que debe quedar como dueño de la cédula')
                            ->options(
                                $siblings->unique('registered_by')->mapWithKeys(fn (Voter $sibling): array => [
                                    $sibling->registered_by => sprintf(
                                        '%s (secuencia -%d)',
                                        $sibling->registeredBy?->name ?? "Usuario #{$sibling->registered_by}",
                                        $sibling->duplicate_sequence,
                                    ),
                                ])
                            )
                            ->native(false)
                            ->required()
                            ->rule(Rule::in($ownerIds))
                            ->helperText('Tras el "debate interno", selecciona qué líder/coordinador queda como dueño legítimo de esta cédula. Solo aparecen líderes que ya tienen un registro para este documento.'),
                        Textarea::make('notes')
                            ->label('Motivo de la reasignación')
                            ->required()
                            ->rows(3)
                            ->helperText('Explica por qué este apoyo deja de considerarse duplicado (obligatorio para auditoría, D-03).'),
                    ];
                })
                ->action(function (array $data): void {
                    $siblings = app(VoterDuplicateAssignmentService::class)->siblingsFor($this->record->document_number);
                    $newOwnerUserId = (int) $data['new_owner_user_id'];

                    DB::transaction(function () use ($siblings, $newOwnerUserId, $data): void {
                        foreach ($siblings as $sibling) {
                            $isWinner = $sibling->registered_by === $newOwnerUserId;
                            $previousStatus = $sibling->status;
                            $newStatus = $isWinner ? VoterStatus::PENDING_REVIEW : VoterStatus::DUPLICATE;

                            $sibling->update([
                                'status' => $newStatus,
                                'registered_by' => $isWinner ? $newOwnerUserId : $sibling->registered_by,
                            ]);

                            ValidationHistory::create([
                                'voter_id' => $sibling->id,
                                'previous_status' => $previousStatus,
                                'new_status' => $newStatus,
                                'validated_by' => auth()->id(),
                                'validation_type' => 'duplicate_reassignment',
                                'notes' => $isWinner
                                    ? "{$data['notes']} [Registro confirmado como dueño legítimo de la cédula]"
                                    : "{$data['notes']} [Registro marcado como duplicado tras la reasignación]",
                            ]);
                        }
                    });

                    Notification::make()
                        ->title('Duplicado reasignado correctamente')
                        ->success()
                        ->send();

                    $this->record->refresh();
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

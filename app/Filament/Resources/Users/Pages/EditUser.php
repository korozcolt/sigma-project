<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Cargar roles actuales del usuario
        $data['roles'] = $this->record->roles->pluck('name')->toArray();

        return $data;
    }

    protected function afterSave(): void
    {
        // Sincronizar roles si fueron modificados
        if (isset($this->data['roles'])) {
            $this->record->syncRoles($this->data['roles']);
        }
    }
}

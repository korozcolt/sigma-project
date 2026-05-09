<?php

namespace App\Filament\Resources\Voters\Pages;

use App\Filament\Resources\Voters\Concerns\HasRegistraduriaPolling;
use App\Filament\Resources\Voters\VoterResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditVoter extends EditRecord
{
    use HasRegistraduriaPolling;

    protected static string $resource = VoterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->getResource()::getUrl('index');
    }
}

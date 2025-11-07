<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\VoterStatus;
use App\Filament\Resources\Users\UserResource;
use App\Models\Voter;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        // Sincronizar roles si fueron seleccionados
        if ($this->data['roles'] ?? false) {
            $this->record->syncRoles($this->data['roles']);
        }

        // Crear Voter si se marcó el toggle
        if ($this->data['register_as_voter'] ?? false) {
            $this->createVoterProfile();
        }
    }

    protected function createVoterProfile(): void
    {
        $names = $this->splitName($this->record->name);

        $voter = Voter::create([
            'campaign_id' => $this->data['voter_campaign_id'],
            'document_number' => $this->record->document_number,
            'first_name' => $names['first_name'],
            'last_name' => $names['last_name'],
            'birth_date' => $this->record->birth_date,
            'phone' => $this->record->phone,
            'secondary_phone' => $this->record->secondary_phone,
            'email' => $this->record->email,
            'municipality_id' => $this->record->municipality_id,
            'neighborhood_id' => $this->record->neighborhood_id,
            'address' => $this->record->address,
            'registered_by' => auth()->id(),
            'status' => VoterStatus::CONFIRMED,
            'notes' => $this->data['voter_notes'] ?? 'Usuario del sistema registrado como votante',
        ]);

        // Vincular el voter al usuario
        $this->record->update(['voter_id' => $voter->id]);
    }

    protected function splitName(string $fullName): array
    {
        $parts = explode(' ', trim($fullName), 2);

        return [
            'first_name' => $parts[0] ?? '',
            'last_name' => $parts[1] ?? $parts[0] ?? '',
        ];
    }
}

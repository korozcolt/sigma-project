<?php

namespace App\Observers;

use App\Enums\VoterStatus;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Support\Facades\Log;

class UserObserver
{
    /**
     * Handle the User "created" event.
     * Auto-crear registro de votante para coordinadores y líderes
     */
    public function created(User $user): void
    {
        // Solo crear votante si el user tiene documento y datos territoriales
        if (! $user->document_number || ! $user->municipality_id) {
            return;
        }

        // Obtener la primera campaña asignada al usuario
        $campaign = $user->campaigns()->first();

        if (! $campaign) {
            Log::info('User creado sin campaña asignada, no se crea registro de votante', [
                'user_id' => $user->id,
            ]);

            return;
        }

        // Separar nombre en first_name y last_name
        $nameParts = explode(' ', $user->name, 2);
        $firstName = $nameParts[0];
        $lastName = $nameParts[1] ?? '';

        // Crear registro de votante
        Voter::create([
            'user_id' => $user->id,
            'campaign_id' => $campaign->id,
            'document_number' => $user->document_number,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'birth_date' => $user->birth_date,
            'phone' => $user->phone ?? '0000000000',
            'secondary_phone' => $user->secondary_phone,
            'municipality_id' => $user->municipality_id,
            'neighborhood_id' => $user->neighborhood_id,
            'address' => $user->address,
            'registered_by' => $user->id, // Se auto-registra
            'status' => VoterStatus::CONFIRMED,
        ]);

        Log::info('Registro de votante auto-creado para user', [
            'user_id' => $user->id,
            'campaign_id' => $campaign->id,
        ]);
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        //
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        //
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        //
    }

    /**
     * Handle the User "force deleted" event.
     */
    public function forceDeleted(User $user): void
    {
        //
    }
}

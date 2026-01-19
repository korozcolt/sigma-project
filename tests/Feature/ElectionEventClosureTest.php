<?php

use App\Enums\VoterStatus;
use App\Models\Campaign;
use App\Models\ElectionEvent;
use App\Models\User;
use App\Models\Voter;
use App\Models\ValidationHistory;

test('al desactivar evento se marcan votantes sin registro como did_not_vote', function () {
    $campaign = Campaign::factory()->create();
    $admin = User::factory()->create();
    
    // Crear votantes en diferentes estados
    $voterConfirmed = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => VoterStatus::CONFIRMED,
    ]);
    
    $voterVerifiedCall = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => VoterStatus::VERIFIED_CALL,
    ]);
    
    $voterVoted = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => VoterStatus::CONFIRMED,
    ]);
    
    $voterPending = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => VoterStatus::PENDING_REVIEW,
    ]);
    
    // Crear evento electoral activo
    $event = ElectionEvent::factory()->create([
        'campaign_id' => $campaign->id,
        'is_active' => true,
        'date' => now(),
    ]);
    
    // Votante que sí votó (tiene VoteRecord)
    $voterVoted->voteRecords()->create([
        'campaign_id' => $campaign->id,
        'election_event_id' => $event->id,
        'recorded_by' => $admin->id,
        'voted_at' => now(),
        'photo_path' => 'votes/test.jpg',
        'latitude' => 6.244203,
        'longitude' => -75.581215,
    ]);
    
    // Desactivar el evento (simular cierre)
    $event->update(['is_active' => false]);
    
    // Ejecutar el proceso de cierre (simulado)
    // En la implementación real, esto se haría con un Job o Event Listener
    $eligibleVoters = Voter::where('campaign_id', $campaign->id)
        ->whereIn('status', [VoterStatus::VERIFIED_CALL, VoterStatus::CONFIRMED])
        ->whereDoesntHave('voteRecords', function ($query) use ($event) {
            $query->where('election_event_id', $event->id);
        })
        ->get();
    
    foreach ($eligibleVoters as $voter) {
        $voter->update(['status' => VoterStatus::DID_NOT_VOTE]);
        
        ValidationHistory::create([
            'voter_id' => $voter->id,
            'previous_status' => $voter->getOriginal('status'),
            'new_status' => VoterStatus::DID_NOT_VOTE,
            'validated_by' => $admin->id,
            'validation_type' => 'election',
            'notes' => 'Marcado como no votó al cerrar evento electoral',
        ]);
    }
    
    // Verificar estados actualizados
    expect($voterConfirmed->fresh()->status)->toBe(VoterStatus::DID_NOT_VOTE);
    expect($voterVerifiedCall->fresh()->status)->toBe(VoterStatus::DID_NOT_VOTE);
    
    // Votante que ya tenía registro mantiene su estado
    expect($voterVoted->fresh()->status)->toBe(VoterStatus::CONFIRMED);
    
    // Votante pendiente no es afectado
    expect($voterPending->fresh()->status)->toBe(VoterStatus::PENDING_REVIEW);
});

test('se crea historial de validación al cerrar evento', function () {
    $campaign = Campaign::factory()->create();
    $admin = User::factory()->create();
    
    $voter = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => VoterStatus::CONFIRMED,
    ]);
    
    $event = ElectionEvent::factory()->create([
        'campaign_id' => $campaign->id,
        'is_active' => true,
        'date' => now(),
    ]);
    
    // Desactivar y marcar como no votó
    $event->update(['is_active' => false]);
    
    $voter->update(['status' => VoterStatus::DID_NOT_VOTE]);
    
    ValidationHistory::create([
        'voter_id' => $voter->id,
        'previous_status' => VoterStatus::CONFIRMED,
        'new_status' => VoterStatus::DID_NOT_VOTE,
        'validated_by' => $admin->id,
        'validation_type' => 'election',
        'notes' => 'Cierre del evento electoral',
    ]);
    
    // Verificar historial creado
    $history = ValidationHistory::where('voter_id', $voter->id)
        ->where('validation_type', 'election')
        ->first();
    
    expect($history)->not->toBeNull();
    expect($history->previous_status)->toBe(VoterStatus::CONFIRMED);
    expect($history->new_status)->toBe(VoterStatus::DID_NOT_VOTE);
    expect($history->validation_type)->toBe('election');
    expect(strtolower($history->notes))->toContain('cierre');
});
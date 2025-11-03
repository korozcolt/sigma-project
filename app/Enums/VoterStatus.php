<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum VoterStatus: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    case PENDING_REVIEW = 'pending_review';
    case REJECTED_CENSUS = 'rejected_census';
    case VERIFIED_CENSUS = 'verified_census';
    case CORRECTION_REQUIRED = 'correction_required';
    case VERIFIED_CALL = 'verified_call';
    case CONFIRMED = 'confirmed';
    case VOTED = 'voted';
    case DID_NOT_VOTE = 'did_not_vote';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PENDING_REVIEW => 'Pendiente de Revisión',
            self::REJECTED_CENSUS => 'Rechazado en Censo',
            self::VERIFIED_CENSUS => 'Verificado en Censo',
            self::CORRECTION_REQUIRED => 'Requiere Corrección',
            self::VERIFIED_CALL => 'Verificado por Llamada',
            self::CONFIRMED => 'Confirmado',
            self::VOTED => 'Votó',
            self::DID_NOT_VOTE => 'No Votó',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PENDING_REVIEW => 'gray',
            self::REJECTED_CENSUS => 'danger',
            self::VERIFIED_CENSUS => 'info',
            self::CORRECTION_REQUIRED => 'warning',
            self::VERIFIED_CALL => 'success',
            self::CONFIRMED => 'success',
            self::VOTED => 'success',
            self::DID_NOT_VOTE => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::PENDING_REVIEW => 'heroicon-m-clock',
            self::REJECTED_CENSUS => 'heroicon-m-x-circle',
            self::VERIFIED_CENSUS => 'heroicon-m-check-badge',
            self::CORRECTION_REQUIRED => 'heroicon-m-exclamation-triangle',
            self::VERIFIED_CALL => 'heroicon-m-phone',
            self::CONFIRMED => 'heroicon-m-check-circle',
            self::VOTED => 'heroicon-m-hand-thumb-up',
            self::DID_NOT_VOTE => 'heroicon-m-hand-thumb-down',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::PENDING_REVIEW => 'El votante está pendiente de revisión inicial',
            self::REJECTED_CENSUS => 'El votante fue rechazado al validar contra el censo electoral',
            self::VERIFIED_CENSUS => 'El votante fue verificado exitosamente en el censo electoral',
            self::CORRECTION_REQUIRED => 'Los datos del votante requieren corrección antes de continuar',
            self::VERIFIED_CALL => 'El votante fue verificado mediante llamada telefónica',
            self::CONFIRMED => 'El votante confirmó su asistencia a votar',
            self::VOTED => 'El votante ejerció su derecho al voto',
            self::DID_NOT_VOTE => 'El votante no asistió a votar',
        };
    }
}

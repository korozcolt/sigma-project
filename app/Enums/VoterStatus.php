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
    case CENSUS_NOT_FOUND = 'census_not_found';
    case VERIFIED_CENSUS = 'verified_census';
    case VERIFIED_REGISTRADURIA = 'verified_registraduria';
    case CORRECTION_REQUIRED = 'correction_required';
    case VERIFIED_CALL = 'verified_call';
    case CONFIRMED = 'confirmed';
    case VOTED = 'voted';
    case DID_NOT_VOTE = 'did_not_vote';
    case DUPLICATE = 'duplicate';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PENDING_REVIEW => 'Pendiente de Revisión',
            self::REJECTED_CENSUS => 'Rechazado en Censo',
            self::CENSUS_NOT_FOUND => 'No Encontrado en Censo',
            self::VERIFIED_CENSUS => 'Verificado en Censo',
            self::VERIFIED_REGISTRADURIA => 'Verificado por Registraduría',
            self::CORRECTION_REQUIRED => 'Requiere Corrección',
            self::VERIFIED_CALL => 'Verificado por Llamada',
            self::CONFIRMED => 'Confirmado',
            self::VOTED => 'Votó',
            self::DID_NOT_VOTE => 'No Votó',
            self::DUPLICATE => 'Duplicado en Disputa',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PENDING_REVIEW => 'gray',
            self::REJECTED_CENSUS => 'danger',
            self::CENSUS_NOT_FOUND => 'warning',
            self::VERIFIED_CENSUS => 'info',
            self::VERIFIED_REGISTRADURIA => 'success',
            self::CORRECTION_REQUIRED => 'warning',
            self::VERIFIED_CALL => 'success',
            self::CONFIRMED => 'success',
            self::VOTED => 'success',
            self::DID_NOT_VOTE => 'danger',
            self::DUPLICATE => 'warning',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::PENDING_REVIEW => 'heroicon-m-clock',
            self::REJECTED_CENSUS => 'heroicon-m-x-circle',
            self::CENSUS_NOT_FOUND => 'heroicon-m-question-mark-circle',
            self::VERIFIED_CENSUS => 'heroicon-m-check-badge',
            self::VERIFIED_REGISTRADURIA => 'heroicon-m-shield-check',
            self::CORRECTION_REQUIRED => 'heroicon-m-exclamation-triangle',
            self::VERIFIED_CALL => 'heroicon-m-phone',
            self::CONFIRMED => 'heroicon-m-check-circle',
            self::VOTED => 'heroicon-m-hand-thumb-up',
            self::DID_NOT_VOTE => 'heroicon-m-hand-thumb-down',
            self::DUPLICATE => 'heroicon-m-document-duplicate',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::PENDING_REVIEW => 'El apoyo está pendiente de revisión inicial',
            self::REJECTED_CENSUS => 'El apoyo fue rechazado al validar contra el censo electoral',
            self::CENSUS_NOT_FOUND => 'El apoyo no se encontró en el censo electoral local al momento de registrarlo; requiere revisión o reconciliación en segundo plano.',
            self::VERIFIED_CENSUS => 'El apoyo fue verificado exitosamente en el censo electoral',
            self::VERIFIED_REGISTRADURIA => 'El apoyo fue verificado directamente contra un resultado en vivo de la Registraduría — la fuente más confiable disponible, más fuerte que el censo local',
            self::CORRECTION_REQUIRED => 'Los datos del apoyo requieren corrección antes de continuar',
            self::VERIFIED_CALL => 'El apoyo fue verificado mediante llamada telefónica',
            self::CONFIRMED => 'El apoyo confirmó su asistencia a votar',
            self::VOTED => 'El apoyo ejerció su derecho al voto',
            self::DID_NOT_VOTE => 'El apoyo no asistió a votar',
            self::DUPLICATE => 'El apoyo tiene una cédula duplicada pendiente de resolución por un administrador',
        };
    }
}

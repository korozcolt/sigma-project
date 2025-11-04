<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum CallResult: string implements HasColor, HasIcon, HasLabel
{
    case ANSWERED = 'answered';
    case NO_ANSWER = 'no_answer';
    case BUSY = 'busy';
    case WRONG_NUMBER = 'wrong_number';
    case REJECTED = 'rejected';
    case CALLBACK_REQUESTED = 'callback_requested';
    case NOT_INTERESTED = 'not_interested';
    case CONFIRMED = 'confirmed';
    case INVALID_NUMBER = 'invalid_number';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::ANSWERED => 'Respondió',
            self::NO_ANSWER => 'No Contestó',
            self::BUSY => 'Ocupado',
            self::WRONG_NUMBER => 'Número Equivocado',
            self::REJECTED => 'Rechazó Llamada',
            self::CALLBACK_REQUESTED => 'Solicita Llamada Posterior',
            self::NOT_INTERESTED => 'No Interesado',
            self::CONFIRMED => 'Confirmado',
            self::INVALID_NUMBER => 'Número Inválido',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::CONFIRMED => 'success',
            self::ANSWERED => 'info',
            self::CALLBACK_REQUESTED => 'warning',
            self::NO_ANSWER, self::BUSY => 'gray',
            self::NOT_INTERESTED, self::REJECTED => 'danger',
            self::WRONG_NUMBER, self::INVALID_NUMBER => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::CONFIRMED => 'heroicon-o-check-circle',
            self::ANSWERED => 'heroicon-o-phone',
            self::CALLBACK_REQUESTED => 'heroicon-o-clock',
            self::NO_ANSWER => 'heroicon-o-phone-x-mark',
            self::BUSY => 'heroicon-o-phone-arrow-down-left',
            self::NOT_INTERESTED => 'heroicon-o-hand-raised',
            self::REJECTED => 'heroicon-o-x-circle',
            self::WRONG_NUMBER, self::INVALID_NUMBER => 'heroicon-o-exclamation-triangle',
        };
    }

    /**
     * Check if this result indicates a successful contact
     */
    public function isSuccessfulContact(): bool
    {
        return in_array($this, [
            self::ANSWERED,
            self::CONFIRMED,
            self::CALLBACK_REQUESTED,
        ]);
    }

    /**
     * Check if this result requires a follow-up call
     */
    public function requiresFollowUp(): bool
    {
        return in_array($this, [
            self::NO_ANSWER,
            self::BUSY,
            self::CALLBACK_REQUESTED,
        ]);
    }

    /**
     * Check if this result indicates the number is invalid
     */
    public function isInvalidNumber(): bool
    {
        return in_array($this, [
            self::WRONG_NUMBER,
            self::INVALID_NUMBER,
        ]);
    }
}

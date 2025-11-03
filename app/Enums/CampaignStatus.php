<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum CampaignStatus: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case PAUSED = 'paused';
    case COMPLETED = 'completed';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::DRAFT => 'Borrador',
            self::ACTIVE => 'Activa',
            self::PAUSED => 'Pausada',
            self::COMPLETED => 'Completada',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::ACTIVE => 'success',
            self::PAUSED => 'warning',
            self::COMPLETED => 'info',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::DRAFT => 'heroicon-m-document-text',
            self::ACTIVE => 'heroicon-m-bolt',
            self::PAUSED => 'heroicon-m-pause-circle',
            self::COMPLETED => 'heroicon-m-check-circle',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::DRAFT => 'Campaña en preparación, no visible para el equipo.',
            self::ACTIVE => 'Campaña en curso, equipo activo registrando votantes.',
            self::PAUSED => 'Campaña temporalmente pausada, sin nuevos registros.',
            self::COMPLETED => 'Campaña finalizada, solo lectura y reportes.',
        };
    }
}

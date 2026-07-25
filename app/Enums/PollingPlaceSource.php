<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PollingPlaceSource: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    case LIVE = 'live';
    case DB_RECONSTRUCTION = 'db_reconstruction';
    case SNAPSHOT = 'snapshot';
    case MANUAL = 'manual';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::LIVE => 'En Vivo',
            self::DB_RECONSTRUCTION => 'Reconstruido en Base de Datos',
            self::SNAPSHOT => 'Snapshot Nacional',
            self::MANUAL => 'Manual',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::LIVE => 'success',
            self::DB_RECONSTRUCTION => 'info',
            self::SNAPSHOT => 'warning',
            self::MANUAL => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::LIVE => 'heroicon-m-signal',
            self::DB_RECONSTRUCTION => 'heroicon-m-circle-stack',
            self::SNAPSHOT => 'heroicon-m-archive-box',
            self::MANUAL => 'heroicon-m-pencil',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::LIVE => 'El puesto de votación fue resuelto en tiempo real desde la Registraduría',
            self::DB_RECONSTRUCTION => 'El puesto de votación fue reconstruido desde el censo registrado de la campaña',
            self::SNAPSHOT => 'El puesto de votación proviene del snapshot nacional de censo (respaldo sin conexión)',
            self::MANUAL => 'El puesto de votación fue asignado manualmente por un operador',
        };
    }

    /** Lower number = more trusted. Used by PollingPlaceResolver's no-downgrade guard (SRC-02). */
    public function precedence(): int
    {
        return match ($this) {
            self::LIVE => 0,
            self::DB_RECONSTRUCTION => 1,
            self::SNAPSHOT => 2,
            self::MANUAL => 3,
        };
    }

    public function outranks(self $other): bool
    {
        return $this->precedence() < $other->precedence();
    }
}

<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel, HasColor, HasIcon, HasDescription
{
    case SUPER_ADMIN = 'super_admin';
    case ADMIN_CAMPAIGN = 'admin_campaign';
    case COORDINATOR = 'coordinator';
    case LEADER = 'leader';
    case REVIEWER = 'reviewer';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Administrador',
            self::ADMIN_CAMPAIGN => 'Administrador de Campaña',
            self::COORDINATOR => 'Coordinador',
            self::LEADER => 'Líder',
            self::REVIEWER => 'Revisor',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::SUPER_ADMIN => 'danger',
            self::ADMIN_CAMPAIGN => 'warning',
            self::COORDINATOR => 'primary',
            self::LEADER => 'success',
            self::REVIEWER => 'info',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'heroicon-m-shield-check',
            self::ADMIN_CAMPAIGN => 'heroicon-m-user-circle',
            self::COORDINATOR => 'heroicon-m-users',
            self::LEADER => 'heroicon-m-user',
            self::REVIEWER => 'heroicon-m-eye',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Acceso completo al sistema y gestión de todas las campañas.',
            self::ADMIN_CAMPAIGN => 'Administra una campaña específica y su equipo.',
            self::COORDINATOR => 'Coordina líderes en un territorio específico.',
            self::LEADER => 'Registra y gestiona votantes en su zona.',
            self::REVIEWER => 'Valida votantes y realiza llamadas de verificación.',
        };
    }

    /**
     * Get all role values as array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get all role names as array
     */
    public static function names(): array
    {
        return array_column(self::cases(), 'name');
    }
}

<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    case SUPER_ADMIN = 'super_admin';
    case ADMIN_CAMPAIGN = 'admin_campaign';
    case COORDINATOR = 'coordinator';
    case AREA_COORDINATOR = 'area_coordinator';
    case LEADER = 'leader';
    case REVIEWER = 'reviewer';
    case REPORTS_VIEWER = 'reports_viewer';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Administrador',
            self::ADMIN_CAMPAIGN => 'Administrador de Campaña',
            self::COORDINATOR => 'Coordinador',
            self::AREA_COORDINATOR => 'Articulador',
            self::LEADER => 'Líder',
            self::REVIEWER => 'Revisor',
            self::REPORTS_VIEWER => 'Analista de Reportes',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::SUPER_ADMIN => 'danger',
            self::ADMIN_CAMPAIGN => 'warning',
            self::COORDINATOR => 'primary',
            self::AREA_COORDINATOR => 'primary',
            self::LEADER => 'success',
            self::REVIEWER => 'info',
            self::REPORTS_VIEWER => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'heroicon-m-shield-check',
            self::ADMIN_CAMPAIGN => 'heroicon-m-user-circle',
            self::COORDINATOR => 'heroicon-m-users',
            self::AREA_COORDINATOR => 'heroicon-m-user-group',
            self::LEADER => 'heroicon-m-user',
            self::REVIEWER => 'heroicon-m-eye',
            self::REPORTS_VIEWER => 'heroicon-m-chart-bar',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Acceso completo al sistema y gestión de todas las campañas.',
            self::ADMIN_CAMPAIGN => 'Administra una campaña específica y su equipo.',
            self::COORDINATOR => 'Coordina líderes en un territorio específico.',
            self::AREA_COORDINATOR => 'Organiza y gestiona un conjunto de coordinadores; un nivel jerárquico por encima del coordinador, sin anidamiento adicional.',
            self::LEADER => 'Registra y gestiona apoyos en su zona.',
            self::REVIEWER => 'Valida apoyos y realiza llamadas de verificación.',
            self::REPORTS_VIEWER => 'Consulta reportes y listados de la campaña activa; sin permisos de creación, edición o eliminación.',
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

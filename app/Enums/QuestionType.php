<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum QuestionType: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    case YES_NO = 'yes_no';
    case SCALE = 'scale';
    case TEXT = 'text';
    case MULTIPLE_CHOICE = 'multiple_choice';
    case SINGLE_CHOICE = 'single_choice';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::YES_NO => 'Sí/No',
            self::SCALE => 'Escala Numérica',
            self::TEXT => 'Texto Libre',
            self::MULTIPLE_CHOICE => 'Opción Múltiple',
            self::SINGLE_CHOICE => 'Selección Única',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::YES_NO => 'success',
            self::SCALE => 'info',
            self::TEXT => 'gray',
            self::MULTIPLE_CHOICE => 'warning',
            self::SINGLE_CHOICE => 'primary',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::YES_NO => 'heroicon-m-check-circle',
            self::SCALE => 'heroicon-m-chart-bar',
            self::TEXT => 'heroicon-m-document-text',
            self::MULTIPLE_CHOICE => 'heroicon-m-queue-list',
            self::SINGLE_CHOICE => 'heroicon-m-circle-stack',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::YES_NO => 'Pregunta con respuesta Sí o No',
            self::SCALE => 'Pregunta con escala numérica (ej: 1-5, 1-10)',
            self::TEXT => 'Pregunta con respuesta de texto libre',
            self::MULTIPLE_CHOICE => 'Pregunta con múltiples opciones seleccionables',
            self::SINGLE_CHOICE => 'Pregunta con una única opción seleccionable',
        };
    }
}

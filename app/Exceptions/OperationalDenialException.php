<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class OperationalDenialException extends RuntimeException
{
    public static function campaignScope(string $detail): self
    {
        return new self("Contexto de campaña requerido: {$detail}");
    }

    public static function territorialOwnership(string $detail): self
    {
        return new self("Restricción de propiedad territorial: {$detail}");
    }
}

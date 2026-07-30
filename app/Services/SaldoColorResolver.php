<?php

declare(strict_types=1);

namespace App\Services;

class SaldoColorResolver
{
    // 2captcha (USD) — umbrales aprobados por el usuario, ajustables
    private const TWOCAPTCHA_GREEN_MIN_USD = 5.0;

    private const TWOCAPTCHA_YELLOW_MIN_USD = 1.0;

    // TODO ajustar con el usuario — valores provisionales (COP), aún sin confirmar
    private const HABLAME_GREEN_MIN_COP = 500000.0;

    private const HABLAME_YELLOW_MIN_COP = 100000.0;

    private const GRAY_UNAVAILABLE = 'bg-gray-100 text-gray-600';

    private const GREEN = 'bg-green-100 text-green-700';

    private const YELLOW = 'bg-yellow-100 text-yellow-700';

    private const RED = 'bg-red-100 text-red-700';

    public static function twoCaptcha(?float $usd): string
    {
        if ($usd === null) {
            return self::GRAY_UNAVAILABLE;
        }

        return match (true) {
            $usd >= self::TWOCAPTCHA_GREEN_MIN_USD => self::GREEN,
            $usd >= self::TWOCAPTCHA_YELLOW_MIN_USD => self::YELLOW,
            default => self::RED,
        };
    }

    public static function hablame(?float $cop): string
    {
        if ($cop === null) {
            return self::GRAY_UNAVAILABLE;
        }

        return match (true) {
            $cop >= self::HABLAME_GREEN_MIN_COP => self::GREEN,
            $cop >= self::HABLAME_YELLOW_MIN_COP => self::YELLOW,
            default => self::RED,
        };
    }
}

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

    private const GRAY_UNAVAILABLE = 'gray';

    private const GREEN = 'success';

    private const YELLOW = 'warning';

    private const RED = 'danger';

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

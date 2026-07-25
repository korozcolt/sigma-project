<?php

declare(strict_types=1);

namespace App\Services;

interface LiveSourceAdapter
{
    /** @throws \Exception if the service is unreachable or returns an error */
    public function startLookup(string $cedula): string;

    /**
     * @return array{status: string, data: array<string,string>|null, error: string|null}
     */
    public function getResult(string $sessionId): array;

    /** Cheap reachability check, no captcha cost. */
    public function isReachable(): bool;
}

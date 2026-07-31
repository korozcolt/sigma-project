<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ConsultaCensoService implements LiveSourceAdapter
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.consulta_censo.url', 'http://localhost:5757');
    }

    public function startLookup(string $cedula): string
    {
        $response = Http::timeout(10)->post("{$this->baseUrl}/lookup/censo", ['cedula' => $cedula]);

        if (! $response->successful()) {
            Log::error('ConsultaCensoService: lookup failed', [
                'cedula' => $cedula,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('El servicio de consultacenso no esta disponible. Inicia el servicio Python primero.');
        }

        $sessionId = $response->json('session_id');

        if (! $sessionId) {
            throw new \Exception('El servicio de consultacenso no devolvio un session_id valido.');
        }

        return $sessionId;
    }

    /**
     * @return array{status: string, data: array<string, string>|null, error: string|null}
     */
    public function getResult(string $sessionId): array
    {
        $response = Http::timeout(5)->get("{$this->baseUrl}/result/{$sessionId}");

        if ($response->status() === 404) {
            return ['status' => 'error', 'data' => null, 'error' => 'Sesion no encontrada'];
        }

        if (! $response->successful()) {
            return ['status' => 'error', 'data' => null, 'error' => 'Error comunicandose con el servicio'];
        }

        return $response->json();
    }

    public function isReachable(): bool
    {
        if (! config('services.consulta_censo.live_enabled')) {
            return false;
        }

        try {
            $response = Http::connectTimeout(2)->timeout(3)->withoutRedirecting()->get(config('services.consulta_censo.probe_url'));

            return $response->successful() || $response->redirect();
        } catch (ConnectionException) {
            return false;
        }
    }
}

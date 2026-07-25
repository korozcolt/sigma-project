<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RegistraduriaService implements LiveSourceAdapter
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.registraduria.url', 'http://localhost:5757');
    }

    /**
     * Start an async lookup for the given cedula.
     *
     * Returns the session_id from the Python service.
     *
     * @throws \Exception if the service is unreachable or returns an error
     */
    public function startLookup(string $cedula): string
    {
        $response = Http::timeout(10)
            ->post("{$this->baseUrl}/lookup", ['cedula' => $cedula]);

        if (! $response->successful()) {
            Log::error('RegistraduriaService: lookup failed', [
                'cedula' => $cedula,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('El servicio de Registraduría no está disponible. Inicia el servicio Python primero.');
        }

        $sessionId = $response->json('session_id');

        if (! $sessionId) {
            throw new \Exception('El servicio de Registraduría no devolvió un session_id válido.');
        }

        return $sessionId;
    }

    /**
     * Poll the result for a given session.
     *
     * @return array{status: string, data: array<string, string>|null, error: string|null}
     *                                                                                     status: pending | waiting_captcha | done | error
     *                                                                                     data: puesto_nombre, puesto_codigo, zona_codigo, mesa_numero, departamento, municipio, direccion
     */
    public function getResult(string $sessionId): array
    {
        $response = Http::timeout(5)
            ->get("{$this->baseUrl}/result/{$sessionId}");

        if ($response->status() === 404) {
            return ['status' => 'error', 'data' => null, 'error' => 'Sesión no encontrada'];
        }

        if (! $response->successful()) {
            return ['status' => 'error', 'data' => null, 'error' => 'Error comunicándose con el servicio'];
        }

        return $response->json();
    }

    /**
     * Cheap reachability check against the actual external Registraduría/infovotantes
     * host, independent of this class's own async startLookup()/getResult() contract.
     * startLookup() always returns HTTP 200 immediately regardless of whether the real
     * site is up (it only enqueues a background thread) — it must NEVER be used to
     * gate a "should we even try live" decision. See 08-RESEARCH.md Pattern 2.
     */
    public function isReachable(): bool
    {
        if (! config('services.registraduria.live_enabled')) {
            return false;
        }

        try {
            $response = Http::connectTimeout(2)->timeout(3)->withoutRedirecting()->head(config('services.registraduria.probe_url'));

            return $response->successful() || $response->redirect();
        } catch (ConnectionException) {
            return false;
        }
    }
}

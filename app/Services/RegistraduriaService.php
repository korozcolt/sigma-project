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

        $payload = $response->json();

        if (($payload['status'] ?? null) === 'done' && ! empty($payload['data']['raw_message_html'] ?? null)) {
            $payload['data'] = $this->parseConsultaHtml($payload['data']['raw_message_html']);
        }

        return $payload;
    }

    /**
     * Parse the wsp `#consulta` success table HTML into the 7 structured fields
     * documented on getResult(). Derived from the real captured fixture
     * (tests/fixtures/registraduria/consulta-sample.html): header labels are
     * NUIP, DEPARTAMENTO, MUNICIPIO, PUESTO, DIRECCIÓN, MESA — there is no
     * "CODIGO PUESTO"/"ZONA" column in the real response, so puesto_codigo and
     * zona_codigo remain empty unless a future response shape includes them.
     *
     * Never throws: malformed/empty HTML degrades to all-empty fields so a
     * parse failure falls back to snapshot instead of crashing reconciliation.
     *
     * @return array{puesto_nombre: string, puesto_codigo: string, zona_codigo: string, mesa_numero: string, departamento: string, municipio: string, direccion: string}
     */
    private function parseConsultaHtml(string $html): array
    {
        $fields = [
            'puesto_nombre' => '',
            'puesto_codigo' => '',
            'zona_codigo' => '',
            'mesa_numero' => '',
            'departamento' => '',
            'municipio' => '',
            'direccion' => '',
        ];

        if (trim($html) === '') {
            return $fields;
        }

        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        $headers = $xpath->query("//table[@id='consulta']//thead//th");
        $row = $xpath->query("//table[@id='consulta']//tbody/tr[1]/td");

        if ($headers === false || $row === false || $headers->length === 0 || $row->length === 0) {
            return $fields;
        }

        $labelToField = [
            'PUESTO' => 'puesto_nombre',
            'LUGAR' => 'puesto_nombre',
            'CODIGO PUESTO' => 'puesto_codigo',
            'ZONA' => 'zona_codigo',
            'MESA' => 'mesa_numero',
            'DEPARTAMENTO' => 'departamento',
            'MUNICIPIO' => 'municipio',
            'DIRECCION' => 'direccion',
        ];

        foreach ($headers as $i => $header) {
            $label = mb_strtoupper(trim(str_replace(
                ['Á', 'É', 'Í', 'Ó', 'Ú'],
                ['A', 'E', 'I', 'O', 'U'],
                $header->textContent
            )));
            $field = $labelToField[$label] ?? null;

            if ($field !== null && $row->item($i) !== null) {
                $fields[$field] = trim($row->item($i)->textContent);
            }
        }

        return $fields;
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
            $response = Http::connectTimeout(2)->timeout(3)->withoutRedirecting()->get(config('services.registraduria.probe_url'));

            return $response->successful() || $response->redirect();
        } catch (ConnectionException) {
            return false;
        }
    }
}

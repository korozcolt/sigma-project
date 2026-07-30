<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TwoCaptchaService
{
    protected ?string $apiKey;

    protected string $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.twocaptcha.api_key');
        $this->apiUrl = config('services.twocaptcha.api_url');
    }

    /**
     * Consulta el saldo (USD) de la cuenta 2captcha. Degrada a null ante
     * cualquier fallo (clave ausente, error de la API, timeout/excepción de
     * conexión) — nunca lanza, ya que esta llamada alimenta un cuadrito de
     * topbar que no debe romper el panel admin.
     */
    public function getBalance(): ?float
    {
        if (! $this->apiKey) {
            return null;
        }

        try {
            $response = Http::timeout(10)
                ->acceptJson()
                ->post("{$this->apiUrl}/getBalance", [
                    'clientKey' => $this->apiKey,
                ]);

            if ($response->successful()) {
                $json = $response->json();

                if (($json['errorId'] ?? null) === 0 && isset($json['balance'])) {
                    return (float) $json['balance'];
                }

                Log::warning('2captcha getBalance devolvió un error', [
                    'errorId' => $json['errorId'] ?? null,
                    'errorCode' => $json['errorCode'] ?? null,
                ]);

                return null;
            }

            Log::warning('2captcha getBalance respondió con status no exitoso', [
                'status' => $response->status(),
            ]);

            return null;
        } catch (\Throwable $e) {
            Log::error('2captcha getBalance lanzó una excepción', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}

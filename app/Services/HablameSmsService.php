<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Message;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HablameSmsService
{
    protected ?string $apiKey;

    protected string $apiUrl;

    protected string $from;

    protected bool $sandboxMode;

    public function __construct()
    {
        $this->apiKey = config('services.hablame.api_key');
        $this->apiUrl = config('services.hablame.api_url');
        $this->from = config('services.hablame.from');
        $this->sandboxMode = config('services.hablame.sandbox_mode', false);
    }

    /**
     * Enviar SMS a través de la API de Hablame v5
     */
    public function send(Message $message): array
    {
        if (! $this->apiKey) {
            throw new \Exception('Hablame API Key no configurada. Verificar HABLAME_API_KEY en .env');
        }

        // Validar número de teléfono
        $phone = $this->formatPhoneNumber($message->voter->phone);

        if (! $phone) {
            throw new \Exception('Número de teléfono inválido: '.$message->voter->phone);
        }

        // Modo sandbox: simular respuesta exitosa sin consumir saldo
        if ($this->sandboxMode) {
            return $this->sandboxResponse($phone, $message->content);
        }

        try {
            $response = Http::withHeaders([
                'X-Hablame-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout(30)
                ->post("{$this->apiUrl}/sms/v5/send", [
                    'to' => [$phone],
                    'from' => $this->from,
                    'message' => $message->content,
                    'priority' => 1,
                ]);

            // Log de la solicitud
            Log::info('Hablame SMS API Request', [
                'message_id' => $message->id,
                'to' => $phone,
                'from' => $this->from,
                'status' => $response->status(),
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'batch_id' => $data['payLoad']['batch_id'] ?? null,
                    'sent' => $data['payLoad']['sent'] ?? 0,
                    'failed' => $data['payLoad']['failed'] ?? 0,
                    'cost' => $data['payLoad']['cost'] ?? 0,
                    'status_code' => $data['statusCode'] ?? 200,
                    'status_message' => $data['statusMessage'] ?? 'OK',
                    'response_time' => $data['responseTime'] ?? null,
                ];
            }

            // Manejo de errores HTTP
            $errorData = $response->json();

            Log::error('Hablame SMS API Error', [
                'message_id' => $message->id,
                'status' => $response->status(),
                'error' => $errorData,
            ]);

            return [
                'success' => false,
                'error' => $errorData['statusMessage'] ?? 'Error desconocido',
                'status_code' => $errorData['statusCode'] ?? $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('Hablame SMS Exception', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Obtener información de la cuenta
     */
    public function getAccountInfo(): array
    {
        if (! $this->apiKey) {
            throw new \Exception('Hablame API Key no configurada');
        }

        if ($this->sandboxMode) {
            return [
                'success' => true,
                'account_id' => 'sandbox_account',
                'status' => 'active',
                'balance' => 999.99,
                'billing_type' => 'prepaid',
            ];
        }

        try {
            $response = Http::withHeaders([
                'X-Hablame-Key' => $this->apiKey,
            ])
                ->timeout(10)
                ->get("{$this->apiUrl}/v5/account/info");

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'account_id' => $data['payLoad']['account_id'] ?? null,
                    'status' => $data['payLoad']['status'] ?? null,
                    'balance' => $data['payLoad']['balance'] ?? null,
                    'billing_type' => $data['payLoad']['billing_type'] ?? null,
                    'created_at' => $data['payLoad']['created_at'] ?? null,
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['statusMessage'] ?? 'Error obteniendo información de cuenta',
            ];
        } catch (\Exception $e) {
            Log::error('Hablame Account Info Error', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Formatear número de teléfono a formato internacional
     */
    protected function formatPhoneNumber(string $phone): ?string
    {
        // Eliminar espacios, guiones y paréntesis
        $phone = preg_replace('/[\s\-\(\)]/', '', $phone);

        // Si ya tiene +57, retornar
        if (str_starts_with($phone, '+57')) {
            return $phone;
        }

        // Si empieza con 57, agregar +
        if (str_starts_with($phone, '57')) {
            return '+'.$phone;
        }

        // Si es número de 10 dígitos (Colombia), agregar +57
        if (strlen($phone) === 10 && preg_match('/^3\d{9}$/', $phone)) {
            return '+57'.$phone;
        }

        // Número inválido
        return null;
    }

    /**
     * Respuesta simulada para modo sandbox
     */
    protected function sandboxResponse(string $phone, string $content): array
    {
        Log::info('Hablame SMS Sandbox Mode', [
            'to' => $phone,
            'from' => $this->from,
            'message' => $content,
        ]);

        return [
            'success' => true,
            'batch_id' => 'sandbox_'.uniqid(),
            'sent' => 1,
            'failed' => 0,
            'cost' => 0.034,
            'status_code' => 201,
            'status_message' => 'Message sent successfully (Sandbox Mode)',
            'response_time' => '50',
        ];
    }

    /**
     * Verificar si la API Key es válida
     */
    public function validateApiKey(): bool
    {
        if (! $this->apiKey) {
            return false;
        }

        if ($this->sandboxMode) {
            return true;
        }

        $accountInfo = $this->getAccountInfo();

        return $accountInfo['success'] ?? false;
    }
}

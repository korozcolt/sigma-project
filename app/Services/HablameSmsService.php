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
                'Accept' => 'application/json',
            ])
                ->timeout(30)
                ->post("{$this->apiUrl}/sms/v5/send", [
                    'messages' => [
                        [
                            'to' => $phone,
                            'text' => $message->content,
                        ],
                    ],
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $payload = $data['payLoad'] ?? [];
                $messages = $payload['messages'] ?? [];
                $firstMessage = $messages[0] ?? [];

                // Log completo de la respuesta para debug
                Log::info('Hablame SMS API Full Response', [
                    'message_id' => $message->id,
                    'response' => $data,
                ]);

                // Calcular mensajes enviados vs fallidos basado en statusId
                // statusId 102 = enviado exitosamente
                // statusId 106 = programado/en cola (también es exitoso)
                $sent = 0;
                $failed = 0;
                $totalCost = 0;

                foreach ($messages as $msg) {
                    $statusId = $msg['statusId'] ?? 0;

                    Log::info('Processing message statusId', [
                        'statusId' => $statusId,
                        'message' => $msg,
                    ]);

                    // Considerar exitosos: 102 (enviado) y 106 (programado/en cola)
                    if (in_array($statusId, [102, 106])) {
                        $sent++;
                    } else {
                        $failed++;
                    }
                    $totalCost += $msg['price'] ?? 0;
                }

                $result = [
                    'success' => true,
                    // Preferir batch_id del payload si está disponible; de lo contrario usar id del primer mensaje
                    'batch_id' => $payload['batch_id'] ?? ($firstMessage['id'] ?? null),
                    'sent' => $sent,
                    'failed' => $failed,
                    'cost' => $totalCost,
                    'sms_qty' => $payload['smsQty'] ?? 1,
                    'status_code' => $data['statusCode'] ?? 200,
                    'status_message' => $data['statusMessage'] ?? 'OK',
                    'response_time' => $data['responseTime'] ?? null,
                    'account_id' => $payload['accountId'] ?? null,
                    'send_date' => $payload['sendDate'] ?? null,
                ];

                // Log de respuesta exitosa
                Log::info('Hablame SMS API Success', [
                    'message_id' => $message->id,
                    'to' => $phone,
                    'batch_id' => $result['batch_id'],
                    'sent' => $result['sent'],
                    'cost' => $result['cost'],
                    'response_time' => $result['response_time'].'s',
                ]);

                return $result;
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
     * Formatear número de teléfono para API Hablame
     * La API acepta números de 10 dígitos sin prefijo internacional
     */
    protected function formatPhoneNumber(string $phone): ?string
    {
        // Eliminar espacios, guiones, paréntesis y símbolos
        $phone = preg_replace('/[\s\-\(\)\+]/', '', $phone);

        // Si empieza con 57 (código de Colombia), removerlo para tener solo 10 dígitos
        if (str_starts_with($phone, '57') && strlen($phone) === 12) {
            $phone = substr($phone, 2);
        }

        // Validar que sea número de 10 dígitos válido para Colombia (inicia con 3)
        if (strlen($phone) === 10 && preg_match('/^3\d{9}$/', $phone)) {
            return $phone;
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
            'request' => [
                'messages' => [
                    ['to' => $phone, 'text' => $content],
                ],
            ],
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

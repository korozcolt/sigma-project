<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendMessage;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Voter;
use Illuminate\Support\Facades\Log;

class MessageService
{
    public function send(Message $message): void
    {
        if ($message->isSent()) {
            return;
        }

        try {
            $result = match ($message->channel) {
                'whatsapp' => $this->sendWhatsApp($message),
                'sms' => $this->sendSMS($message),
                'email' => $this->sendEmail($message),
                default => throw new \InvalidArgumentException("Canal no soportado: {$message->channel}"),
            };

            if ($result['success']) {
                $message->markAsSent($result['external_id'] ?? null);

                if ($message->batch) {
                    $message->batch->incrementSent();
                }
            } else {
                throw new \Exception($result['error'] ?? 'Error desconocido al enviar mensaje');
            }
        } catch (\Exception $e) {
            Log::error('Error enviando mensaje', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);

            $message->markAsFailed($e->getMessage());

            if ($message->batch) {
                $message->batch->incrementFailed();
            }

            throw $e;
        }
    }

    public function schedule(Message $message): void
    {
        if ($message->scheduled_for && $message->scheduled_for->isFuture()) {
            SendMessage::dispatch($message)->delay($message->scheduled_for);
        } else {
            SendMessage::dispatch($message);
        }
    }

    public function createFromTemplate(
        MessageTemplate $template,
        Voter $voter,
        array $variables = [],
        ?\DateTime $scheduledFor = null
    ): Message {
        $content = $template->renderContent($variables);
        $subject = $template->renderSubject($variables);

        return Message::create([
            'campaign_id' => $template->campaign_id,
            'voter_id' => $voter->id,
            'template_id' => $template->id,
            'type' => $template->type,
            'channel' => $template->channel,
            'subject' => $subject,
            'content' => $content,
            'status' => $scheduledFor ? 'scheduled' : 'pending',
            'scheduled_for' => $scheduledFor,
        ]);
    }

    protected function sendWhatsApp(Message $message): array
    {
        // Simulación - En producción usar API real de Hablame o similar
        Log::info('Enviando WhatsApp', [
            'to' => $message->voter->phone,
            'content' => $message->content,
        ]);

        // return $this->sendViaHablame($message);

        // Simulación de éxito
        return [
            'success' => true,
            'external_id' => 'wa_'.uniqid(),
        ];
    }

    protected function sendSMS(Message $message): array
    {
        $hablameSms = app(HablameSmsService::class);

        try {
            $result = $hablameSms->send($message);

            if ($result['success']) {
                Log::info('SMS enviado via Hablame', [
                    'message_id' => $message->id,
                    'batch_id' => $result['batch_id'] ?? null,
                    'cost' => $result['cost'] ?? null,
                ]);

                return [
                    'success' => true,
                    'external_id' => $result['batch_id'] ?? null,
                    'cost' => $result['cost'] ?? null,
                ];
            }

            Log::error('Error enviando SMS via Hablame', [
                'message_id' => $message->id,
                'error' => $result['error'] ?? 'Error desconocido',
            ]);

            return [
                'success' => false,
                'error' => $result['error'] ?? 'Error enviando SMS',
            ];
        } catch (\Exception $e) {
            Log::error('Excepción enviando SMS', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function sendEmail(Message $message): array
    {
        // Usar el sistema de email de Laravel
        Log::info('Enviando Email', [
            'to' => $message->voter->email,
            'subject' => $message->subject,
            'content' => $message->content,
        ]);

        // Simulación de éxito
        return [
            'success' => true,
            'external_id' => 'email_'.uniqid(),
        ];
    }
}

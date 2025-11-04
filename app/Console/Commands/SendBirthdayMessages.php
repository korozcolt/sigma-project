<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\MessageTemplate;
use App\Models\Voter;
use App\Services\MessageService;
use Illuminate\Console\Command;

class SendBirthdayMessages extends Command
{
    protected $signature = 'messages:send-birthdays {--campaign= : ID de la campaña específica}';

    protected $description = 'Envía mensajes de cumpleaños a los votantes que cumplen años hoy';

    public function handle(MessageService $messageService): int
    {
        $this->info('Iniciando envío de mensajes de cumpleaños...');

        $campaigns = $this->option('campaign')
            ? Campaign::where('id', $this->option('campaign'))->get()
            : Campaign::query()->active()->get();

        if ($campaigns->isEmpty()) {
            $this->warn('No hay campañas activas para procesar.');

            return self::SUCCESS;
        }

        $totalSent = 0;

        foreach ($campaigns as $campaign) {
            $this->info("Procesando campaña: {$campaign->name}");

            // Obtener plantilla de cumpleaños activa
            $template = MessageTemplate::forCampaign($campaign->id)
                ->byType('birthday')
                ->active()
                ->first();

            if (! $template) {
                $this->warn("  No hay plantilla de cumpleaños activa para la campaña {$campaign->name}");

                continue;
            }

            // Verificar horarios permitidos
            if (! $template->isWithinAllowedTime()) {
                $this->warn("  Fuera del horario permitido para la campaña {$campaign->name}");

                continue;
            }

            // Obtener votantes que cumplen años hoy
            $voters = Voter::where('campaign_id', $campaign->id)
                ->whereMonth('birth_date', today()->month)
                ->whereDay('birth_date', today()->day)
                ->confirmed()
                ->get();

            if ($voters->isEmpty()) {
                $this->info("  No hay votantes que cumplan años hoy en {$campaign->name}");

                continue;
            }

            $sent = 0;

            foreach ($voters as $voter) {
                // Verificar rate limiting
                if (! $template->canSendToVoter($voter)) {
                    $this->warn("  Rate limit alcanzado para votante {$voter->id}");

                    continue;
                }

                if (! $template->canSendInCampaign()) {
                    $this->warn('  Rate limit de campaña alcanzado');

                    break;
                }

                // Calcular edad
                $age = today()->diffInYears($voter->birth_date);

                // Crear mensaje desde plantilla
                $message = $messageService->createFromTemplate($template, $voter, [
                    'nombre' => $voter->first_name,
                    'edad' => $age,
                    'candidato' => $campaign->candidate_name,
                ]);

                // Enviar mensaje
                $messageService->schedule($message);

                $sent++;
            }

            $this->info("  ✓ Enviados {$sent} mensajes de cumpleaños en {$campaign->name}");
            $totalSent += $sent;
        }

        $this->info("\n✓ Proceso completado. Total de mensajes enviados: {$totalSent}");

        return self::SUCCESS;
    }
}

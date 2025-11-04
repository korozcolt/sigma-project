<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\MessageTemplate;
use App\Models\Voter;
use App\Services\MessageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendBirthdayMessages extends Command
{
    protected $signature = 'messages:send-birthdays
                            {--campaign= : ID de la campaña específica}
                            {--dry-run : Simular envío sin enviar mensajes reales}
                            {--force : Forzar envío ignorando horarios permitidos}';

    protected $description = 'Envía mensajes de cumpleaños a los votantes que cumplen años hoy';

    public function handle(MessageService $messageService): int
    {
        $isDryRun = $this->option('dry-run');
        $isForced = $this->option('force');

        if ($isDryRun) {
            $this->warn('⚠️  MODO DRY-RUN: No se enviarán mensajes reales');
        }

        $this->info('Iniciando envío de mensajes de cumpleaños...');
        Log::info('Iniciando envío de mensajes de cumpleaños', [
            'dry_run' => $isDryRun,
            'forced' => $isForced,
            'campaign_filter' => $this->option('campaign'),
        ]);

        $campaigns = $this->option('campaign')
            ? Campaign::where('id', $this->option('campaign'))->get()
            : Campaign::query()->active()->get();

        if ($campaigns->isEmpty()) {
            $this->warn('No hay campañas activas para procesar.');
            Log::warning('No hay campañas activas para procesar');

            return self::SUCCESS;
        }

        $totalSent = 0;
        $totalSkipped = 0;
        $totalErrors = 0;

        foreach ($campaigns as $campaign) {
            $this->newLine();
            $this->info("📋 Procesando campaña: {$campaign->name} (ID: {$campaign->id})");

            // Obtener plantilla de cumpleaños activa
            $template = MessageTemplate::forCampaign($campaign->id)
                ->byType('birthday')
                ->active()
                ->first();

            if (! $template) {
                $this->warn("  ⚠️  No hay plantilla de cumpleaños activa para la campaña {$campaign->name}");
                Log::warning('No hay plantilla de cumpleaños activa', ['campaign' => $campaign->name]);

                continue;
            }

            $this->info("  ✓ Plantilla encontrada: {$template->name}");

            // Verificar horarios permitidos
            if (! $template->isWithinAllowedTime() && ! $isForced) {
                $this->warn("  ⏰ Fuera del horario permitido para la campaña {$campaign->name}");
                Log::warning('Fuera del horario permitido', [
                    'campaign' => $campaign->name,
                    'current_time' => now()->format('H:i:s'),
                    'allowed_start' => $template->allowed_start_time,
                    'allowed_end' => $template->allowed_end_time,
                ]);

                continue;
            }

            if ($isForced) {
                $this->warn('  🚀 Horarios ignorados (modo forced)');
            }

            // Obtener votantes que cumplen años hoy
            $voters = Voter::where('campaign_id', $campaign->id)
                ->whereMonth('birth_date', today()->month)
                ->whereDay('birth_date', today()->day)
                ->confirmed()
                ->get();

            if ($voters->isEmpty()) {
                $this->info("  ℹ️  No hay votantes que cumplan años hoy en {$campaign->name}");

                continue;
            }

            $this->info("  🎂 {$voters->count()} votantes cumplen años hoy");

            $sent = 0;
            $skipped = 0;
            $errors = 0;

            $progressBar = $this->output->createProgressBar($voters->count());
            $progressBar->start();

            foreach ($voters as $voter) {
                // Verificar rate limiting
                if (! $template->canSendToVoter($voter)) {
                    $this->warn("     ⚠️  Rate limit alcanzado para votante {$voter->full_name}");
                    $skipped++;
                    $progressBar->advance();

                    continue;
                }

                if (! $template->canSendInCampaign()) {
                    $this->warn('     ⚠️  Rate limit de campaña alcanzado');
                    Log::warning('Rate limit de campaña alcanzado', ['campaign' => $campaign->name]);

                    break;
                }

                try {
                    // Calcular edad
                    $age = today()->diffInYears($voter->birth_date);

                    if ($isDryRun) {
                        // En dry-run solo mostramos lo que se haría
                        $this->line("     [DRY-RUN] Enviaría mensaje a: {$voter->full_name} ({$age} años)");
                        $sent++;
                    } else {
                        // Crear mensaje desde plantilla
                        $message = $messageService->createFromTemplate($template, $voter, [
                            'nombre' => $voter->first_name,
                            'edad' => $age,
                            'candidato' => $campaign->candidate_name,
                        ]);

                        // Enviar mensaje
                        $messageService->schedule($message);

                        Log::info('Mensaje de cumpleaños enviado', [
                            'voter_id' => $voter->id,
                            'voter_name' => $voter->full_name,
                            'age' => $age,
                            'campaign' => $campaign->name,
                            'message_id' => $message->id,
                        ]);

                        $sent++;
                    }
                } catch (\Exception $e) {
                    $errors++;
                    $this->error("     ❌ Error al enviar mensaje a {$voter->full_name}: {$e->getMessage()}");
                    Log::error('Error al enviar mensaje de cumpleaños', [
                        'voter_id' => $voter->id,
                        'voter_name' => $voter->full_name,
                        'error' => $e->getMessage(),
                        'campaign' => $campaign->name,
                    ]);
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine();

            $this->info("  ✓ Enviados: {$sent} | Omitidos: {$skipped} | Errores: {$errors}");

            $totalSent += $sent;
            $totalSkipped += $skipped;
            $totalErrors += $errors;
        }

        $this->newLine();
        $this->info('═══════════════════════════════════════');
        $this->info('✅ Proceso completado');
        $this->info("📨 Total enviados: {$totalSent}");
        $this->info("⏭️  Total omitidos: {$totalSkipped}");
        $this->info("❌ Total errores: {$totalErrors}");
        $this->info('═══════════════════════════════════════');

        Log::info('Proceso de cumpleaños completado', [
            'total_sent' => $totalSent,
            'total_skipped' => $totalSkipped,
            'total_errors' => $totalErrors,
            'dry_run' => $isDryRun,
        ]);

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\Message;
use App\Models\Voter;
use App\Services\HablameSmsService;
use Illuminate\Console\Command;

class TestHablameSms extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:hablame-sms
                            {phone : Número de teléfono destino (ej: 3001234567 o +573001234567)}
                            {--message= : Mensaje personalizado (opcional)}
                            {--check-account : Verificar información de la cuenta}
                            {--validate-key : Validar API key}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enviar SMS de prueba via Hablame y verificar integración';

    /**
     * Execute the console command.
     */
    public function handle(HablameSmsService $hablameSms): int
    {
        $this->info('🚀 Prueba de Integración Hablame SMS API v5');
        $this->newLine();

        // Verificar configuración
        if (! config('services.hablame.api_key')) {
            $this->error('❌ HABLAME_API_KEY no está configurada en .env');
            $this->warn('💡 Agrega HABLAME_API_KEY=tu_api_key en el archivo .env');

            return self::FAILURE;
        }

        $this->info('✅ API Key configurada');
        $this->newLine();

        // Opción: Validar API key
        if ($this->option('validate-key')) {
            $this->info('🔑 Validando API Key...');

            if ($hablameSms->validateApiKey()) {
                $this->info('✅ API Key válida');
            } else {
                $this->error('❌ API Key inválida');

                return self::FAILURE;
            }

            $this->newLine();
        }

        // Opción: Verificar información de cuenta
        if ($this->option('check-account')) {
            $this->info('📊 Obteniendo información de cuenta...');
            $accountInfo = $hablameSms->getAccountInfo();

            if ($accountInfo['success']) {
                $this->table(
                    ['Campo', 'Valor'],
                    [
                        ['Account ID', $accountInfo['account_id'] ?? 'N/A'],
                        ['Estado', $accountInfo['status'] ?? 'N/A'],
                        ['Balance', '$'.($accountInfo['balance'] ?? '0')],
                        ['Tipo de facturación', $accountInfo['billing_type'] ?? 'N/A'],
                    ]
                );
            } else {
                $this->error('❌ Error obteniendo información: '.$accountInfo['error']);

                return self::FAILURE;
            }

            $this->newLine();
        }

        // Enviar SMS de prueba
        $phone = $this->argument('phone');
        $customMessage = $this->option('message');

        $this->info("📱 Preparando envío de SMS a: {$phone}");

        // Crear datos de prueba (campaign y voter temporales)
        $campaign = Campaign::first();

        if (! $campaign) {
            $this->warn('⚠️  No hay campañas en la base de datos. Creando campaña de prueba...');
            $campaign = Campaign::factory()->create([
                'name' => 'Campaña de Prueba SMS',
                'candidate_name' => 'Sistema SIGMA',
                'status' => \App\Enums\CampaignStatus::ACTIVE,
            ]);
            $this->info("✅ Campaña creada: {$campaign->name}");
        }

        // Buscar o crear voter con el número de prueba
        $voter = Voter::where('phone', $phone)->first();

        if (! $voter) {
            $this->warn('⚠️  Creando apoyo de prueba...');
            $voter = Voter::factory()->for($campaign)->create([
                'phone' => $phone,
                'first_name' => 'Usuario',
                'last_name' => 'Prueba',
                'document_number' => '9999999'.rand(10, 99),
            ]);
            $this->info("✅ Apoyo creado: {$voter->full_name}");
        }

        // Crear mensaje de prueba
        $messageContent = $customMessage ?? "🧪 Mensaje de prueba desde SIGMA.\n\n".
            "Esto es una prueba de integración con Hablame SMS API v5.\n\n".
            'Fecha: '.now()->format('d/m/Y H:i:s')."\n".
            "¡La integración funciona correctamente! ✅\n\n".
            'SIGMA - Sistema de Gestión Electoral';

        $this->info('📝 Contenido del mensaje:');
        $this->line($messageContent);
        $this->newLine();

        $message = Message::create([
            'campaign_id' => $campaign->id,
            'voter_id' => $voter->id,
            'type' => 'custom',
            'channel' => 'sms',
            'content' => $messageContent,
            'status' => 'pending',
        ]);

        if (! $this->confirm('¿Deseas enviar este SMS?', true)) {
            $this->warn('❌ Envío cancelado');
            $message->delete();

            return self::SUCCESS;
        }

        // Verificar si está en modo sandbox
        if (config('services.hablame.sandbox_mode')) {
            $this->warn('⚠️  Modo SANDBOX activado - No se consumirá saldo real');
        }

        $this->info('📤 Enviando SMS...');

        try {
            $result = $hablameSms->send($message);

            if ($result['success']) {
                $this->newLine();
                $this->info('✅ ¡SMS enviado exitosamente!');
                $this->newLine();

                $this->table(
                    ['Campo', 'Valor'],
                    [
                        ['Batch ID', $result['batch_id'] ?? 'N/A'],
                        ['Mensajes enviados', $result['sent'] ?? 0],
                        ['Mensajes fallidos', $result['failed'] ?? 0],
                        ['Costo', '$'.($result['cost'] ?? '0')],
                        ['Código estado', $result['status_code'] ?? 'N/A'],
                        ['Mensaje estado', $result['status_message'] ?? 'N/A'],
                        ['Tiempo respuesta', ($result['response_time'] ?? 'N/A').'ms'],
                    ]
                );

                // Actualizar mensaje en BD
                $message->markAsSent($result['batch_id'] ?? null);

                $this->newLine();
                $this->info('💾 Mensaje guardado en la base de datos con ID: '.$message->id);

                return self::SUCCESS;
            } else {
                $this->error('❌ Error al enviar SMS: '.$result['error']);
                $message->markAsFailed($result['error']);

                return self::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('❌ Excepción: '.$e->getMessage());
            $message->markAsFailed($e->getMessage());

            return self::FAILURE;
        }
    }
}

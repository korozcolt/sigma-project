<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Services\BirthdayWebhookService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DispatchBirthdayWebhooks extends Command
{
    protected $signature = 'birthday:dispatch-webhooks
                            {--campaign= : ID de la campaña específica}
                            {--force : Omitir verificación de hora}';

    protected $description = 'Despacha webhooks de cumpleaños a las campañas configuradas';

    public function __construct(public BirthdayWebhookService $webhookService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $colombia = now()->timezone('America/Bogota');

        $campaignQuery = $this->option('campaign')
            ? Campaign::where('id', $this->option('campaign'))
            : Campaign::query()->active();

        $campaigns = $campaignQuery->get()->filter(
            fn (Campaign $campaign) => ! empty($campaign->settings['birthday_webhook_enabled'])
                && ! empty($campaign->settings['birthday_webhook_url'])
        );

        foreach ($campaigns as $campaign) {
            $configuredTime = $campaign->settings['birthday_webhook_time'] ?? '08:00';

            if (! $this->option('force') && $colombia->format('H:i') !== $configuredTime) {
                Log::info('Skipping campaign, time not matched', [
                    'campaign_id' => $campaign->id,
                    'configured_time' => $configuredTime,
                    'current_time' => $colombia->format('H:i'),
                ]);

                continue;
            }

            try {
                $this->webhookService->dispatch($campaign, $colombia);
                Log::info('Birthday webhook dispatched successfully', ['campaign_id' => $campaign->id]);
            } catch (\Throwable $e) {
                Log::warning('Webhook failed for campaign', [
                    'campaign_id' => $campaign->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return self::SUCCESS;
    }
}

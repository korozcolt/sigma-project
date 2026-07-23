<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Campaign;
use App\Models\OtpVerification;

class OtpVerificationService
{
    private const DEFAULT_TEMPLATE = 'Tu código de verificación SIGMA es {codigo}. Vence en 10 minutos.';

    private const MAX_ATTEMPTS = 5;

    public function __construct(private HablameSmsService $sms) {}

    public function generate(string $phone, Campaign $campaign): OtpVerification
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $otp = OtpVerification::create([
            'phone' => $phone,
            'campaign_id' => $campaign->id,
            'code' => $code,
            'expires_at' => now()->addMinutes(10),
        ]);

        $template = $campaign->settings['otp_message_template'] ?? self::DEFAULT_TEMPLATE;
        $message = str_replace('{codigo}', $code, $template);

        $this->sms->sendRaw($phone, $message, priority: true);

        return $otp;
    }

    public function verify(string $phone, Campaign $campaign, string $code): bool
    {
        $otp = OtpVerification::query()
            ->where('phone', $phone)
            ->where('campaign_id', $campaign->id)
            ->whereNull('verified_at')
            ->latest()
            ->first();

        if (! $otp || $otp->expires_at->isPast() || $otp->attempts >= self::MAX_ATTEMPTS) {
            return false;
        }

        if (! hash_equals($otp->code, $code)) {
            $otp->increment('attempts');

            return false;
        }

        $otp->update(['verified_at' => now()]);

        return true;
    }
}

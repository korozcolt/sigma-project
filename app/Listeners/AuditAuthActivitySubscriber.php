<?php

namespace App\Listeners;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\CampaignContext;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuditAuthActivitySubscriber
{
    public function handleLogin(Login $event): void
    {
        $this->record('login', $event->user instanceof User ? $event->user : null);
    }

    public function handleLogout(Logout $event): void
    {
        $this->record('logout', $event->user instanceof User ? $event->user : null);
    }

    public function handleFailed(Failed $event): void
    {
        $this->record('login_failed', null, [
            'attempted_login' => $event->credentials['email'] ?? null,
        ]);
    }

    private function record(string $action, ?User $user, array $extra = []): void
    {
        try {
            AuditLog::create([
                'auditable_type' => $user ? User::class : null,
                'auditable_id' => $user?->getKey(),
                'action' => $action,
                'user_id' => $user?->getKey(),
                'campaign_id' => $user ? CampaignContext::currentCampaignId($user) : null,
                'old_values' => null,
                'new_values' => $extra ?: null,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (Throwable $e) {
            Log::error('AuditAuthActivitySubscriber failed to write audit log', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'handleLogin',
            Logout::class => 'handleLogout',
            Failed::class => 'handleFailed',
        ];
    }
}

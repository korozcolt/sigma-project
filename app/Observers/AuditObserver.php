<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\Campaign;
use App\Services\CampaignContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuditObserver
{
    public function created(Model $model): void
    {
        $this->record($model, 'created', null, $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        unset($changes['updated_at']);

        if (empty($changes)) {
            return;
        }

        $old = [];
        foreach (array_keys($changes) as $key) {
            $old[$key] = $model->getOriginal($key);
        }

        $this->record($model, 'updated', $old, $changes);
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted', $model->getAttributes(), null);
    }

    private function record(Model $model, string $action, ?array $old, ?array $new): void
    {
        try {
            AuditLog::create([
                'auditable_type' => $model->getMorphClass(),
                'auditable_id' => $model->getKey(),
                'action' => $action,
                'user_id' => Auth::id(),
                'campaign_id' => $this->resolveCampaignId($model),
                'old_values' => $old,
                'new_values' => $new,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (Throwable $e) {
            // Audit failures must never break the primary write — log and continue.
            Log::error('AuditObserver failed to write audit log', [
                'model' => $model::class,
                'model_id' => $model->getKey(),
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveCampaignId(Model $model): ?int
    {
        if ($model instanceof Campaign) {
            return $model->getKey();
        }

        if (array_key_exists('campaign_id', $model->getAttributes())) {
            return $model->getAttribute('campaign_id');
        }

        return CampaignContext::currentCampaignId();
    }
}

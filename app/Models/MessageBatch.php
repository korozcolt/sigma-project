<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessageBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'template_id',
        'name',
        'type',
        'channel',
        'status',
        'total_recipients',
        'sent_count',
        'failed_count',
        'delivered_count',
        'scheduled_for',
        'started_at',
        'completed_at',
        'filters',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'scheduled_for' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'filters' => 'array',
        'metadata' => 'array',
    ];

    // Relaciones
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'batch_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeDueToProcess($query)
    {
        return $query->where('status', 'pending')
            ->where('scheduled_for', '<=', now());
    }

    public function scopeForCampaign($query, int $campaignId)
    {
        return $query->where('campaign_id', $campaignId);
    }

    // Métodos de ayuda
    public function markAsProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
        ]);
    }

    public function incrementSent(): void
    {
        $this->increment('sent_count');
    }

    public function incrementFailed(): void
    {
        $this->increment('failed_count');
    }

    public function incrementDelivered(): void
    {
        $this->increment('delivered_count');
    }

    // Métricas
    public function getProgressPercentage(): float
    {
        if ($this->total_recipients === 0) {
            return 0;
        }

        $processed = $this->sent_count + $this->failed_count;

        return round(($processed / $this->total_recipients) * 100, 2);
    }

    public function getSuccessRate(): float
    {
        $processed = $this->sent_count + $this->failed_count;

        if ($processed === 0) {
            return 0;
        }

        return round(($this->sent_count / $processed) * 100, 2);
    }

    public function getDeliveryRate(): float
    {
        if ($this->sent_count === 0) {
            return 0;
        }

        return round(($this->delivered_count / $this->sent_count) * 100, 2);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}

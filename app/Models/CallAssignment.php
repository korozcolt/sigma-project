<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CallAssignment extends Model
{
    /** @use HasFactory<\Database\Factories\CallAssignmentFactory> */
    use HasFactory;

    protected $fillable = [
        'voter_id',
        'assigned_to',
        'assigned_by',
        'campaign_id',
        'status',
        'priority',
        'assigned_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    // Relationships

    public function voter(): BelongsTo
    {
        return $this->belongsTo(Voter::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function verificationCalls(): HasMany
    {
        return $this->hasMany(VerificationCall::class, 'assignment_id');
    }

    // Scopes

    public function scopePending(Builder $query): void
    {
        $query->where('status', 'pending');
    }

    public function scopeInProgress(Builder $query): void
    {
        $query->where('status', 'in_progress');
    }

    public function scopeCompleted(Builder $query): void
    {
        $query->where('status', 'completed');
    }

    public function scopeForCampaign(Builder $query, int $campaignId): void
    {
        $query->where('campaign_id', $campaignId);
    }

    public function scopeForCaller(Builder $query, int $callerId): void
    {
        $query->where('assigned_to', $callerId);
    }

    public function scopeByPriority(Builder $query, string $priority): void
    {
        $query->where('priority', $priority);
    }

    public function scopeHighPriority(Builder $query): void
    {
        $query->whereIn('priority', ['high', 'urgent']);
    }

    public function scopeOrderedByPriority(Builder $query): void
    {
        $driver = $query->getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $query->orderByRaw("FIELD(priority, 'urgent', 'high', 'medium', 'low')");
        } else {
            // SQLite and other databases: use CASE statement
            $query->orderByRaw("CASE priority
                WHEN 'urgent' THEN 1
                WHEN 'high' THEN 2
                WHEN 'medium' THEN 3
                WHEN 'low' THEN 4
                ELSE 5
            END");
        }
    }

    // Helper Methods

    public function markInProgress(): void
    {
        $this->update(['status' => 'in_progress']);
    }

    public function markCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function reassign(int $newCallerId): void
    {
        $this->update([
            'assigned_to' => $newCallerId,
            'status' => 'reassigned',
        ]);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isUrgent(): bool
    {
        return $this->priority === 'urgent';
    }

    public function isHighPriority(): bool
    {
        return in_array($this->priority, ['high', 'urgent']);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Invitation extends Model
{
    /** @use HasFactory<\Database\Factories\InvitationFactory> */
    use HasFactory;

    protected $fillable = [
        'token',
        'invited_by_user_id',
        'invited_email',
        'invited_name',
        'target_role',
        'campaign_id',
        'municipality_id',
        'parent_leader_id',
        'leader_user_id',
        'coordinator_user_id',
        'status',
        'expires_at',
        'notes',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public static function boot(): void
    {
        parent::boot();

        static::creating(function ($invitation) {
            if (empty($invitation->token)) {
                $invitation->token = Str::random(60);
            }

            if (empty($invitation->invited_by_user_id) && auth()->check()) {
                $invitation->invited_by_user_id = auth()->id();
            }

            if (empty($invitation->status)) {
                $invitation->status = 'pending';
            }

            if (empty($invitation->target_role)) {
                $invitation->target_role = $invitation->leader_user_id ? 'LEADER' : 'COORDINATOR';
            }

            if (empty($invitation->invited_email)) {
                $invitation->invited_email = "registro+{$invitation->token}@sigma.local";
            }
        });

        static::saving(function ($invitation) {
            if ($invitation->leader_user_id) {
                $invitation->target_role = 'LEADER';
                $invitation->coordinator_user_id ??= \App\Models\User::query()
                    ->whereKey($invitation->leader_user_id)
                    ->value('coordinator_user_id');
            }
        });
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_user_id');
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinator_user_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return $this->status === 'pending' && !$this->isExpired();
    }

    public function getRegistrationUrl(): string
    {
        return route('public.voters.register', ['token' => $this->token]);
    }
}

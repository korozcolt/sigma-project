<?php

namespace App\Models;

use App\Enums\VoterStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ValidationHistory extends Model
{
    /** @use HasFactory<\Database\Factories\ValidationHistoryFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'voter_id',
        'previous_status',
        'new_status',
        'validated_by',
        'validation_type',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'previous_status' => VoterStatus::class,
            'new_status' => VoterStatus::class,
        ];
    }

    /**
     * Get the voter this history belongs to.
     */
    public function voter(): BelongsTo
    {
        return $this->belongsTo(Voter::class);
    }

    /**
     * Get the user who validated this change.
     */
    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    /**
     * Scope a query to only include histories for a specific voter.
     */
    public function scopeForVoter(Builder $query, int $voterId): void
    {
        $query->where('voter_id', $voterId);
    }

    /**
     * Scope a query to filter by validation type.
     */
    public function scopeByType(Builder $query, string $type): void
    {
        $query->where('validation_type', $type);
    }

    /**
     * Scope a query to order by most recent first.
     */
    public function scopeRecent(Builder $query): void
    {
        $query->orderBy('created_at', 'desc');
    }
}

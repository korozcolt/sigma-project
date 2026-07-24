<?php

namespace App\Models;

use App\Enums\PollingPlaceSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PollingPlaceResolution extends Model
{
    /** @use HasFactory<\Database\Factories\PollingPlaceResolutionFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'voter_id',
        'previous_source',
        'new_source',
        'polling_place_id',
        'table_number',
        'resolved_by',
        'resolved_via',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'previous_source' => PollingPlaceSource::class,
            'new_source' => PollingPlaceSource::class,
        ];
    }

    /**
     * Get the voter this resolution belongs to.
     */
    public function voter(): BelongsTo
    {
        return $this->belongsTo(Voter::class);
    }

    /**
     * Get the polling place snapshot at the time of this resolution.
     */
    public function pollingPlace(): BelongsTo
    {
        return $this->belongsTo(PollingPlace::class);
    }

    /**
     * Get the user who resolved this change (nullable — headless actor per D-05).
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Scope a query to only include resolutions for a specific voter.
     */
    public function scopeForVoter(Builder $query, int $voterId): void
    {
        $query->where('voter_id', $voterId);
    }

    /**
     * Scope a query to filter by resolved_via.
     */
    public function scopeByVia(Builder $query, string $via): void
    {
        $query->where('resolved_via', $via);
    }

    /**
     * Scope a query to order by most recent first.
     */
    public function scopeRecent(Builder $query): void
    {
        $query->orderBy('created_at', 'desc');
    }
}

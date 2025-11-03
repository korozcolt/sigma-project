<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Neighborhood extends Model
{
    /** @use HasFactory<\Database\Factories\NeighborhoodFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'municipality_id',
        'campaign_id',
        'name',
        'is_global',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'is_global' => 'boolean',
        ];
    }

    /**
     * Get the municipality that owns the neighborhood.
     */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /**
     * Get the campaign that owns the neighborhood (if any).
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Scope a query to only include global neighborhoods.
     */
    public function scopeGlobal(Builder $query): void
    {
        $query->where('is_global', true)->whereNull('campaign_id');
    }

    /**
     * Scope a query to only include campaign-specific neighborhoods.
     */
    public function scopeForCampaign(Builder $query, int $campaignId): void
    {
        $query->where('campaign_id', $campaignId);
    }

    /**
     * Scope a query to include both global and campaign-specific neighborhoods.
     */
    public function scopeAvailableForCampaign(Builder $query, int $campaignId): void
    {
        $query->where(function ($q) use ($campaignId) {
            $q->where('is_global', true)
                ->orWhere('campaign_id', $campaignId);
        });
    }
}

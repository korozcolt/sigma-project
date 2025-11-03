<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CensusRecord extends Model
{
    /** @use HasFactory<\Database\Factories\CensusRecordFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'campaign_id',
        'document_number',
        'full_name',
        'municipality_code',
        'polling_station',
        'table_number',
        'imported_at',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'imported_at' => 'datetime',
        ];
    }

    /**
     * Get the campaign this census record belongs to.
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Scope a query to only include records for a specific campaign.
     */
    public function scopeForCampaign(Builder $query, int $campaignId): void
    {
        $query->where('campaign_id', $campaignId);
    }

    /**
     * Scope a query to search by document number.
     */
    public function scopeByDocument(Builder $query, string $documentNumber): void
    {
        $query->where('document_number', $documentNumber);
    }

    /**
     * Scope a query to filter by municipality code.
     */
    public function scopeByMunicipality(Builder $query, string $municipalityCode): void
    {
        $query->where('municipality_code', $municipalityCode);
    }
}

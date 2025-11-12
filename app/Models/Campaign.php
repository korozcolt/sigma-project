<?php

namespace App\Models;

use App\Enums\CampaignScope;
use App\Enums\CampaignStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends Model
{
    /** @use HasFactory<\Database\Factories\CampaignFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'logo_path',
        'description',
        'candidate_name',
        'start_date',
        'end_date',
        'election_date',
        'status',
        'scope',
        'settings',
        'created_by',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'election_date' => 'date',
            'status' => CampaignStatus::class,
            'scope' => CampaignScope::class,
            'settings' => 'array',
        ];
    }

    /**
     * Get the user who created the campaign.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the neighborhoods associated with this campaign.
     */
    public function neighborhoods(): HasMany
    {
        return $this->hasMany(Neighborhood::class);
    }

    /**
     * Get the users (team members) associated with this campaign.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'campaign_user')
            ->withPivot('role_id', 'assigned_at', 'assigned_by')
            ->withTimestamps();
    }

    /**
     * Get the voters registered for this campaign
     */
    public function voters(): HasMany
    {
        return $this->hasMany(Voter::class);
    }

    /**
     * Get the census records for this campaign
     */
    public function censusRecords(): HasMany
    {
        return $this->hasMany(CensusRecord::class);
    }

    /**
     * Get the surveys for this campaign
     */
    public function surveys(): HasMany
    {
        return $this->hasMany(Survey::class);
    }

    /**
     * Scope a query to only include active campaigns.
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', CampaignStatus::ACTIVE);
    }

    /**
     * Scope a query to only include draft campaigns.
     */
    public function scopeDraft(Builder $query): void
    {
        $query->where('status', CampaignStatus::DRAFT);
    }

    /**
     * Scope a query to only include completed campaigns.
     */
    public function scopeCompleted(Builder $query): void
    {
        $query->where('status', CampaignStatus::COMPLETED);
    }

    /**
     * Scope a query to only include departamental campaigns.
     */
    public function scopeDepartamental(Builder $query): void
    {
        $query->where('scope', CampaignScope::Departamental);
    }

    /**
     * Scope a query to only include municipal campaigns.
     */
    public function scopeMunicipal(Builder $query): void
    {
        $query->where('scope', CampaignScope::Municipal);
    }

    /**
     * Scope a query to only include regional campaigns.
     */
    public function scopeRegional(Builder $query): void
    {
        $query->where('scope', CampaignScope::Regional);
    }

    /**
     * Get the URL for the campaign logo
     */
    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? \Storage::url($this->logo_path) : null;
    }

    /**
     * Check if the campaign has a logo
     */
    public function hasLogo(): bool
    {
        return ! empty($this->logo_path);
    }
}

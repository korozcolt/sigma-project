<?php

namespace App\Models;

use App\Enums\VoterStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Voter extends Model
{
    /** @use HasFactory<\Database\Factories\VoterFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'campaign_id',
        'document_number',
        'first_name',
        'last_name',
        'birth_date',
        'phone',
        'secondary_phone',
        'email',
        'municipality_id',
        'neighborhood_id',
        'address',
        'detailed_address',
        'registered_by',
        'status',
        'census_validated_at',
        'call_verified_at',
        'confirmed_at',
        'voted_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'status' => VoterStatus::class,
            'census_validated_at' => 'datetime',
            'call_verified_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'voted_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function neighborhood(): BelongsTo
    {
        return $this->belongsTo(Neighborhood::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function validationHistories(): HasMany
    {
        return $this->hasMany(ValidationHistory::class);
    }

    public function surveyResponses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function scopePendingReview(Builder $query): void
    {
        $query->where('status', VoterStatus::PENDING_REVIEW);
    }

    public function scopeVerifiedCensus(Builder $query): void
    {
        $query->where('status', VoterStatus::VERIFIED_CENSUS);
    }

    public function scopeConfirmed(Builder $query): void
    {
        $query->where('status', VoterStatus::CONFIRMED);
    }

    public function scopeVoted(Builder $query): void
    {
        $query->where('status', VoterStatus::VOTED);
    }

    public function scopeDidNotVote(Builder $query): void
    {
        $query->where('status', VoterStatus::DID_NOT_VOTE);
    }

    public function scopeForCampaign(Builder $query, int $campaignId): void
    {
        $query->where('campaign_id', $campaignId);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function isSystemUser(): bool
    {
        return $this->user()->exists();
    }
}

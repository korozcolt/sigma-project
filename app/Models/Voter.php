<?php

namespace App\Models;

use App\Enums\PollingPlaceSource;
use App\Enums\VoterStatus;
use App\Models\Concerns\HasCampaignContext;
use App\Services\VoterDuplicateAssignmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Voter extends Model
{
    /** @use HasFactory<\Database\Factories\VoterFactory> */
    use HasCampaignContext, HasFactory, SoftDeletes;

    protected $attributes = [
        'reconciliation_attempts' => 0,
    ];

    protected $fillable = [
        'campaign_id',
        'user_id',
        'document_number',
        'duplicate_sequence',
        'first_name',
        'last_name',
        'birth_date',
        'phone',
        'secondary_phone',
        'email',
        'municipality_id',
        'neighborhood_id',
        'polling_place_id',
        'polling_table_number',
        'polling_place_source',
        'polling_place_resolved_at',
        'reconciliation_attempts',
        'reconciliation_exhausted_at',
        'address',
        'detailed_address',
        'registered_by',
        'status',
        'census_validated_at',
        'call_verified_at',
        'confirmed_at',
        'voted_at',
        'notes',
        'gremio_id',
        'subcategoria_id',
        'lugar_expedicion_cedula',
        'placa',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'status' => VoterStatus::class,
            'polling_place_source' => PollingPlaceSource::class,
            'polling_place_resolved_at' => 'datetime',
            'reconciliation_attempts' => 'integer',
            'reconciliation_exhausted_at' => 'datetime',
            'census_validated_at' => 'datetime',
            'call_verified_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'voted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Voter $voter): void {
            if ($voter->duplicate_sequence !== null) {
                return;
            }

            if (blank($voter->document_number)) {
                return;
            }

            $sequence = app(VoterDuplicateAssignmentService::class)->nextSequenceFor($voter->document_number);

            $voter->duplicate_sequence = $sequence;

            if ($sequence > 0 && ($voter->status === null || $voter->status === VoterStatus::PENDING_REVIEW)) {
                $voter->status = VoterStatus::DUPLICATE;
            }
        });
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

    public function pollingPlace(): BelongsTo
    {
        return $this->belongsTo(PollingPlace::class);
    }

    public function gremio(): BelongsTo
    {
        return $this->belongsTo(Gremio::class);
    }

    public function subcategoria(): BelongsTo
    {
        return $this->belongsTo(Subcategoria::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function validationHistories(): HasMany
    {
        return $this->hasMany(ValidationHistory::class);
    }

    public function pollingPlaceResolutions(): HasMany
    {
        return $this->hasMany(PollingPlaceResolution::class);
    }

    public function surveyResponses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    public function verificationCalls(): HasMany
    {
        return $this->hasMany(VerificationCall::class);
    }

    public function callAssignments(): HasMany
    {
        return $this->hasMany(CallAssignment::class);
    }

    public function voteRecords(): HasMany
    {
        return $this->hasMany(VoteRecord::class);
    }

    /**
     * Get the User associated with this voter (si es coordinador o líder)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePendingReview(Builder $query): void
    {
        $query->where('status', VoterStatus::PENDING_REVIEW->value);
    }

    public function scopeVerifiedCensus(Builder $query): void
    {
        $query->where('status', VoterStatus::VERIFIED_CENSUS->value);
    }

    public function scopeConfirmed(Builder $query): void
    {
        $query->where('status', VoterStatus::CONFIRMED->value);
    }

    public function scopeVoted(Builder $query): void
    {
        $query->where('status', VoterStatus::VOTED->value);
    }

    public function scopeDidNotVote(Builder $query): void
    {
        $query->where('status', VoterStatus::DID_NOT_VOTE->value);
    }

    public function scopeForCampaign(Builder $query, int $campaignId): void
    {
        $query->where('campaign_id', $campaignId);
    }

    public function scopeDuplicates(Builder $query): void
    {
        $query->where('status', VoterStatus::DUPLICATE->value);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getDisplaySuffixAttribute(): string
    {
        return "{$this->document_number}-{$this->duplicate_sequence}";
    }

    public function isSystemUser(): bool
    {
        return $this->user()->exists();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Survey extends Model
{
    /** @use HasFactory<\Database\Factories\SurveyFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'campaign_id',
        'title',
        'description',
        'is_active',
        'version',
        'parent_survey_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function parentSurvey(): BelongsTo
    {
        return $this->belongsTo(Survey::class, 'parent_survey_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(Survey::class, 'parent_survey_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(SurveyQuestion::class)->orderBy('order');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(SurveyMetrics::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeForCampaign(Builder $query, int $campaignId): void
    {
        $query->where('campaign_id', $campaignId);
    }

    public function scopeLatestVersion(Builder $query): void
    {
        $query->whereNull('parent_survey_id')
            ->orWhereIn('id', function ($subquery) {
                $subquery->selectRaw('MAX(id)')
                    ->from('surveys')
                    ->whereNotNull('parent_survey_id')
                    ->groupBy('parent_survey_id');
            });
    }
}

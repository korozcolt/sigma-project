<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyMetrics extends Model
{
    /** @use HasFactory<\Database\Factories\SurveyMetricsFactory> */
    use HasFactory;

    protected $fillable = [
        'survey_id',
        'survey_question_id',
        'metric_type',
        'total_responses',
        'response_rate',
        'average_value',
        'distribution',
        'metadata',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'total_responses' => 'integer',
            'response_rate' => 'decimal:2',
            'average_value' => 'decimal:2',
            'distribution' => 'array',
            'metadata' => 'array',
            'calculated_at' => 'datetime',
        ];
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(SurveyQuestion::class, 'survey_question_id');
    }

    public function scopeForSurvey(Builder $query, int $surveyId): void
    {
        $query->where('survey_id', $surveyId);
    }

    public function scopeForQuestion(Builder $query, int $questionId): void
    {
        $query->where('survey_question_id', $questionId);
    }

    public function scopeByType(Builder $query, string $type): void
    {
        $query->where('metric_type', $type);
    }

    public function scopeOverall(Builder $query): void
    {
        $query->where('metric_type', 'overall');
    }

    public function scopeRecent(Builder $query): void
    {
        $query->orderBy('calculated_at', 'desc');
    }
}

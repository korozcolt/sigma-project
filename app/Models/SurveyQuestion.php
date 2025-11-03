<?php

namespace App\Models;

use App\Enums\QuestionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SurveyQuestion extends Model
{
    /** @use HasFactory<\Database\Factories\SurveyQuestionFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'survey_id',
        'question_text',
        'question_type',
        'order',
        'is_required',
        'configuration',
    ];

    protected function casts(): array
    {
        return [
            'question_type' => QuestionType::class,
            'is_required' => 'boolean',
            'configuration' => 'array',
            'order' => 'integer',
        ];
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    public function scopeRequired(Builder $query): void
    {
        $query->where('is_required', true);
    }

    public function scopeOptional(Builder $query): void
    {
        $query->where('is_required', false);
    }

    public function scopeByType(Builder $query, QuestionType $type): void
    {
        $query->where('question_type', $type);
    }
}

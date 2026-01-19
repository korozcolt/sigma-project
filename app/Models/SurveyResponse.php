<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyResponse extends Model
{
    /** @use HasFactory<\Database\Factories\SurveyResponseFactory> */
    use HasFactory;

    protected $fillable = [
        'survey_id',
        'survey_question_id',
        'voter_id',
        'answered_by',
        'verification_call_id',
        'response_value',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
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

    public function voter(): BelongsTo
    {
        return $this->belongsTo(Voter::class);
    }

    public function answerer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }

    public function verificationCall(): BelongsTo
    {
        return $this->belongsTo(VerificationCall::class, 'verification_call_id');
    }

    public function scopeForSurvey(Builder $query, int $surveyId): void
    {
        $query->where('survey_id', $surveyId);
    }

    public function scopeForVoter(Builder $query, int $voterId): void
    {
        $query->where('voter_id', $voterId);
    }

    public function scopeForQuestion(Builder $query, int $questionId): void
    {
        $query->where('survey_question_id', $questionId);
    }
}

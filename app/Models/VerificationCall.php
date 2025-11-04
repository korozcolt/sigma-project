<?php

namespace App\Models;

use App\Enums\CallResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationCall extends Model
{
    /** @use HasFactory<\Database\Factories\VerificationCallFactory> */
    use HasFactory;

    protected $fillable = [
        'voter_id',
        'assignment_id',
        'caller_id',
        'attempt_number',
        'call_date',
        'call_duration',
        'call_result',
        'notes',
        'survey_id',
        'survey_completed',
        'next_attempt_at',
    ];

    protected function casts(): array
    {
        return [
            'call_date' => 'datetime',
            'next_attempt_at' => 'datetime',
            'call_result' => CallResult::class,
            'survey_completed' => 'boolean',
            'call_duration' => 'integer',
            'attempt_number' => 'integer',
        ];
    }

    // Relationships

    public function voter(): BelongsTo
    {
        return $this->belongsTo(Voter::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(CallAssignment::class, 'assignment_id');
    }

    public function caller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caller_id');
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    // Scopes

    public function scopeForVoter(Builder $query, int $voterId): void
    {
        $query->where('voter_id', $voterId);
    }

    public function scopeForCaller(Builder $query, int $callerId): void
    {
        $query->where('caller_id', $callerId);
    }

    public function scopeByResult(Builder $query, CallResult $result): void
    {
        $query->where('call_result', $result);
    }

    public function scopeSuccessful(Builder $query): void
    {
        $query->whereIn('call_result', [
            CallResult::ANSWERED->value,
            CallResult::CONFIRMED->value,
            CallResult::CALLBACK_REQUESTED->value,
        ]);
    }

    public function scopeRequiresFollowUp(Builder $query): void
    {
        $query->whereIn('call_result', [
            CallResult::NO_ANSWER->value,
            CallResult::BUSY->value,
            CallResult::CALLBACK_REQUESTED->value,
        ]);
    }

    public function scopeInvalidNumber(Builder $query): void
    {
        $query->whereIn('call_result', [
            CallResult::WRONG_NUMBER->value,
            CallResult::INVALID_NUMBER->value,
        ]);
    }

    public function scopeRecent(Builder $query, int $days = 7): void
    {
        $query->where('call_date', '>=', now()->subDays($days));
    }

    public function scopeWithSurvey(Builder $query): void
    {
        $query->whereNotNull('survey_id');
    }

    public function scopeSurveyCompleted(Builder $query): void
    {
        $query->where('survey_completed', true);
    }

    // Helper Methods

    public function isSuccessful(): bool
    {
        return $this->call_result->isSuccessfulContact();
    }

    public function requiresFollowUp(): bool
    {
        return $this->call_result->requiresFollowUp();
    }

    public function isInvalidNumber(): bool
    {
        return $this->call_result->isInvalidNumber();
    }

    public function scheduleNextAttempt(int $hoursFromNow = 24): void
    {
        $this->update([
            'next_attempt_at' => now()->addHours($hoursFromNow),
        ]);
    }

    public function markSurveyCompleted(): void
    {
        $this->update(['survey_completed' => true]);
    }

    public function getDurationInMinutes(): int
    {
        return (int) ceil($this->call_duration / 60);
    }

    public function getFormattedDuration(): string
    {
        $minutes = (int) floor($this->call_duration / 60);
        $seconds = $this->call_duration % 60;

        return sprintf('%d:%02d', $minutes, $seconds);
    }
}

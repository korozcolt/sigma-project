<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessageTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'name',
        'type',
        'channel',
        'subject',
        'content',
        'is_active',
        'max_per_voter_per_day',
        'max_per_campaign_per_hour',
        'allowed_start_time',
        'allowed_end_time',
        'allowed_days',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'allowed_days' => 'array',
        'allowed_start_time' => 'datetime:H:i:s',
        'allowed_end_time' => 'datetime:H:i:s',
    ];

    // Relaciones
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'template_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    public function scopeForCampaign($query, int $campaignId)
    {
        return $query->where('campaign_id', $campaignId);
    }

    // Métodos para reemplazar variables
    public function renderContent(array $variables): string
    {
        $content = $this->content;

        foreach ($variables as $key => $value) {
            $content = str_replace("{{{$key}}}", (string) $value, $content);
        }

        return $content;
    }

    public function renderSubject(array $variables): ?string
    {
        if (! $this->subject) {
            return null;
        }

        $subject = $this->subject;

        foreach ($variables as $key => $value) {
            $subject = str_replace("{{{$key}}}", (string) $value, $subject);
        }

        return $subject;
    }

    // Validación de horarios
    public function isWithinAllowedTime(): bool
    {
        $now = now();
        $currentTime = $now->format('H:i:s');
        $currentDay = strtolower($now->format('l'));

        // Verificar horario
        $allowedStartTime = $this->allowed_start_time instanceof \DateTime
            ? $this->allowed_start_time->format('H:i:s')
            : $this->allowed_start_time;

        $allowedEndTime = $this->allowed_end_time instanceof \DateTime
            ? $this->allowed_end_time->format('H:i:s')
            : $this->allowed_end_time;

        if ($currentTime < $allowedStartTime || $currentTime > $allowedEndTime) {
            return false;
        }

        // Verificar día de la semana
        if ($this->allowed_days && ! in_array($currentDay, $this->allowed_days)) {
            return false;
        }

        return true;
    }

    // Control de rate limiting
    public function canSendToVoter(Voter $voter): bool
    {
        $count = Message::where('voter_id', $voter->id)
            ->where('template_id', $this->id)
            ->whereDate('created_at', today())
            ->count();

        return $count < $this->max_per_voter_per_day;
    }

    public function canSendInCampaign(): bool
    {
        $count = Message::where('campaign_id', $this->campaign_id)
            ->where('template_id', $this->id)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        return $count < $this->max_per_campaign_per_hour;
    }
}

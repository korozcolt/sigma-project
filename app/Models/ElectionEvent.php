<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ElectionEvent extends Model
{
    /** @use HasFactory<\Database\Factories\ElectionEventFactory> */
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'name',
        'type',
        'date',
        'start_time',
        'end_time',
        'is_active',
        'simulation_number',
        'notes',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function voteRecords(): HasMany
    {
        return $this->hasMany(VoteRecord::class);
    }

    public function isSimulation(): bool
    {
        return $this->type === 'simulation';
    }

    public function isReal(): bool
    {
        return $this->type === 'real';
    }

    public function isToday(): bool
    {
        return $this->date->isToday();
    }

    public function canActivate(): bool
    {
        return $this->isToday() && ! $this->is_active;
    }

    public function canDeactivate(): bool
    {
        return $this->is_active;
    }

public function isWithinTimeRange(): bool
    {
        if (! $this->start_time || ! $this->end_time) {
            return true;
        }

        $now = now();
        $startTime = now()->setTimeFromTimeString($this->start_time);
        $endTime = now()->setTimeFromTimeString($this->end_time);

        // Handle case where end time is after midnight (crosses day boundary)
        if ($endTime->lt($startTime)) {
            return $now->gte($startTime) || $now->lte($endTime);
        }

        return $now->gte($startTime) && $now->lte($endTime);
    }

    public function activate(): bool
    {
        if (! $this->canActivate()) {
            return false;
        }

        // Desactivar otros eventos activos de la misma campaña
        static::where('campaign_id', $this->campaign_id)
            ->where('id', '!=', $this->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        return $this->update(['is_active' => true]);
    }

    public function deactivate(): bool
    {
        if (! $this->canDeactivate()) {
            return false;
        }

        return $this->update(['is_active' => false]);
    }

    public function getStatusBadgeAttribute(): string
    {
        if ($this->is_active) {
            return 'Activo';
        }

        if ($this->date->isFuture()) {
            return 'Programado';
        }

        return 'Realizado';
    }

    public function getTypeLabel(): string
    {
        return match ($this->type) {
            'simulation' => 'Simulacro',
            'real' => 'Día D Real',
            default => 'Desconocido',
        };
    }
}

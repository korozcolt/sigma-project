<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RevalidationRun extends Model
{
    /** @use HasFactory<\Database\Factories\RevalidationRunFactory> */
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'leader_id',
        'started_at',
        'finished_at',
        'total',
        'processed',
        'changed',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'total' => 'integer',
            'processed' => 'integer',
            'changed' => 'integer',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_id');
    }
}

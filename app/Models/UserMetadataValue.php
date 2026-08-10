<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMetadataValue extends Model
{
    /** @use HasFactory<\Database\Factories\UserMetadataValueFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'metadata_key_id',
        'value',
        'assigned_by',
        'assigned_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function metadataKey(): BelongsTo
    {
        return $this->belongsTo(MetadataKey::class);
    }

    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}

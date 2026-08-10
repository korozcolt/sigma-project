<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetadataKey extends Model
{
    /** @use HasFactory<\Database\Factories\MetadataKeyFactory> */
    use HasFactory;

    protected $fillable = [
        'key',
        'label',
        'type',
        'options',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function values(): HasMany
    {
        return $this->hasMany(UserMetadataValue::class);
    }
}

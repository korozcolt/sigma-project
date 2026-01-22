<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PollingPlace extends Model
{
    /** @use HasFactory<\Database\Factories\PollingPlaceFactory> */
    use HasFactory;

    protected $fillable = [
        'department_id',
        'municipality_id',
        'dane_department_code',
        'dane_municipality_code',
        'zone_code',
        'place_code',
        'name',
        'address',
        'commune',
        'max_tables',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function voters(): HasMany
    {
        return $this->hasMany(Voter::class);
    }
}


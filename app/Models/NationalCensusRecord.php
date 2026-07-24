<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NationalCensusRecord extends Model
{
    /** @use HasFactory<\Database\Factories\NationalCensusRecordFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'document_number',
        'polling_place_id',
        'dane_department_code',
        'dane_municipality_code',
        'zone_code',
        'place_code',
        'polling_station_name',
        'table_number',
        'imported_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'imported_at' => 'datetime',
        ];
    }

    public function pollingPlace(): BelongsTo
    {
        return $this->belongsTo(PollingPlace::class);
    }
}

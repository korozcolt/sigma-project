<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subcategoria extends Model
{
    /** @use HasFactory<\Database\Factories\SubcategoriaFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'gremio_id',
        'name',
    ];

    /**
     * Get the gremio that owns the subcategoria.
     */
    public function gremio(): BelongsTo
    {
        return $this->belongsTo(Gremio::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gremio extends Model
{
    /** @use HasFactory<\Database\Factories\GremioFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
    ];

    /**
     * Get the subcategorias for the gremio.
     */
    public function subcategorias(): HasMany
    {
        return $this->hasMany(Subcategoria::class);
    }
}

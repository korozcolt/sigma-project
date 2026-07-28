<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NationalIdentityRecord extends Model
{
    /** @use HasFactory<\Database\Factories\NationalIdentityRecordFactory> */
    use HasFactory;

    protected $fillable = [
        'cedula',
        'nombre1',
        'nombre2',
        'apellido1',
        'apellido2',
    ];
}

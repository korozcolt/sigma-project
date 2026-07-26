<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistraduriaLookup extends Model
{
    /** @use HasFactory<\Database\Factories\RegistraduriaLookupFactory> */
    use HasFactory;

    protected $fillable = [
        'document_number',
        'puesto_nombre',
        'puesto_codigo',
        'zona_codigo',
        'mesa_numero',
        'departamento',
        'municipio',
        'direccion',
        'source',
        'campaign_id',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Raw fields in the exact shape RegistraduriaService/PollingPlaceResolver already use
     * (puesto_nombre, puesto_codigo, zona_codigo, mesa_numero, departamento, municipio,
     * direccion) — no translation layer needed by callers.
     *
     * @return array{puesto_nombre: string, puesto_codigo: string, zona_codigo: string, mesa_numero: string, departamento: string, municipio: string, direccion: string}
     */
    public function toRegistraduriaFields(): array
    {
        return [
            'puesto_nombre' => $this->puesto_nombre ?? '',
            'puesto_codigo' => $this->puesto_codigo ?? '',
            'zona_codigo' => $this->zona_codigo ?? '',
            'mesa_numero' => $this->mesa_numero ?? '',
            'departamento' => $this->departamento ?? '',
            'municipio' => $this->municipio ?? '',
            'direccion' => $this->direccion ?? '',
        ];
    }
}

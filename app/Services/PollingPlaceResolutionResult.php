<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PollingPlaceSource;

final readonly class PollingPlaceResolutionResult
{
    /**
     * @param  array<string, string|null>  $fields  Raw polling-place fields in the existing
     *                                              Registraduría shape: puesto_nombre, puesto_codigo, zona_codigo, mesa_numero,
     *                                              departamento, municipio, direccion. Kept in this exact shape so callers (the
     *                                              Filament trait's fillPollingPlaceFields()) need no translation layer.
     * @param  ?int  $pollingPlaceId  Resolved PollingPlace row id, snapshotted for the audit
     *                                row (App\Models\PollingPlaceResolution.polling_place_id) — NOT the same class
     *                                as this VO, despite the similar name.
     */
    public function __construct(
        public PollingPlaceSource $source,
        public array $fields,
        public ?int $pollingPlaceId = null,
        public ?string $tableNumber = null,
    ) {}
}

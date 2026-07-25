<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PollingPlaceSource;
use App\Models\CensusRecord;
use App\Models\Municipality;
use App\Models\NationalCensusRecord;
use App\Models\PollingPlace;

class PollingPlaceResolver
{
    public function __construct(
        /** @var iterable<LiveSourceAdapter> */
        private readonly iterable $liveAdapters,
    ) {}

    /** True if ANY configured live adapter reports reachable (LIVE-01 priority order, LIVE-03 gate). */
    public function isLiveReachable(): bool
    {
        foreach ($this->liveAdapters as $adapter) {
            if ($adapter->isReachable()) {
                return true;
            }
        }

        return false;
    }

    /** Starts a lookup on the first configured adapter (LIVE-01 priority order). */
    public function startLiveLookup(string $cedula): string
    {
        foreach ($this->liveAdapters as $adapter) {
            return $adapter->startLookup($cedula);
        }

        throw new \RuntimeException('No live source adapters configured.');
    }

    /**
     * Reconstruct polling-place data from this campaign's own census_records + polling_places.
     * Lifted from HasRegistraduriaPolling::resolveFromDatabase() (unchanged join logic).
     */
    public function resolveFromCampaignCensus(string $cedula): ?PollingPlaceResolutionResult
    {
        $census = CensusRecord::query()
            ->where('document_number', $cedula)
            ->whereNotNull('polling_station')
            ->latest('imported_at')
            ->first();

        if (! $census || blank($census->polling_station)) {
            return null;
        }

        $municipality = filled($census->municipality_code)
            ? Municipality::query()->where('code', $census->municipality_code)->first()
            : null;

        $pollingPlace = null;
        if ($municipality) {
            $pollingPlace = PollingPlace::query()
                ->where('municipality_id', $municipality->id)
                ->where('name', $census->polling_station)
                ->with(['municipality.department', 'department'])
                ->first();
        }

        if (! $municipality && ! $pollingPlace) {
            return null;
        }

        $department = $pollingPlace?->department
            ?? $pollingPlace?->municipality?->department
            ?? $municipality?->department;

        $fields = [
            'puesto_nombre' => $census->polling_station,
            'puesto_codigo' => $pollingPlace?->place_code ?? '',
            'zona_codigo' => $pollingPlace?->zone_code ?? '',
            'mesa_numero' => (string) ($census->table_number ?? ''),
            'departamento' => $department?->name ?? '',
            'municipio' => $municipality?->name ?? $pollingPlace?->municipality?->name ?? '',
            'direccion' => $pollingPlace?->address ?? '',
        ];

        return new PollingPlaceResolutionResult(
            source: PollingPlaceSource::DB_RECONSTRUCTION,
            fields: $fields,
            pollingPlaceId: $pollingPlace?->id,
            tableNumber: ltrim($fields['mesa_numero'], '0') ?: null,
        );
    }

    /**
     * Resolve from the national census snapshot (Phase 6's national_census_records,
     * already divipol-joined against polling_places at import time). CENSO-01's tier.
     */
    public function resolveFromNationalSnapshot(string $cedula): ?PollingPlaceResolutionResult
    {
        $record = NationalCensusRecord::query()
            ->where('document_number', $cedula)
            ->with(['pollingPlace.department', 'pollingPlace.municipality.department'])
            ->first();

        if (! $record || ! $record->polling_place_id || ! $record->pollingPlace) {
            return null;
        }

        $pollingPlace = $record->pollingPlace;
        $department = $pollingPlace->department ?? $pollingPlace->municipality?->department;

        $fields = [
            'puesto_nombre' => $record->polling_station_name ?? $pollingPlace->name,
            'puesto_codigo' => (string) $pollingPlace->place_code,
            'zona_codigo' => (string) $pollingPlace->zone_code,
            'mesa_numero' => (string) ($record->table_number ?? ''),
            'departamento' => $department?->name ?? '',
            'municipio' => $pollingPlace->municipality?->name ?? '',
            'direccion' => $pollingPlace->address ?? '',
        ];

        return new PollingPlaceResolutionResult(
            source: PollingPlaceSource::SNAPSHOT,
            fields: $fields,
            pollingPlaceId: $pollingPlace->id,
            tableNumber: ltrim($fields['mesa_numero'], '0') ?: null,
        );
    }
}

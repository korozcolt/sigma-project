<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PollingPlaceSource;
use App\Models\CensusRecord;
use App\Models\Municipality;
use App\Models\NationalCensusRecord;
use App\Models\PollingPlace;
use App\Models\PollingPlaceResolution;
use App\Models\RegistraduriaLookup;
use App\Models\Voter;
use Illuminate\Support\Sleep;

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

    /**
     * Starts a lookup on the first reachable adapter, in priority order (LIVE-01).
     * Skips unreachable adapters rather than blindly using the first one, so priority
     * order only applies among adapters that are actually up.
     */
    public function startLiveLookup(string $cedula): string
    {
        foreach ($this->liveAdapters as $adapter) {
            if ($adapter->isReachable()) {
                return $adapter->startLookup($cedula);
            }
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

    /**
     * Resolve from the permanent Registraduría lookup table (persisted results from any
     * genuine past live lookup — admin interactive flow or headless reconciliation).
     * Checked before the interactive live modal (replaces the old 30-day cache, D-01 layer 1)
     * and before the automated cascade's live attempt, so a cédula already resolved once
     * never re-pays for a live/2captcha lookup. Treated as PollingPlaceSource::LIVE because
     * every row in this table originated from a genuine live result — more authoritative
     * than a CensusRecord match, mirroring resolveFromNationalSnapshot()'s shape exactly.
     */
    public function resolveFromPermanentLookup(string $cedula): ?PollingPlaceResolutionResult
    {
        $lookup = RegistraduriaLookup::query()->where('document_number', $cedula)->first();

        if (! $lookup) {
            return null;
        }

        $fields = $lookup->toRegistraduriaFields();

        return new PollingPlaceResolutionResult(
            source: PollingPlaceSource::LIVE,
            fields: $fields,
            pollingPlaceId: $this->resolveOrCreatePollingPlace($fields)?->id,
            tableNumber: ltrim($fields['mesa_numero'], '0') ?: null,
        );
    }

    /**
     * Upsert a genuine live Registraduría result into the permanent lookup table, keyed by
     * document_number. Called by every point that produces a real live result (the
     * interactive admin modal's handleRegistraduriaResult() and this class's own
     * resolveAutomated()) so the whole system stops re-paying for a cédula already resolved.
     *
     * @param  array<string, string>  $fields  Raw Registraduría fields (RegistraduriaService shape)
     */
    public function persistPermanentLookup(string $cedula, array $fields, ?int $campaignId = null): void
    {
        RegistraduriaLookup::updateOrCreate(
            ['document_number' => $cedula],
            [
                'puesto_nombre' => $fields['puesto_nombre'] ?? null,
                'puesto_codigo' => $fields['puesto_codigo'] ?? null,
                'zona_codigo' => $fields['zona_codigo'] ?? null,
                'mesa_numero' => $fields['mesa_numero'] ?? null,
                'departamento' => $fields['departamento'] ?? null,
                'municipio' => $fields['municipio'] ?? null,
                'direccion' => $fields['direccion'] ?? null,
                'source' => PollingPlaceSource::LIVE->value,
                'campaign_id' => $campaignId,
            ]
        );
    }

    /**
     * Persist a resolution result against a voter, enforcing the no-downgrade guard
     * (SRC-02) unless $isExplicitOverride is true (D-10 — the "Actualizar datos" force
     * refresh always bypasses the guard). Writes an audit row ONLY on a real source
     * transition (D-11); a no-op re-confirmation still refreshes polling_place_resolved_at
     * (D-12). Returns null when the guard blocks the write; otherwise returns $result.
     *
     * $voter is null when no Voter record exists yet (e.g. the Filament CreateVoter flow
     * before the first save) — in that case this is a pure pass-through with no persistence,
     * since there is no voter_id to attach an audit row to.
     */
    public function persist(
        ?Voter $voter,
        PollingPlaceResolutionResult $result,
        bool $isExplicitOverride,
        string $resolvedVia,
    ): ?PollingPlaceResolutionResult {
        if ($voter === null) {
            return $result;
        }

        $existingSource = $voter->polling_place_source;

        if (! $isExplicitOverride && $existingSource !== null && $existingSource->outranks($result->source)) {
            return null;
        }

        $voter->update([
            'polling_place_source' => $result->source,
            'polling_place_resolved_at' => now(),
        ]);

        if ($existingSource !== $result->source) {
            PollingPlaceResolution::create([
                'voter_id' => $voter->id,
                'previous_source' => $existingSource,
                'new_source' => $result->source,
                'polling_place_id' => $result->pollingPlaceId,
                'table_number' => $result->tableNumber,
                'resolved_by' => auth()->id(),
                'resolved_via' => $resolvedVia,
            ]);
        }

        return $result;
    }

    /**
     * Poll a started live session up to 5 times with short backoff, giving up
     * immediately (not after exhausting polls) if the status is waiting_captcha —
     * that status means a human needs to interact, which the automated path can
     * never do (D-07). Total wall-clock stays well under 10s (D-08).
     *
     * @return array<string,string>|null Raw fields on success, null on any give-up.
     */
    private function attemptLiveAutomated(LiveSourceAdapter $adapter, string $cedula): ?array
    {
        if (! $adapter->isReachable()) {
            return null;
        }

        try {
            $sessionId = $adapter->startLookup($cedula);
        } catch (\Exception) {
            return null;
        }

        $backoffMs = [200, 400, 800, 1200, 1600];

        foreach ($backoffMs as $i => $delayMs) {
            $result = $adapter->getResult($sessionId);

            if ($result['status'] === 'done') {
                return $result['data'];
            }

            if ($result['status'] === 'waiting_captcha' || $result['status'] === 'error') {
                return null;
            }

            if ($i < count($backoffMs) - 1) {
                Sleep::for($delayMs)->milliseconds();
            }
        }

        return null;
    }

    /**
     * Automated/headless cascade (D-03): live -> national snapshot only. Never blocks
     * (LIVE-03) — every give-up path returns promptly. Always persists via persist()
     * with isExplicitOverride=false (the automatic no-downgrade guard always applies here).
     */
    public function resolveAutomated(string $cedula, Voter $voter, string $resolvedVia = 'reconciliation'): ?PollingPlaceResolutionResult
    {
        if ($fromPermanentLookup = $this->resolveFromPermanentLookup($cedula)) {
            return $this->persist($voter, $fromPermanentLookup, isExplicitOverride: false, resolvedVia: $resolvedVia);
        }

        foreach ($this->liveAdapters as $adapter) {
            if ($fields = $this->attemptLiveAutomated($adapter, $cedula)) {
                $this->persistPermanentLookup($cedula, $fields, $voter->campaign_id);

                $result = new PollingPlaceResolutionResult(
                    source: PollingPlaceSource::LIVE,
                    fields: $fields,
                    pollingPlaceId: $this->resolveOrCreatePollingPlace($fields)?->id,
                    tableNumber: ltrim($fields['mesa_numero'] ?? '', '0') ?: null,
                );

                return $this->persist($voter, $result, isExplicitOverride: false, resolvedVia: $resolvedVia);
            }
        }

        if ($fromSnapshot = $this->resolveFromNationalSnapshot($cedula)) {
            return $this->persist($voter, $fromSnapshot, isExplicitOverride: false, resolvedVia: $resolvedVia);
        }

        return null;
    }

    /**
     * Resolve-or-create the PollingPlace a fresh live result refers to. Shared by both
     * the automated/headless cascade (resolveAutomated(), used by ReconcileFallbackPollingPlaces)
     * and the interactive Filament modal (HasRegistraduriaPolling::fillPollingPlaceFields())
     * so both callers get identical matching/creation behaviour (LIVE-01).
     *
     * Real live Registraduría lookups never expose a "CODIGO PUESTO"/"ZONA" column
     * (see RegistraduriaService::parseConsultaHtml() docblock), so puesto_codigo/zona_codigo
     * are blank ('') for EVERY live-sourced result in practice — this is not an edge case.
     * When blank, match/create by (municipality_id, name) instead of by DIVIPOLE codes —
     * inserting '' into the NOT NULL-turned-nullable unsignedSmallInteger zone_code/place_code
     * columns previously threw a QueryException (MySQL 1366 "Incorrect integer value").
     * See .planning/debug/resolved/registraduria-interactive-result-not-parsed.md.
     */
    public function resolveOrCreatePollingPlace(array $fields): ?PollingPlace
    {
        $municipality = Municipality::query()
            ->whereRaw('LOWER(name) = ?', [strtolower($fields['municipio'] ?? '')])
            ->first();

        if (! $municipality) {
            return null;
        }

        $name = $fields['puesto_nombre'] ?? '';
        $zoneCode = filled($fields['zona_codigo'] ?? null) ? $fields['zona_codigo'] : null;
        $placeCode = filled($fields['puesto_codigo'] ?? null) ? $fields['puesto_codigo'] : null;

        if ($zoneCode !== null && $placeCode !== null) {
            return PollingPlace::firstOrCreate(
                [
                    'municipality_id' => $municipality->id,
                    'zone_code' => $zoneCode,
                    'place_code' => $placeCode,
                ],
                [
                    'name' => $name ?: 'Desconocido',
                    'address' => $fields['direccion'] ?? null,
                    'department_id' => $municipality->department_id,
                    'max_tables' => 0,
                ]
            );
        }

        if (blank($name)) {
            return null;
        }

        return PollingPlace::query()
            ->where('municipality_id', $municipality->id)
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->first()
            ?? PollingPlace::create([
                'municipality_id' => $municipality->id,
                'zone_code' => null,
                'place_code' => null,
                'name' => $name,
                'address' => $fields['direccion'] ?? null,
                'department_id' => $municipality->department_id,
                'max_tables' => 0,
            ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PollingPlaceSource;
use App\Exceptions\RegistraduriaLookupInProgressException;
use App\Jobs\CollectRegistraduriaLookupResult;
use App\Models\CensusRecord;
use App\Models\Municipality;
use App\Models\NationalCensusRecord;
use App\Models\PollingPlace;
use App\Models\PollingPlaceResolution;
use App\Models\RegistraduriaLiveSession;
use App\Models\RegistraduriaLookup;
use App\Models\Voter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Sleep;

class PollingPlaceResolver
{
    private const LIVE_POLL_ATTEMPTS = 9;

    private const LIVE_POLL_INTERVAL_MS = 5000;

    /**
     * How long a RegistraduriaLiveSession claim survives before it's considered
     * abandoned and a fresh attempt is allowed again — bounds the concurrency
     * guard/cooldown even if nothing ever explicitly releases the row (crash,
     * abandoned browser tab). Comfortably past the microservice's own ~150s
     * hard 2captcha-solving bound, and matches CollectRegistraduriaLookupResult's
     * own collection window. See .planning/debug/resolved/2captcha-duplicate-spend.md.
     */
    private const LIVE_SESSION_WINDOW_MINUTES = 10;

    private const COLLECTOR_INITIAL_DELAY_SECONDS = 90;

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
     *
     * Claims a RegistraduriaLiveSession for $cedula BEFORE ever calling a real
     * adapter's startLookup() (the actual 2captcha-spending call) — the interactive
     * modal's "consultar"/"actualizar datos" buttons are the other real-money entry
     * point besides the automated cascade (attemptLiveAutomated()), and without this
     * guard a page refresh, a modal close/reopen, or a repeated force-refresh click
     * during a slow captcha solve would each start ANOTHER paid live lookup for the
     * same cédula while the first one might still be genuinely resolving. Throws
     * RegistraduriaLookupInProgressException (never calls any adapter) when a claim
     * already exists — every existing generic `catch (\Exception $e)` call site
     * keeps working unchanged. See .planning/debug/resolved/2captcha-duplicate-spend.md.
     */
    public function startLiveLookup(string $cedula, ?int $voterId = null, ?int $campaignId = null, string $resolvedVia = 'interactive'): string
    {
        if (! $this->claimLiveSession($cedula, $voterId, $campaignId, $resolvedVia)) {
            throw new RegistraduriaLookupInProgressException(
                'Ya hay una consulta en curso para esta cédula. Espera unos segundos e intenta de nuevo.'
            );
        }

        foreach ($this->liveAdapters as $adapter) {
            if ($adapter->isReachable()) {
                try {
                    $sessionId = $adapter->startLookup($cedula);
                } catch (\Exception $e) {
                    $this->releaseLiveSession($cedula);

                    throw $e;
                }

                $this->attachAdapterToSession($cedula, $sessionId, $adapter);

                return $sessionId;
            }
        }

        $this->releaseLiveSession($cedula);

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
     *
     * Also writes polling_place_id (the actual FK the "Ranking de Puestos de Votación"
     * widget's PollingPlace::voters() relation and the "sin puesto de votación asignado"
     * count both depend on) whenever $result->pollingPlaceId is non-null — every resolve*()
     * method on this class already computes it via resolveOrCreatePollingPlace(), but until
     * this fix persist() silently dropped it, so only the interactive Filament flow (which
     * separately assigns $this->data['polling_place_id'] before the ordinary form save) ever
     * ended up with the FK populated; the entire automated cascade (resolveAutomated(), used
     * by ReconcileFallbackPollingPlaces and VoterValidationService) never did.
     * See .planning/debug/resolved/polling-place-id-not-persisted-by-resolver.md.
     *
     * Deliberately NEVER clears an existing polling_place_id when $result->pollingPlaceId is
     * null (e.g. resolveOrCreatePollingPlace() couldn't match a municipality/name at
     * re-resolution time) — that would downgrade a previously-good FK to NULL on a merely
     * incomplete re-resolution, the same no-downgrade spirit as the source guard above but
     * applied independently since a result can carry a real source with no resolvable
     * pollingPlaceId (e.g. a DB_RECONSTRUCTION result with no municipality match).
     *
     * Also writes polling_table_number (voters.polling_table_number), the sibling of the
     * polling_place_id bug fixed above — same root cause (persist() silently dropping a
     * value the resolve*() methods already compute via $result->tableNumber), same fix
     * shape, same backfill-command precedent. See
     * .planning/debug/resolved/polling-place-id-not-persisted-by-resolver.md. A real
     * $result->tableNumber ALWAYS overwrites whatever is currently stored — mirroring the
     * polling_place_id FK block's overwrite philosophy above, since real data can always
     * correct stale/defaulted data. When the source carries no mesa number at all, the
     * weaker single-mesa '1' default (used only when the resolved PollingPlace has exactly
     * one possible mesa, max_tables === 1) is deliberately guarded to fill ONLY a currently-
     * null value — never overwriting an existing real or previously-defaulted number, since
     * we can't distinguish the two from the column alone and a weaker default must never
     * clobber a stronger prior write.
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

        $updates = [
            'polling_place_source' => $result->source,
            'polling_place_resolved_at' => now(),
        ];

        if ($result->pollingPlaceId !== null) {
            $updates['polling_place_id'] = $result->pollingPlaceId;
        }

        if ($result->tableNumber !== null) {
            $updates['polling_table_number'] = $result->tableNumber;
        } elseif ($voter->polling_table_number === null && $result->pollingPlaceId !== null) {
            $pollingPlace = PollingPlace::find($result->pollingPlaceId);

            if ($pollingPlace?->max_tables === 1) {
                $updates['polling_table_number'] = '1';
            }
        }

        $voter->update($updates);

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
     * Poll a started live session up to LIVE_POLL_ATTEMPTS (9) times, sleeping
     * LIVE_POLL_INTERVAL_MS (5s) between polls, giving up immediately (not after
     * exhausting polls) if the status is waiting_captcha — that status means a human
     * needs to interact, which the automated path can never do (D-07).
     *
     * Assumes the adapter is already known-reachable — resolveAutomated()'s loop is
     * now the SOLE isReachable() check for the automated cascade (this method no
     * longer duplicates it), so escalation to the next adapter is decided in exactly
     * one place.
     *
     * The window was deliberately widened to ~40s/adapter (9 polls, 8x 5s sleeps —
     * within an instructed 30-45s per-adapter budget) because the real 2captcha-backed
     * registraduria-service microservice can take up to 150s to resolve a challenge
     * (its own polling tick is also 5s — see app.py's `for _ in range(30): sleep(5)`),
     * and the prior ~2.6s window (200/400/800/1200/1600ms, 5 polls/4 sleeps) was
     * discarding real, already-paid-for captcha-solving work almost immediately.
     * Bounded well short of the real 150s worst case to keep the hourly reconciliation
     * job — which can process up to 50 voters times up to 3 cascading adapters — from
     * running for hours.
     *
     * Claims a RegistraduriaLiveSession for $cedula before ever calling
     * $adapter->startLookup() (the concurrency guard — see startLiveLookup()'s
     * docblock; the two hourly cron jobs sharing this cascade are the other race
     * this closes). On the terminal outcomes reachable within this method's own
     * synchronous poll window (success, done-but-blank, waiting_captcha, error), the
     * claim is released immediately since a definitive answer is already in hand.
     *
     * On the ONE remaining exit — every poll returned 'pending' and all
     * LIVE_POLL_ATTEMPTS are exhausted — the claim is deliberately kept and handed
     * off to CollectRegistraduriaLookupResult (real queue, never dispatchSync) so an
     * already-paid-for solve that's still genuinely in progress is never silently
     * discarded/re-paid-for. Skipped for non-instantiable adapters (anonymous test
     * doubles — see isDispatchableAdapter()), which just release immediately instead.
     * See .planning/debug/resolved/2captcha-duplicate-spend.md.
     *
     * @return array<string,string>|null Raw fields on success, null on any give-up.
     */
    private function attemptLiveAutomated(LiveSourceAdapter $adapter, string $cedula, Voter $voter, string $resolvedVia): ?array
    {
        if (! $this->claimLiveSession($cedula, $voter->id, $voter->campaign_id, $resolvedVia)) {
            return null;
        }

        try {
            $sessionId = $adapter->startLookup($cedula);
        } catch (\Exception) {
            $this->releaseLiveSession($cedula);

            return null;
        }

        $this->attachAdapterToSession($cedula, $sessionId, $adapter);

        $backoffMs = array_fill(0, self::LIVE_POLL_ATTEMPTS, self::LIVE_POLL_INTERVAL_MS);

        foreach ($backoffMs as $i => $delayMs) {
            $result = $adapter->getResult($sessionId);

            if ($result['status'] === 'done') {
                // Guard: a done status with no usable puesto_nombre is treated as
                // non-success (equivalent to not_found), never as a live match — defends
                // against ANY adapter/microservice returning "done" without a distinct
                // not_found outcome (the infovotantes Python flow did exactly this).
                if (blank($result['data']['puesto_nombre'] ?? null)) {
                    $this->releaseLiveSession($cedula);

                    return null;
                }

                $this->releaseLiveSession($cedula);

                return $result['data'];
            }

            if ($result['status'] === 'waiting_captcha' || $result['status'] === 'error') {
                $this->releaseLiveSession($cedula);

                return null;
            }

            if ($i < count($backoffMs) - 1) {
                Sleep::for($delayMs)->milliseconds();
            }
        }

        if (! $this->isDispatchableAdapter($adapter)) {
            $this->releaseLiveSession($cedula);

            return null;
        }

        CollectRegistraduriaLookupResult::dispatch($cedula)->delay(now()->addSeconds(self::COLLECTOR_INITIAL_DELAY_SECONDS));

        return null;
    }

    /**
     * Automated/headless cascade (D-03): live -> national snapshot only. Never blocks
     * (LIVE-03) — every give-up path returns promptly. Always persists via persist()
     * with isExplicitOverride=false (the automatic no-downgrade guard always applies here).
     *
     * Escalates to the NEXT live adapter ONLY when the current one is unreachable
     * (isReachable() === false). Once a reachable adapter is attempted, ANY non-success
     * result (timeout/exhausted-polls, error, waiting_captcha, not_found) stops the live
     * cascade for this cédula entirely and falls through to the free national snapshot
     * tier — it never tries a second/third live adapter. This prevents tripling 2captcha
     * spend per cédula on a merely-slow/failed (but reachable) live site.
     */
    public function resolveAutomated(string $cedula, Voter $voter, string $resolvedVia = 'reconciliation'): ?PollingPlaceResolutionResult
    {
        if ($fromPermanentLookup = $this->resolveFromPermanentLookup($cedula)) {
            return $this->persist($voter, $fromPermanentLookup, isExplicitOverride: false, resolvedVia: $resolvedVia);
        }

        foreach ($this->liveAdapters as $adapter) {
            if (! $adapter->isReachable()) {
                continue;
            }

            // A reachable adapter gets exactly one attempt. Any non-success (timeout,
            // error, waiting_captcha, not_found) stops the live cascade HERE — it falls
            // through to the free national snapshot tier below, never to another
            // live/2captcha adapter. Only genuine unreachability (above) advances the loop.
            if ($fields = $this->attemptLiveAutomated($adapter, $cedula, $voter, $resolvedVia)) {
                $this->persistPermanentLookup($cedula, $fields, $voter->campaign_id);

                $result = new PollingPlaceResolutionResult(
                    source: PollingPlaceSource::LIVE,
                    fields: $fields,
                    pollingPlaceId: $this->resolveOrCreatePollingPlace($fields)?->id,
                    tableNumber: ltrim($fields['mesa_numero'] ?? '', '0') ?: null,
                );

                return $this->persist($voter, $result, isExplicitOverride: false, resolvedVia: $resolvedVia);
            }

            break;
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

        $mesaNumero = (int) ($fields['mesa_numero'] ?? 0);
        $maxTablesFromMesa = $mesaNumero > 0 ? $mesaNumero : null;

        if ($zoneCode !== null && $placeCode !== null) {
            $pollingPlace = PollingPlace::query()
                ->where('municipality_id', $municipality->id)
                ->where('zone_code', $zoneCode)
                ->where('place_code', $placeCode)
                ->first();

            if ($pollingPlace) {
                if ($maxTablesFromMesa !== null && $maxTablesFromMesa > $pollingPlace->max_tables) {
                    $pollingPlace->update(['max_tables' => $maxTablesFromMesa]);
                }

                return $pollingPlace;
            }

            return PollingPlace::create([
                'municipality_id' => $municipality->id,
                'zone_code' => $zoneCode,
                'place_code' => $placeCode,
                'name' => $name ?: 'Desconocido',
                'address' => $fields['direccion'] ?? null,
                'department_id' => $municipality->department_id,
                'max_tables' => $maxTablesFromMesa ?? 0,
            ]);
        }

        if (blank($name)) {
            return null;
        }

        $pollingPlace = PollingPlace::query()
            ->where('municipality_id', $municipality->id)
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->first();

        if ($pollingPlace) {
            if ($maxTablesFromMesa !== null && $maxTablesFromMesa > $pollingPlace->max_tables) {
                $pollingPlace->update(['max_tables' => $maxTablesFromMesa]);
            }

            return $pollingPlace;
        }

        return PollingPlace::create([
            'municipality_id' => $municipality->id,
            'zone_code' => null,
            'place_code' => null,
            'name' => $name,
            'address' => $fields['direccion'] ?? null,
            'department_id' => $municipality->department_id,
            'max_tables' => $maxTablesFromMesa ?? 0,
        ]);
    }

    /**
     * Atomically claim the single RegistraduriaLiveSession slot for $cedula. Relies on
     * the table's unique index on document_number as the actual concurrency guard
     * (robust across processes/hosts sharing the same MySQL instance, unlike an
     * in-memory or cache-driver-dependent lock) rather than a check-then-insert
     * pattern, which would have a race window between the SELECT and the INSERT.
     * Returns false — without throwing — when a claim already exists.
     */
    private function claimLiveSession(string $cedula, ?int $voterId, ?int $campaignId, string $resolvedVia): bool
    {
        try {
            RegistraduriaLiveSession::create([
                'document_number' => $cedula,
                'voter_id' => $voterId,
                'campaign_id' => $campaignId,
                'resolved_via' => $resolvedVia,
                'expires_at' => now()->addMinutes(self::LIVE_SESSION_WINDOW_MINUTES),
            ]);

            return true;
        } catch (QueryException) {
            return false;
        }
    }

    private function attachAdapterToSession(string $cedula, string $sessionId, LiveSourceAdapter $adapter): void
    {
        RegistraduriaLiveSession::where('document_number', $cedula)->update([
            'session_id' => $sessionId,
            'adapter_class' => get_class($adapter),
        ]);
    }

    /**
     * Release the claim for $cedula, if any. Public so callers outside this class that
     * observe a definitive interactive result in real time (HasRegistraduriaPolling's
     * fast-path handler for the browser's own Alpine.js polling loop) can release
     * immediately instead of waiting for CollectRegistraduriaLookupResult's next check.
     */
    public function releaseLiveSession(string $cedula): void
    {
        RegistraduriaLiveSession::where('document_number', $cedula)->delete();
    }

    /**
     * CollectRegistraduriaLookupResult resolves the stored adapter_class back into an
     * instance via the container (`app($class)`) once the synchronous cascade window
     * gives up. An anonymous class (every LiveSourceAdapter test double throughout the
     * test suite) either can't be resolved this way at all, or — worse — under the
     * `sync` queue driver used in testing (phpunit.xml), the job would run INLINE
     * within the very test that triggered it, resolving a brand-new instance separate
     * from the test's own reference-capturing anonymous class and potentially
     * recursing (self-redispatch also runs inline under `sync`). Real production
     * adapters (RegistraduriaService, InfovotantesService, ConsultaCensoService) are
     * always real, named, container-resolvable classes, so this check only ever skips
     * dispatch for test doubles. See .planning/debug/resolved/2captcha-duplicate-spend.md.
     */
    private function isDispatchableAdapter(LiveSourceAdapter $adapter): bool
    {
        return ! str_contains(get_class($adapter), '@anonymous');
    }
}

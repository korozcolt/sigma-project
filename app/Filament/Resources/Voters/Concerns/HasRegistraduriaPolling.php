<?php

namespace App\Filament\Resources\Voters\Concerns;

use App\Enums\PollingPlaceSource;
use App\Exceptions\RegistraduriaLookupInProgressException;
use App\Models\CensusRecord;
use App\Models\Department;
use App\Models\Municipality;
use App\Services\CampaignContext;
use App\Services\PollingPlaceResolutionResult;
use App\Services\PollingPlaceResolver;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Livewire\Attributes\On;

trait HasRegistraduriaPolling
{
    public string $registraduriaSessionId = '';

    public bool $registraduriaOpen = false;

    /** Set by forceRefreshFromRegistraduria() so handleRegistraduriaResult() knows to
     *  bypass the no-downgrade guard for this one pending result (D-10). */
    public bool $registraduriaForceOverride = false;

    /** Save-bound form fields fillPollingPlaceFields() mutates that must be reverted if
     *  PollingPlaceResolver::persist() blocks the write (SRC-02) — see applyResolvedFields(). */
    private const GUARDED_IDENTITY_FIELDS = [
        'polling_place_id',
        'municipality_id',
        'department_id',
        'polling_table_number',
        'address',
        'detailed_address',
    ];

    /**
     * Called by the suffixAction on the document_number field.
     *
     * Lookup order (D-01): permanent Registraduría lookup table -> live (if reachable) ->
     * DB reconstruction -> national snapshot. Live is attempted first when reachable
     * because the operator
     * explicitly prioritized freshness/reliability over cost; when live is unreachable
     * (or REGISTRADURIA_LIVE_ENABLED=false), the live browser modal is never opened at
     * all (LIVE-03) and the cascade falls through to DB reconstruction, then the
     * national snapshot (CENSO-01).
     */
    public function openRegistraduriaBrowser(string $cedula): void
    {
        if (blank($cedula)) {
            Notification::make()
                ->title('Número de documento requerido')
                ->body('Ingresa el número de cédula antes de consultar.')
                ->warning()
                ->send();

            return;
        }

        $resolver = app(PollingPlaceResolver::class);

        // Layer 1: permanent Registraduría lookup table (replaces the old 30-day Redis-backed
        // cache — same invariant holds: only ever written by a genuine live result, either here
        // via handleRegistraduriaResult() or headlessly via PollingPlaceResolver::resolveAutomated(),
        // so a hit is always safely LIVE-sourced.
        $permanentResult = $resolver->resolveFromPermanentLookup($cedula);
        if ($permanentResult) {
            $this->applyResolvedFields($permanentResult);
            Notification::make()
                ->title('Puesto de votación (ya verificado por Registraduría)')
                ->body("Puesto: {$permanentResult->fields['puesto_nombre']} — Mesa: {$permanentResult->fields['mesa_numero']}")
                ->success()
                ->send();

            return;
        }

        // D-01/D-04: attempt live first, but only when reachable and not kill-switched.
        if ($resolver->isLiveReachable()) {
            $voter = ($this instanceof EditRecord) ? $this->record : null;

            try {
                $sessionId = $resolver->startLiveLookup(
                    $cedula,
                    voterId: $voter?->id,
                    campaignId: CampaignContext::currentCampaignId(),
                    resolvedVia: 'interactive',
                );
                $this->registraduriaSessionId = $sessionId;
                $this->registraduriaOpen = true;
            } catch (RegistraduriaLookupInProgressException $e) {
                Notification::make()
                    ->title('Consulta en curso')
                    ->body($e->getMessage())
                    ->warning()
                    ->send();
            } catch (\Exception $e) {
                Notification::make()
                    ->title('Error al conectar con el servicio')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }

            return;
        }

        // D-04: live unreachable/disabled — never open the modal, fall through instead.
        $fromDb = $resolver->resolveFromCampaignCensus($cedula);
        if ($fromDb) {
            $this->applyResolvedFields($fromDb);
            // Deliberately NOT caching this tier's result (SRC-02 second-round fix): this
            // query is already a fast, free DB read, and warming the shared LIVE-only cache
            // key here would let a later cache hit mislabel this never-live-verified data
            // as PollingPlaceSource::LIVE, permanently shielding it from correction. Only a
            // genuine live result (handleRegistraduriaResult()) may write to this cache key.
            Notification::make()
                ->title('Puesto de votación (desde base de datos)')
                ->body("Puesto: {$fromDb->fields['puesto_nombre']} — Mesa: {$fromDb->fields['mesa_numero']}")
                ->info()
                ->send();

            return;
        }

        // CENSO-01: national snapshot fallback — new this phase.
        $fromSnapshot = $resolver->resolveFromNationalSnapshot($cedula);
        if ($fromSnapshot) {
            $this->applyResolvedFields($fromSnapshot);
            Notification::make()
                ->title('Puesto de votación (snapshot nacional)')
                ->body("Puesto: {$fromSnapshot->fields['puesto_nombre']} — Mesa: {$fromSnapshot->fields['mesa_numero']}")
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title('Puesto de votación no encontrado')
            ->body('El servicio en vivo no está disponible y no se encontró información en base de datos ni en el snapshot nacional.')
            ->warning()
            ->send();
    }

    /**
     * Force a fresh Registraduría lookup for a cédula, bypassing the Redis cache, DB
     * reconstruction, AND the national snapshot fallback — always straight to live
     * (D-10), exactly as before this phase. Also bypasses the no-downgrade guard (D-10).
     *
     * INFERRED DECISION (not explicit in CONTEXT.md — D-05 describes the kill switch only
     * in terms of the automatic cascade, and D-10 only exempts force-refresh from the
     * no-downgrade guard, not from the kill switch): this method still respects
     * REGISTRADURIA_LIVE_ENABLED. Rationale: if live is fully disabled ops-wide, a
     * force-refresh cannot succeed regardless (there's nothing to bypass to — the whole
     * point of "straight to live" is that live is the only tier this action touches), so
     * gating it here is the only behavior that makes sense, not a new restriction on the
     * operator's override. Revisit if a future phase wants force-refresh to attempt live
     * even while the switch is off (e.g. for a one-off manual retry during an outage).
     *
     * Used by the secondary "Actualizar datos" button when an operator explicitly needs
     * to refresh already-resolved polling-place data (e.g. after a Registraduría data
     * correction).
     */
    public function forceRefreshFromRegistraduria(string $cedula): void
    {
        abort_unless(CampaignContext::isSuperAdmin(), 403);

        if (blank($cedula)) {
            Notification::make()
                ->title('Número de documento requerido')
                ->body('Ingresa el número de cédula antes de actualizar.')
                ->warning()
                ->send();

            return;
        }

        if (! config('services.registraduria.live_enabled')) {
            Notification::make()
                ->title('Servicio en vivo deshabilitado')
                ->body('La consulta en vivo a la Registraduría está deshabilitada temporalmente.')
                ->warning()
                ->send();

            return;
        }

        $voter = ($this instanceof EditRecord) ? $this->record : null;

        try {
            $sessionId = app(PollingPlaceResolver::class)->startLiveLookup(
                $cedula,
                voterId: $voter?->id,
                campaignId: CampaignContext::currentCampaignId(),
                resolvedVia: 'interactive',
            );
            $this->registraduriaSessionId = $sessionId;
            $this->registraduriaOpen = true;
            $this->registraduriaForceOverride = true;
        } catch (RegistraduriaLookupInProgressException $e) {
            // Also the cooldown for repeated "Actualizar datos" clicks (D-13): while a
            // claim is live for this cédula, another force-refresh click is turned away
            // here instead of paying for a second concurrent live lookup. See
            // .planning/debug/resolved/2captcha-duplicate-spend.md.
            Notification::make()
                ->title('Consulta en curso')
                ->body($e->getMessage())
                ->warning()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error al conectar con el servicio')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Triggered via window.Livewire.dispatch('registraduria-result', {data: {...}})
     * from Alpine.js inside the modal (which lives outside the Livewire component DOM).
     *
     * @param  array<string, string>  $data  Direct polling-place fields from the API
     */
    #[On('registraduria-result')]
    public function handleRegistraduriaResult(array $data): void
    {
        $this->registraduriaOpen = false;
        $this->registraduriaSessionId = '';
        $isExplicitOverride = $this->registraduriaForceOverride;
        $this->registraduriaForceOverride = false;

        // The browser's own Alpine.js polling loop only ever dispatches this event on a
        // definitive `status: done` — release the claim immediately rather than waiting
        // on CollectRegistraduriaLookupResult's next check, so a legitimate follow-up
        // lookup for the same cédula (e.g. after a "not found") isn't blocked for up to
        // LIVE_SESSION_WINDOW_MINUTES. See .planning/debug/resolved/2captcha-duplicate-spend.md.
        $lookupCedula = $this->data['document_number'] ?? null;
        if ($lookupCedula) {
            app(PollingPlaceResolver::class)->releaseLiveSession($lookupCedula);
        }

        // Normalise: accept either the raw data array or the full {status,data} wrapper
        if (isset($data['data']) && is_array($data['data'])) {
            $data = $data['data'];
        }

        if (empty($data) || empty($data['puesto_nombre'] ?? '')) {
            $errorMsg = $data['error'] ?? 'Error desconocido al consultar la Registraduría';
            Notification::make()
                ->title('Error al consultar Registraduría')
                ->body($errorMsg)
                ->danger()
                ->send();

            return;
        }

        $this->applyResolvedFields(new PollingPlaceResolutionResult(PollingPlaceSource::LIVE, $data), $isExplicitOverride);

        // Persist the result to the permanent Registraduría lookup table so the next lookup
        // for this cédula is instant (no 2captcha cost) — replaces the old 30-day cache write.
        // This is the ONLY interactive-flow place that writes here — a genuine live-sourced
        // result — which is what keeps openRegistraduriaBrowser()'s permanent-table branch
        // above always safely LIVE-sourced.
        $cedula = $this->data['document_number'] ?? null;
        if ($cedula) {
            app(PollingPlaceResolver::class)->persistPermanentLookup($cedula, $data, CampaignContext::currentCampaignId());
        }

        Notification::make()
            ->title('Puesto de votación encontrado')
            ->body("Puesto: {$data['puesto_nombre']} — Mesa: {$data['mesa_numero']}")
            ->success()
            ->send();
    }

    /**
     * Fill the Filament form's municipality/department/polling-place fields (unchanged
     * fillPollingPlaceFields logic below), then persist polling_place_source and
     * polling_place_resolved_at via the resolver, applying the no-downgrade guard
     * (SRC-02) unless $isExplicitOverride is true (D-10).
     *
     * CRITICAL (SRC-02): fillPollingPlaceFields() unconditionally overwrites the real,
     * save-bound identity fields in self::GUARDED_IDENTITY_FIELDS. If the resolver's
     * persist() call then blocks the write (guard triggered — e.g. a live-sourced voter
     * would be downgraded to db_reconstruction/snapshot), those already-mutated fields
     * are reverted back to their pre-lookup values here, so a subsequent ordinary Save
     * can never silently persist the blocked downgrade. Only the source/timestamp are
     * gated by persist() itself; the visible form fields are gated by this revert.
     */
    private function applyResolvedFields(PollingPlaceResolutionResult $result, bool $isExplicitOverride = false): void
    {
        $preLookupFields = collect(self::GUARDED_IDENTITY_FIELDS)
            ->mapWithKeys(fn (string $field): array => [$field => $this->data[$field] ?? null])
            ->all();

        $this->fillPollingPlaceFields($result->fields);

        $voter = ($this instanceof EditRecord) ? $this->record : null;

        // Prefer the resolver-resolved polling_place_id (DB/snapshot tiers already
        // queried the exact PollingPlace row); fall back to whatever
        // fillPollingPlaceFields() just resolved/created for a fresh live result.
        $pollingPlaceId = $result->pollingPlaceId ?? ($this->data['polling_place_id'] ?? null);
        $tableNumber = $result->tableNumber ?? ($this->data['polling_table_number'] ?? null);

        $toPersist = new PollingPlaceResolutionResult($result->source, $result->fields, $pollingPlaceId, $tableNumber);

        $applied = app(PollingPlaceResolver::class)->persist($voter, $toPersist, $isExplicitOverride, resolvedVia: 'interactive');

        if ($applied !== null) {
            $this->data['polling_place_source'] = $applied->source->value;
            $this->data['polling_place_resolved_at'] = now()->toDateTimeString();

            return;
        }

        // Guard blocked the write (SRC-02): revert every save-bound identity field
        // fillPollingPlaceFields() just overwrote, back to its pre-lookup value.
        foreach ($preLookupFields as $field => $value) {
            $this->data[$field] = $value;
        }
    }

    /**
     * Resolve municipality/department/polling-place and populate the Livewire form data bag.
     * PollingPlace resolution itself now delegates to PollingPlaceResolver::resolveOrCreatePollingPlace()
     * (previously a duplicated, buggy firstOrCreate() here — see
     * .planning/debug/resolved/registraduria-interactive-result-not-parsed.md); everything
     * else is unchanged from before D-09.
     *
     * @param  array<string, string>  $data
     */
    private function fillPollingPlaceFields(array $data): void
    {
        $municipality = Municipality::findByFuzzyName($data['municipio'] ?? '');

        $department = $municipality
            ? $municipality->department
            : Department::query()
                ->whereRaw('LOWER(name) = ?', [strtolower($data['departamento'] ?? '')])
                ->first();

        // Resolve-or-create the PollingPlace via the shared resolver (not a duplicated
        // firstOrCreate here) so this interactive path and the automated reconciliation
        // cascade (PollingPlaceResolver::resolveAutomated()) share identical
        // matching/creation behaviour for the always-blank puesto_codigo/zona_codigo a
        // real live Registraduría lookup returns — see
        // .planning/debug/resolved/registraduria-interactive-result-not-parsed.md.
        $pollingPlace = app(PollingPlaceResolver::class)->resolveOrCreatePollingPlace($data);

        if ($municipality) {
            $this->data['municipality_id'] = $municipality->id;
        }

        if ($department) {
            $this->data['department_id'] = $department->id;
        }

        if ($pollingPlace) {
            $this->data['polling_place_id'] = $pollingPlace->id;
        }

        $tableNumber = ltrim($data['mesa_numero'] ?? '', '0') ?: null;
        $this->data['polling_table_number'] = $tableNumber;

        if (filled($data['direccion'] ?? '')) {
            $this->data['address'] = $data['direccion'];
        }

        $zonaPart = filled($data['zona_codigo'] ?? '') ? "Zona {$data['zona_codigo']}" : null;
        $puestoPart = filled($data['puesto_nombre'] ?? '') ? "Puesto: {$data['puesto_nombre']}" : null;
        $detailedParts = array_filter([$zonaPart, $puestoPart]);
        if ($detailedParts) {
            $this->data['detailed_address'] = implode(' — ', $detailedParts);
        }

        // Enrich census: upsert the census_record for this cedula so the
        // registry accumulates real, verified Registraduria data with every lookup.
        $cedula = $this->data['document_number'] ?? null;
        $campaignId = CampaignContext::currentCampaignId();

        if ($cedula && $campaignId) {
            $firstName = trim($this->data['first_name'] ?? '');
            $lastName = trim($this->data['last_name'] ?? '');
            $fullName = trim("{$firstName} {$lastName}") ?: null;

            CensusRecord::updateOrCreate(
                [
                    'campaign_id' => $campaignId,
                    'document_number' => $cedula,
                ],
                [
                    'full_name' => $fullName,
                    'polling_station' => $data['puesto_nombre'] ?? null,
                    'table_number' => $tableNumber,
                    'municipality_code' => $municipality?->code,
                    'imported_at' => now(),
                ]
            );
        }
    }

    /**
     * Triggered via window.Livewire.dispatch('registraduria-close') from Alpine.js.
     */
    #[On('registraduria-close')]
    public function closeRegistraduriaBrowser(): void
    {
        $this->registraduriaOpen = false;
        $this->registraduriaSessionId = '';
    }
}

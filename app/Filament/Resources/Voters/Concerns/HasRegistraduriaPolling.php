<?php

namespace App\Filament\Resources\Voters\Concerns;

use App\Models\CensusRecord;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\PollingPlace;
use App\Services\CampaignContext;
use App\Services\RegistraduriaService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;

trait HasRegistraduriaPolling
{
    public string $registraduriaSessionId = '';

    public bool $registraduriaOpen = false;

    /** Cache TTL for Registraduría results: 30 days (polling places rarely change mid-campaign). */
    private const CACHE_TTL_DAYS = 30;

    private function registraduriaCacheKey(string $cedula): string
    {
        return "registraduria:cedula:{$cedula}";
    }

    /**
     * Called by the suffixAction on the document_number field.
     *
     * Lookup order (cheapest first):
     *   1. Redis cache      — 30-day TTL, instant
     *   2. DB reconstruction — census_records + polling_places, permanent, zero cost
     *   3. 2captcha request  — last resort, costs money
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

        // Layer 1: Redis cache (30-day TTL)
        $cached = Cache::get($this->registraduriaCacheKey($cedula));
        if ($cached) {
            $this->fillPollingPlaceFields($cached);
            Notification::make()
                ->title('Puesto de votación (desde caché)')
                ->body("Puesto: {$cached['puesto_nombre']} — Mesa: {$cached['mesa_numero']}")
                ->success()
                ->send();

            return;
        }

        // Layer 2: DB reconstruction — no cost, permanent
        $fromDb = $this->resolveFromDatabase($cedula);
        if ($fromDb) {
            $this->fillPollingPlaceFields($fromDb);
            // Re-warm Redis so next lookup is instant
            Cache::put($this->registraduriaCacheKey($cedula), $fromDb, now()->addDays(self::CACHE_TTL_DAYS));
            Notification::make()
                ->title('Puesto de votación (desde base de datos)')
                ->body("Puesto: {$fromDb['puesto_nombre']} — Mesa: {$fromDb['mesa_numero']}")
                ->info()
                ->send();

            return;
        }

        // Layer 3: 2captcha — only if we have no prior data anywhere
        try {
            $sessionId = app(RegistraduriaService::class)->startLookup($cedula);
            $this->registraduriaSessionId = $sessionId;
            $this->registraduriaOpen = true;
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error al conectar con el servicio')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Reconstruct Registraduría data from census_records + polling_places.
     * Used as a zero-cost fallback when Redis cache is cold.
     *
     * @return array<string, string>|null
     */
    private function resolveFromDatabase(string $cedula): ?array
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

        // Need at least municipality to be useful
        if (! $municipality && ! $pollingPlace) {
            return null;
        }

        $department = $pollingPlace?->department
            ?? $pollingPlace?->municipality?->department
            ?? $municipality?->department;

        return [
            'puesto_nombre' => $census->polling_station,
            'puesto_codigo' => $pollingPlace?->place_code ?? '',
            'zona_codigo' => $pollingPlace?->zone_code ?? '',
            'mesa_numero' => (string) ($census->table_number ?? ''),
            'departamento' => $department?->name ?? '',
            'municipio' => $municipality?->name ?? $pollingPlace?->municipality?->name ?? '',
            'direccion' => $pollingPlace?->address ?? '',
        ];
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

        $this->fillPollingPlaceFields($data);

        // Cache the result so the next lookup for this cedula is instant (no 2captcha cost)
        $cedula = $this->data['document_number'] ?? null;
        if ($cedula) {
            Cache::put(
                $this->registraduriaCacheKey($cedula),
                $data,
                now()->addDays(self::CACHE_TTL_DAYS)
            );
        }

        Notification::make()
            ->title('Puesto de votación encontrado')
            ->body("Puesto: {$data['puesto_nombre']} — Mesa: {$data['mesa_numero']}")
            ->success()
            ->send();
    }

    /**
     * Resolve municipality/department/polling-place and populate the Livewire form data bag.
     *
     * @param  array<string, string>  $data
     */
    private function fillPollingPlaceFields(array $data): void
    {
        $municipality = Municipality::query()
            ->whereRaw('LOWER(name) = ?', [strtolower($data['municipio'] ?? '')])
            ->first();

        $department = $municipality
            ? $municipality->department
            : Department::query()
                ->whereRaw('LOWER(name) = ?', [strtolower($data['departamento'] ?? '')])
                ->first();

        $placeCode = $data['puesto_codigo'] ?? substr($data['puesto_nombre'] ?? '', 0, 2);
        $pollingPlace = null;

        if ($municipality) {
            $pollingPlace = PollingPlace::firstOrCreate(
                [
                    'municipality_id' => $municipality->id,
                    'zone_code' => $data['zona_codigo'] ?? null,
                    'place_code' => $placeCode,
                ],
                [
                    'name' => $data['puesto_nombre'] ?? 'Desconocido',
                    'address' => $data['direccion'] ?? null,
                    'department_id' => $department?->id,
                    'max_tables' => 0,
                ]
            );

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

<?php

namespace App\Filament\Resources\Voters\Concerns;

use App\Models\Department;
use App\Models\Municipality;
use App\Models\PollingPlace;
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
     * Checks cache first — if hit, fills form immediately at zero cost.
     * Otherwise starts the 2captcha lookup and opens the progress modal.
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

        // Check cache — avoids a 2captcha call if we've seen this cedula before
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
                ]
            );

            $this->data['municipality_id'] = $municipality->id;
        }

        if ($pollingPlace) {
            $this->data['polling_place_id'] = $pollingPlace->id;
        }

        $this->data['polling_table_number'] = ltrim($data['mesa_numero'] ?? '', '0') ?: null;
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

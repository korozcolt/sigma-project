<?php

namespace App\Filament\Resources\Voters\Concerns;

use App\Models\Department;
use App\Models\Municipality;
use App\Models\PollingPlace;
use App\Services\RegistraduriaService;
use Filament\Notifications\Notification;

trait HasRegistraduriaPolling
{
    public string $registraduriaSessionId = '';

    public bool $registraduriaOpen = false;

    /**
     * Called by the suffixAction on the document_number field.
     * Starts the Python lookup, opens the screenshot modal.
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

        try {
            $service = new RegistraduriaService;
            $sessionId = $service->startLookup($cedula);

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
     * Called from Alpine.js via $wire.handleRegistraduriaResult(data)
     * when the screenshot modal detects status=done.
     *
     * @param  array{status: string, data: array<string, string>|null, error: string|null}  $result
     */
    public function handleRegistraduriaResult(array $result): void
    {
        $this->registraduriaOpen = false;
        $this->registraduriaSessionId = '';

        if ($result['status'] === 'done' && isset($result['data'])) {
            $data = $result['data'];

            $municipality = Municipality::query()
                ->whereRaw('LOWER(name) = ?', [strtolower($data['municipio'] ?? '')])
                ->first();

            $department = null;

            if ($municipality) {
                $department = $municipality->department;
            } else {
                $department = Department::query()
                    ->whereRaw('LOWER(name) = ?', [strtolower($data['departamento'] ?? '')])
                    ->first();
            }

            $placeCode = $data['puesto_codigo'] ?? substr($data['puesto_nombre'] ?? '', 0, 2);
            $pollingPlace = null;

            if ($municipality) {
                $pollingPlace = PollingPlace::query()
                    ->where('municipality_id', $municipality->id)
                    ->where('zone_code', $data['zona_codigo'] ?? null)
                    ->where('place_code', $placeCode)
                    ->first();

                if (! $pollingPlace) {
                    $pollingPlace = PollingPlace::create([
                        'municipality_id' => $municipality->id,
                        'department_id' => $department?->id,
                        'zone_code' => $data['zona_codigo'] ?? null,
                        'place_code' => $placeCode,
                        'name' => $data['puesto_nombre'] ?? 'Desconocido',
                        'address' => $data['direccion'] ?? null,
                    ]);
                }
            }

            if ($municipality) {
                $this->data['municipality_id'] = $municipality->id;
            }

            if ($pollingPlace) {
                $this->data['polling_place_id'] = $pollingPlace->id;
            }

            $this->data['polling_table_number'] = ltrim($data['mesa_numero'] ?? '', '0') ?: null;

            Notification::make()
                ->title('Puesto de votación encontrado')
                ->body("Puesto: {$data['puesto_nombre']} — Mesa: {$data['mesa_numero']}")
                ->success()
                ->send();
        }

        if ($result['status'] === 'error') {
            Notification::make()
                ->title('Error al consultar Registraduría')
                ->body($result['error'] ?? 'Error desconocido')
                ->danger()
                ->send();
        }
    }

    /**
     * Called from Alpine.js close button via $wire.closeRegistraduriaBrowser().
     */
    public function closeRegistraduriaBrowser(): void
    {
        $this->registraduriaOpen = false;
        $this->registraduriaSessionId = '';
    }
}

<?php

namespace App\Filament\Resources\Voters\Concerns;

use App\Models\Department;
use App\Models\Municipality;
use App\Models\PollingPlace;
use App\Services\RegistraduriaService;
use Filament\Notifications\Notification;

trait HasRegistraduriaPolling
{
    /**
     * Poll the Registraduria service for a lookup result and fill form fields on success.
     *
     * Called by Alpine.js every 2 seconds via $wire.call().
     * Returns the result array when status is "done" or "error", null otherwise (keep polling).
     *
     * @return array{status: string, data: array<string, string>|null, error: string|null}|null
     */
    public function pollRegistraduria(string $sessionId): ?array
    {
        $service = new RegistraduriaService;
        $result = $service->getResult($sessionId);

        if ($result['status'] === 'done' && isset($result['data'])) {
            $data = $result['data'];

            // Resolve municipality by name (case-insensitive match)
            $municipality = Municipality::query()
                ->whereRaw('LOWER(name) = ?', [strtolower($data['municipio'] ?? '')])
                ->first();

            // Resolve department by name if municipality not found directly
            $department = null;

            if ($municipality) {
                $department = $municipality->department;
            } else {
                $department = Department::query()
                    ->whereRaw('LOWER(name) = ?', [strtolower($data['departamento'] ?? '')])
                    ->first();
            }

            // Find or create PollingPlace
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

            // Set form fields via Livewire data bag
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

            return $result;
        }

        if ($result['status'] === 'error') {
            Notification::make()
                ->title('Error al consultar Registraduría')
                ->body($result['error'] ?? 'Error desconocido')
                ->danger()
                ->send();

            return $result;
        }

        // Still pending or waiting_captcha — return null to keep polling
        return null;
    }
}

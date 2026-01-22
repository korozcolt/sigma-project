<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Municipality;
use App\Models\PollingPlace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PollingPlaceSeeder extends Seeder
{
    public function run(): void
    {
        $dataset = env('DIVIPOLE_DATASET', 'nacional');
        $path = match ($dataset) {
            'sucre' => database_path('external-data/divipole-sucre.json'),
            default => database_path('external-data/divipole-nacional.json'),
        };

        if (! file_exists($path)) {
            $this->command?->warn('No se encontró el archivo de Divipole: '.$path);

            return;
        }

        if (Department::count() === 0 || Municipality::count() === 0) {
            $this->command?->warn('Primero debes importar departamentos y municipios antes de cargar Divipole.');

            return;
        }

        $this->command?->info('🗳️  Importando puestos de votación (Divipole): '.$dataset);

        $raw = file_get_contents($path);
        $records = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);

        $departmentsByName = Department::query()
            ->get(['id', 'name'])
            ->keyBy(fn (Department $department) => $this->normalizeName($department->name));

        $municipalities = Municipality::query()
            ->with('department:id,name')
            ->get(['id', 'department_id', 'name']);

        $municipalitiesByDepartment = [];
        foreach ($municipalities as $municipality) {
            $departmentName = $municipality->department?->name ?? '';
            $departmentKey = $this->normalizeName($departmentName);
            $municipalitiesByDepartment[$departmentKey][$this->normalizeName($municipality->name)] = $municipality;
        }

        $batch = [];
        $missingDepartments = 0;
        $missingMunicipalities = 0;
        $imported = 0;

        foreach ($records as $record) {
            $departmentKey = $this->normalizeName((string) ($record['departamento'] ?? ''));
            $municipalityKey = $this->normalizeMunicipalityName((string) ($record['municipio'] ?? ''));

            $department = $departmentsByName[$departmentKey] ?? null;
            if (! $department) {
                $missingDepartments++;
                continue;
            }

            $municipality = $municipalitiesByDepartment[$departmentKey][$municipalityKey] ?? null;
            if (! $municipality) {
                $missingMunicipalities++;
                continue;
            }

            $batch[] = [
                'department_id' => $department->id,
                'municipality_id' => $municipality->id,
                'dane_department_code' => (int) ($record['dd'] ?? 0),
                'dane_municipality_code' => (int) ($record['mm'] ?? 0),
                'zone_code' => (int) ($record['zz'] ?? 0),
                'place_code' => (int) ($record['pp'] ?? 0),
                'name' => Str::of((string) ($record['puesto'] ?? ''))->squish()->toString(),
                'address' => filled($record['direccion'] ?? null)
                    ? Str::of((string) $record['direccion'])->replace("\r\n", ' ')->squish()->toString()
                    : null,
                'commune' => filled($record['comuna'] ?? null)
                    ? Str::of((string) $record['comuna'])->replace("\r\n", ' ')->squish()->toString()
                    : null,
                'max_tables' => (int) ($record['mesas'] ?? 0),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $imported++;

            if (count($batch) >= 1000) {
                $this->flush($batch);
                $batch = [];
            }
        }

        $this->flush($batch);

        $this->command?->info('✅ Puestos importados: '.$imported);
        if ($missingDepartments > 0 || $missingMunicipalities > 0) {
            $this->command?->warn('⚠️  Registros ignorados por coincidencias faltantes (departamentos: '.$missingDepartments.', municipios: '.$missingMunicipalities.').');
        }
    }

    private function flush(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        PollingPlace::query()->upsert(
            $rows,
            ['dane_department_code', 'dane_municipality_code', 'zone_code', 'place_code'],
            ['department_id', 'municipality_id', 'name', 'address', 'commune', 'max_tables', 'updated_at'],
        );
    }

    private function normalizeName(string $name): string
    {
        $clean = preg_replace('/\\s*\\([^)]*\\)\\s*/', ' ', $name) ?? $name;

        return Str::of($clean)->ascii()->upper()->squish()->toString();
    }

    private function normalizeMunicipalityName(string $name): string
    {
        $normalized = $this->normalizeName($name);

        $aliases = [
            'SINCE' => 'SAN LUIS DE SINCE',
            'TOLU' => 'SANTIAGO DE TOLU',
            'TOLUVIEJO' => 'TOLU VIEJO',
        ];

        return $aliases[$normalized] ?? $normalized;
    }
}

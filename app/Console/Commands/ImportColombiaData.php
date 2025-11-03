<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\Municipality;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ImportColombiaData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'colombia:import {--fresh : Truncate tables before importing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import departments and municipalities from api-colombia.com';

    private const API_BASE = 'https://api-colombia.com/api/v1';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🇨🇴 Importando datos de Colombia desde API...');
        $this->newLine();

        if ($this->option('fresh')) {
            $this->warn('⚠️  Limpiando tablas existentes...');
            Municipality::query()->delete();
            Department::query()->delete();
            $this->info('✅ Tablas limpiadas');
            $this->newLine();
        }

        $this->importDepartments();

        $this->newLine();
        $this->info('🎉 Importación completada exitosamente!');

        return self::SUCCESS;
    }

    private function importDepartments(): void
    {
        $this->info('📍 Importando departamentos...');

        $response = Http::get(self::API_BASE.'/Department');

        if (! $response->successful()) {
            $this->error('❌ Error al obtener departamentos de la API');

            return;
        }

        $departments = $response->json();
        $this->info('   Encontrados: '.count($departments).' departamentos');

        $bar = $this->output->createProgressBar(count($departments));
        $bar->start();

        foreach ($departments as $departmentData) {
            $department = Department::updateOrCreate(
                ['code' => (string) $departmentData['id']],
                ['name' => $departmentData['name']]
            );

            // Importar municipios de este departamento
            $this->importMunicipalitiesForDepartment($department, $departmentData['id']);

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('✅ Departamentos importados: '.Department::count());
    }

    private function importMunicipalitiesForDepartment(Department $department, int $departmentApiId): void
    {
        $response = Http::get(self::API_BASE."/Department/{$departmentApiId}/cities");

        if (! $response->successful()) {
            return;
        }

        $cities = $response->json();

        foreach ($cities as $cityData) {
            Municipality::updateOrCreate(
                [
                    'department_id' => $department->id,
                    'code' => (string) $cityData['id'],
                ],
                ['name' => $cityData['name']]
            );
        }
    }
}

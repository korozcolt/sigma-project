<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🇨🇴 Importando departamentos y municipios desde API de Colombia...');

        // Llamar al comando de importación
        Artisan::call('colombia:import');

        $this->command->info('✅ Importación completada');
    }
}

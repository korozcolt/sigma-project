<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class MunicipalitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('ℹ️  Los municipios se importan automáticamente con el DepartmentSeeder');
        $this->command->info('   Usa: php artisan colombia:import');
    }
}

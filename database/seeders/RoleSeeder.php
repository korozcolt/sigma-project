<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear todos los roles del sistema
        foreach (UserRole::cases() as $role) {
            Role::firstOrCreate(
                ['name' => $role->value],
                ['guard_name' => 'web']
            );
        }

        $this->command->info('✅ Roles creados exitosamente:');
        foreach (UserRole::cases() as $role) {
            $this->command->line("   - {$role->getLabel()} ({$role->value})");
        }
    }
}

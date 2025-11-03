<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('👤 Creando usuario Super Admin...');

        $user = User::updateOrCreate(
            ['email' => 'ing.korozco@gmail.com'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('Admin123'),
                'email_verified_at' => now(),
            ]
        );

        // Asignar el rol de Super Admin
        $user->assignRole(UserRole::SUPER_ADMIN->value);

        $this->command->info('✅ Usuario Super Admin creado: '.$user->email);
    }
}

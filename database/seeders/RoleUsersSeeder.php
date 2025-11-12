<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\Neighborhood;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class RoleUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $password = Hash::make('password'); // Misma contraseña para todos

        // Asegurar que los roles existen
        $roles = ['super_admin', 'admin_campaign', 'coordinator', 'leader', 'reviewer'];
        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        // Obtener o crear datos necesarios
        // Primero, obtener o crear un usuario base para created_by
        $baseUser = User::firstOrCreate(
            ['email' => 'system@sistema.com'],
            [
                'name' => 'Sistema',
                'password' => $password,
                'email_verified_at' => now(),
            ]
        );

        $campaign = Campaign::firstOrCreate(
            ['name' => 'Campaña Principal'],
            [
                'description' => 'Campaña de prueba para el sistema',
                'candidate_name' => 'Candidato Principal',
                'start_date' => now(),
                'end_date' => now()->addMonths(6),
                'election_date' => now()->addMonths(6),
                'status' => 'active',
                'scope' => 'municipal',
                'created_by' => $baseUser->id,
            ]
        );

        $department = Department::firstOrCreate(
            ['name' => 'Sucre'],
            ['code' => 'SUC']
        );

        $municipality = Municipality::firstOrCreate(
            ['name' => 'Sincelejo', 'department_id' => $department->id],
            ['code' => 'SIN']
        );

        $neighborhood = Neighborhood::firstOrCreate(
            ['name' => 'Centro', 'municipality_id' => $municipality->id]
        );

        // 1. Super Admin
        $superAdmin = User::firstOrCreate(
            ['email' => 'ing.korozco+superadmin@gmail.com'],
            [
                'name' => 'Super Administrador',
                'password' => $password,
                'email_verified_at' => now(),
            ]
        );
        $superAdmin->syncRoles(['super_admin']);
        $superAdmin->campaigns()->syncWithoutDetaching([$campaign->id]);

        $this->command->info('✓ Super Admin creado: ing.korozco+superadmin@gmail.com');

        // 2. Administrador de Campaña
        $adminCampaign = User::firstOrCreate(
            ['email' => 'ing.korozco+admin@gmail.com'],
            [
                'name' => 'Administrador de Campaña',
                'password' => $password,
                'email_verified_at' => now(),
            ]
        );
        $adminCampaign->syncRoles(['admin_campaign']);
        $adminCampaign->campaigns()->syncWithoutDetaching([$campaign->id]);

        $this->command->info('✓ Admin de Campaña creado: ing.korozco+admin@gmail.com');

        // 3. Coordinador
        $coordinator = User::firstOrCreate(
            ['email' => 'ing.korozco+coordinador@gmail.com'],
            [
                'name' => 'Coordinador Principal',
                'password' => $password,
                'email_verified_at' => now(),
                'municipality_id' => $municipality->id,
                'neighborhood_id' => $neighborhood->id,
            ]
        );
        $coordinator->syncRoles(['coordinator']);
        $coordinator->campaigns()->syncWithoutDetaching([$campaign->id]);

        $this->command->info('✓ Coordinador creado: ing.korozco+coordinador@gmail.com');

        // 4. Líder
        $leader = User::firstOrCreate(
            ['email' => 'ing.korozco+lider@gmail.com'],
            [
                'name' => 'Líder de Campo',
                'password' => $password,
                'email_verified_at' => now(),
                'municipality_id' => $municipality->id,
                'neighborhood_id' => $neighborhood->id,
            ]
        );
        $leader->syncRoles(['leader']);
        $leader->campaigns()->syncWithoutDetaching([$campaign->id]);

        $this->command->info('✓ Líder creado: ing.korozco+lider@gmail.com');

        // 5. Reviewer (adicional)
        $reviewer = User::firstOrCreate(
            ['email' => 'ing.korozco+revisor@gmail.com'],
            [
                'name' => 'Revisor de Datos',
                'password' => $password,
                'email_verified_at' => now(),
            ]
        );
        $reviewer->syncRoles(['reviewer']);
        $reviewer->campaigns()->syncWithoutDetaching([$campaign->id]);

        $this->command->info('✓ Revisor creado: ing.korozco+revisor@gmail.com');

        $this->command->newLine();
        $this->command->info('=================================');
        $this->command->info('Usuarios de prueba creados:');
        $this->command->info('=================================');
        $this->command->info('Super Admin:     ing.korozco+superadmin@gmail.com');
        $this->command->info('Admin Campaña:   ing.korozco+admin@gmail.com');
        $this->command->info('Coordinador:     ing.korozco+coordinador@gmail.com');
        $this->command->info('Líder:           ing.korozco+lider@gmail.com');
        $this->command->info('Revisor:         ing.korozco+revisor@gmail.com');
        $this->command->newLine();
        $this->command->info('Contraseña para todos: password');
        $this->command->info('=================================');
    }
}

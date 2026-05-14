<?php

namespace Database\Seeders;

use App\Enums\CampaignScope;
use App\Enums\CampaignStatus;
use App\Enums\ElectionType;
use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AlcaldiaInstancia2027Seeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🏛️  Configurando instancia Alcaldía Sincelejo 2027...');

        $sucre = Department::where('name', 'like', '%Sucre%')->firstOrFail();
        $sincelejo = Municipality::where('name', 'like', '%Sincelejo%')
            ->where('department_id', $sucre->id)
            ->firstOrFail();

        $this->command->info("   Departamento: {$sucre->name}");
        $this->command->info("   Municipio: {$sincelejo->name}");

        $admin = User::where('email', 'ing.korozco@gmail.com')->firstOrFail();

        $campaign = Campaign::updateOrCreate(
            ['name' => 'Alcaldía Sincelejo 2027'],
            [
                'election_type' => ElectionType::MAYOR,
                'scope' => CampaignScope::Municipal,
                'candidate_name' => 'Aldemar Alfaro',
                'description' => 'Campaña para la Alcaldía de Sincelejo, Sucre - Elecciones 2027',
                'status' => CampaignStatus::ACTIVE,
                'department_id' => $sucre->id,
                'municipality_id' => $sincelejo->id,
                'election_date' => '2027-10-31',
                'start_date' => now()->toDateString(),
                'created_by' => $admin->id,
            ]
        );

        $this->command->info("✅ Campaña creada: {$campaign->name}");

        $aldemar = User::updateOrCreate(
            ['email' => 'aldemar@sincelejo2027.sigma'],
            [
                'name' => 'Aldemar Alfaro',
                'password' => Hash::make('Aldemar2027!'),
                'email_verified_at' => now(),
            ]
        );

        $aldemar->assignRole(UserRole::ADMIN_CAMPAIGN->value);

        if (! $campaign->users()->where('user_id', $aldemar->id)->exists()) {
            $campaign->users()->attach($aldemar->id);
        }

        $this->command->info("✅ Usuario líder creado: {$aldemar->email}");
        $this->command->info('');
        $this->command->line('   Credenciales Aldemar:');
        $this->command->line('   Email:    aldemar@sincelejo2027.sigma');
        $this->command->line('   Password: Aldemar2027!');
    }
}

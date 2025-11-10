<?php

namespace App\Console\Commands;

use App\Enums\VoterStatus;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Console\Command;

class CreateVoterRecordsForUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:create-voter-records
                            {--force : Forzar creación incluso si ya existe}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crear registros de votantes para users existentes que no tienen uno';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Buscando users sin registro de votante...');

        // Obtener users que:
        // 1. Tienen document_number y municipality_id
        // 2. Están asignados a al menos una campaña
        // 3. NO tienen registro de votante (o forzar si --force)
        $query = User::query()
            ->whereNotNull('document_number')
            ->whereNotNull('municipality_id')
            ->whereHas('campaigns');

        if (! $this->option('force')) {
            $query->doesntHave('voter');
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            $this->info('No hay users para procesar.');

            return self::SUCCESS;
        }

        $this->info("Encontrados {$users->count()} users para procesar.");

        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        $created = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($users as $user) {
            try {
                // Si ya tiene votante y no es --force, skip
                if ($user->voter && ! $this->option('force')) {
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                // Obtener la primera campaña asignada
                $campaign = $user->campaigns()->first();

                if (! $campaign) {
                    $this->warn("User {$user->id} no tiene campaña asignada.");
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                // Si existe y es --force, actualizar
                if ($user->voter && $this->option('force')) {
                    $user->voter->update([
                        'campaign_id' => $campaign->id,
                        'municipality_id' => $user->municipality_id,
                        'neighborhood_id' => $user->neighborhood_id,
                        'phone' => $user->phone,
                        'secondary_phone' => $user->secondary_phone,
                        'address' => $user->address,
                        'birth_date' => $user->birth_date,
                    ]);
                    $created++;
                    $bar->advance();

                    continue;
                }

                // Separar nombre en first_name y last_name
                $nameParts = explode(' ', $user->name, 2);
                $firstName = $nameParts[0];
                $lastName = $nameParts[1] ?? '';

                // Crear registro de votante
                Voter::create([
                    'user_id' => $user->id,
                    'campaign_id' => $campaign->id,
                    'document_number' => $user->document_number,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'birth_date' => $user->birth_date,
                    'phone' => $user->phone ?? '0000000000',
                    'secondary_phone' => $user->secondary_phone,
                    'municipality_id' => $user->municipality_id,
                    'neighborhood_id' => $user->neighborhood_id,
                    'address' => $user->address,
                    'registered_by' => $user->id, // Se auto-registra
                    'status' => VoterStatus::CONFIRMED,
                ]);

                $created++;
            } catch (\Exception $e) {
                $this->error("Error procesando user {$user->id}: {$e->getMessage()}");
                $errors++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Proceso completado:');
        $this->table(
            ['Resultado', 'Cantidad'],
            [
                ['Creados', $created],
                ['Omitidos', $skipped],
                ['Errores', $errors],
            ]
        );

        return self::SUCCESS;
    }
}

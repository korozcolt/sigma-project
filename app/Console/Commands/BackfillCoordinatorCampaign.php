<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;
use Throwable;

class BackfillCoordinatorCampaign extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'campaigns:backfill-orphan-memberships
                            {campaign : ID de la campaña a la que se asignarán los coordinadores/líderes sin campaña}
                            {--apply : Persistir los cambios. Sin esta opción solo se muestra un dry-run.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Adjunta a campaign_user los coordinadores y líderes creados sin campaña asignada (bug coordinator-campaign-not-attached)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $campaign = Campaign::query()->find((int) $this->argument('campaign'));

        if (! $campaign) {
            $this->error("No existe una campaña con id {$this->argument('campaign')}.");

            return self::FAILURE;
        }

        $orphans = User::withoutGlobalScopes()
            ->role([UserRole::COORDINATOR->value, UserRole::LEADER->value])
            ->doesntHave('campaigns')
            ->with('roles')
            ->get();

        if ($orphans->isEmpty()) {
            $this->info('No se encontraron coordinadores ni líderes sin campaña asignada.');

            return self::SUCCESS;
        }

        $this->info("Campaña destino: {$campaign->name} (ID {$campaign->id})");
        $this->table(
            ['ID', 'Nombre', 'Correo', 'Roles'],
            $orphans->map(fn (User $user): array => [
                $user->id,
                $user->name,
                $user->email,
                $user->roles->pluck('name')->implode(', '),
            ])->all()
        );

        if (! $this->option('apply')) {
            $this->warn("Dry-run: {$orphans->count()} usuario(s) serían adjuntados a la campaña #{$campaign->id}. Ejecuta con --apply para persistir.");

            return self::SUCCESS;
        }

        if (! $this->confirm("¿Confirmas adjuntar {$orphans->count()} usuario(s) a la campaña '{$campaign->name}'?")) {
            $this->warn('Operación cancelada.');

            return self::SUCCESS;
        }

        $attached = 0;
        $errors = 0;

        foreach ($orphans as $user) {
            try {
                $roleName = $user->roles->contains('name', UserRole::COORDINATOR->value)
                    ? UserRole::COORDINATOR->value
                    : UserRole::LEADER->value;

                $roleId = Role::where('name', $roleName)->value('id');

                $user->campaigns()->attach($campaign->id, [
                    'role_id' => $roleId,
                    'assigned_at' => now(),
                ]);

                $attached++;
            } catch (Throwable $exception) {
                $this->error("Error adjuntando usuario {$user->id}: {$exception->getMessage()}");
                $errors++;
            }
        }

        $this->newLine();
        $this->info('Backfill completado.');
        $this->table(
            ['Resultado', 'Cantidad'],
            [
                ['Adjuntados', $attached],
                ['Errores', $errors],
            ]
        );

        return self::SUCCESS;
    }
}

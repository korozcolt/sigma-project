<?php

namespace App\Console\Commands;

use App\Imports\NeighborhoodsImport;
use App\Models\Municipality;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;

class ImportNeighborhoods extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'neighborhoods:import
                            {file : Ruta al archivo Excel}
                            {--municipality= : ID del municipio}
                            {--municipality-name= : Nombre del municipio}
                            {--campaign= : ID de la campaña (opcional, null para barrios globales)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importar barrios desde un archivo Excel';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $filePath = $this->argument('file');

        if (! file_exists($filePath)) {
            $this->error("El archivo {$filePath} no existe.");

            return self::FAILURE;
        }

        $municipality = $this->getMunicipality();

        if (! $municipality) {
            return self::FAILURE;
        }

        $campaignId = $this->option('campaign');

        $this->info("Importando barrios para {$municipality->name}...");

        try {
            Excel::import(
                new NeighborhoodsImport($municipality->id, $campaignId),
                $filePath
            );

            $this->info('✅ Barrios importados exitosamente!');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Error al importar: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    private function getMunicipality(): ?Municipality
    {
        if ($municipalityId = $this->option('municipality')) {
            return Municipality::find($municipalityId);
        }

        if ($municipalityName = $this->option('municipality-name')) {
            return Municipality::where('name', 'like', "%{$municipalityName}%")->first();
        }

        $this->error('Debe especificar --municipality o --municipality-name');

        return null;
    }
}

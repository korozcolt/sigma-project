<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\NationalIdentityRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;

class ImportIdentityDirectory extends Command
{
    /**
     * @var string
     */
    protected $signature = 'identity:import-directory
                            {--path= : Ruta al CSV nacional de identidades (cedula, nombre1, nombre2, apellido1, apellido2)}
                            {--dry-run : Solo reporta lo que se importaría, sin escribir en la base de datos}';

    /**
     * @var string
     */
    protected $description = 'Importar el directorio nacional de identidades (cedula -> nombre) a national_identity_records, descartando cedulas con datos en conflicto';

    public function handle(): int
    {
        $path = $this->option('path');

        if (! $path || ! file_exists($path)) {
            $this->error("El archivo {$path} no existe. Debe indicarse con --path=");

            return self::FAILURE;
        }

        $isDryRun = (bool) $this->option('dry-run');

        // Pass 1: detect cedulas whose rows disagree on name data.
        $firstTuple = [];
        $conflictCedulas = [];
        $totalRows = 0;
        $blankRowsSkipped = 0;

        $this->readRows($path)->each(function (array $row) use (&$firstTuple, &$conflictCedulas, &$totalRows, &$blankRowsSkipped): void {
            $totalRows++;

            [$cedula, $nombre1, $nombre2, $apellido1, $apellido2] = $row;

            if ($cedula === '' || $nombre1 === '' || $apellido1 === '') {
                $blankRowsSkipped++;

                return;
            }

            $tuple = "{$nombre1}|{$nombre2}|{$apellido1}|{$apellido2}";

            if (! array_key_exists($cedula, $firstTuple)) {
                $firstTuple[$cedula] = $tuple;

                return;
            }

            if ($firstTuple[$cedula] !== $tuple) {
                $conflictCedulas[$cedula] = true;
            }
        });

        // Pass 2: split rows into the conflicts report vs. the clean upsert buffer.
        $conflictRows = [];
        $buffer = [];
        $upsertedRows = 0;

        $this->readRows($path)->each(function (array $row) use (&$conflictCedulas, &$conflictRows, &$buffer, &$upsertedRows, $isDryRun): void {
            [$cedula, $nombre1, $nombre2, $apellido1, $apellido2] = $row;

            if ($cedula === '' || $nombre1 === '' || $apellido1 === '') {
                return;
            }

            if (isset($conflictCedulas[$cedula])) {
                $conflictRows[] = [$cedula, $nombre1, $nombre2, $apellido1, $apellido2];

                return;
            }

            $buffer[$cedula] = [
                'cedula' => $cedula,
                'nombre1' => $nombre1,
                'nombre2' => $nombre2 !== '' ? $nombre2 : null,
                'apellido1' => $apellido1,
                'apellido2' => $apellido2 !== '' ? $apellido2 : null,
            ];

            $upsertedRows++;

            if (count($buffer) >= 1000) {
                if (! $isDryRun) {
                    NationalIdentityRecord::upsert(array_values($buffer), ['cedula'], ['nombre1', 'nombre2', 'apellido1', 'apellido2']);
                }
                $buffer = [];
            }
        });

        if (! empty($buffer) && ! $isDryRun) {
            NationalIdentityRecord::upsert(array_values($buffer), ['cedula'], ['nombre1', 'nombre2', 'apellido1', 'apellido2']);
        }

        $reportPath = null;

        if (! empty($conflictRows)) {
            $reportPath = 'identity-import-conflicts-'.now()->format('Ymd_His').'.csv';

            $handle = fopen('php://temp', 'r+');
            fputcsv($handle, ['cedula', 'nombre1', 'nombre2', 'apellido1', 'apellido2'], ',', '"', '\\');
            foreach ($conflictRows as $row) {
                fputcsv($handle, $row, ',', '"', '\\');
            }
            rewind($handle);
            $csv = stream_get_contents($handle);
            fclose($handle);

            Storage::disk('local')->put($reportPath, $csv);
        }

        if ($isDryRun) {
            $this->warn('[DRY-RUN] No se escribió nada en la base de datos.');
        }

        $this->info("Filas leídas: {$totalRows}.");
        $this->warn("Filas vacías omitidas (cédula/nombre1/apellido1 en blanco): {$blankRowsSkipped}.");
        $this->warn('Cédulas en conflicto descartadas: '.count($conflictCedulas).($reportPath ? " (reporte: storage/app/{$reportPath})" : ' (sin conflictos).'));
        $this->info(($isDryRun ? 'Registros que se importarían: ' : 'Registros importados/actualizados: ')."{$upsertedRows}.");

        return self::SUCCESS;
    }

    /**
     * @return LazyCollection<int, array{0:string,1:string,2:string,3:string,4:string}>
     */
    private function readRows(string $path): LazyCollection
    {
        return LazyCollection::make(function () use ($path) {
            $handle = fopen($path, 'rb');
            fgets($handle); // skip header row
            while (($line = fgets($handle)) !== false) {
                $columns = str_getcsv(rtrim($line, "\r\n"), ',', '"', '');
                yield [
                    trim($columns[0] ?? ''),
                    trim($columns[1] ?? ''),
                    trim($columns[2] ?? ''),
                    trim($columns[3] ?? ''),
                    trim($columns[4] ?? ''),
                ];
            }
            fclose($handle);
        });
    }
}

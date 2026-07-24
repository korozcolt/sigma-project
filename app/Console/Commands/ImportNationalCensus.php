<?php

namespace App\Console\Commands;

use App\Models\NationalCensusRecord;
use App\Models\PollingPlace;
use Illuminate\Console\Command;
use Illuminate\Support\LazyCollection;

class ImportNationalCensus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'census:import-national
                            {--path= : Ruta alterna al CSV (por defecto, el snapshot nacional incluido)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importar el snapshot nacional del censo electoral a national_census_records';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $path = $this->option('path') ?: database_path('external-data/censo_decoded_202310210734.csv');

        if (! file_exists($path)) {
            $this->error("El archivo {$path} no existe.");

            return self::FAILURE;
        }

        $placeMap = PollingPlace::query()
            ->get(['id', 'dane_department_code', 'dane_municipality_code', 'zone_code', 'place_code'])
            ->keyBy(fn (PollingPlace $place) => "{$place->dane_department_code}-{$place->dane_municipality_code}-{$place->zone_code}-{$place->place_code}")
            ->map->id;

        $total = 0;
        $unmatched = 0;

        LazyCollection::make(function () use ($path) {
            $handle = fopen($path, 'rb');
            fgets($handle); // skip header row
            while (($line = fgets($handle)) !== false) {
                yield mb_convert_encoding($line, 'UTF-8', 'ISO-8859-1');
            }
            fclose($handle);
        })
            ->map(fn (string $line) => str_getcsv(rtrim($line, "\r\n"), ';', '"', ''))
            ->filter(fn (array $columns) => count($columns) >= 13 && $columns[2] !== '')
            ->chunk(1000)
            ->each(function (LazyCollection $chunk) use ($placeMap, &$total, &$unmatched) {
                $rows = [];

                foreach ($chunk as $columns) {
                    $key = ((int) $columns[3]).'-'.((int) $columns[6]).'-'.((int) $columns[8]).'-'.((int) $columns[10]);
                    $pollingPlaceId = $placeMap[$key] ?? null;

                    if ($pollingPlaceId === null) {
                        $unmatched++;
                    }

                    $total++;

                    $rows[$columns[2]] = [
                        'document_number' => $columns[2],
                        'polling_place_id' => $pollingPlaceId,
                        'dane_department_code' => (int) $columns[3],
                        'dane_municipality_code' => (int) $columns[6],
                        'zone_code' => (int) $columns[8],
                        'place_code' => (int) $columns[10],
                        'polling_station_name' => $columns[11] !== '' ? $columns[11] : null,
                        'table_number' => $columns[12] !== '' ? $columns[12] : null,
                        'imported_at' => now(),
                    ];
                }

                NationalCensusRecord::upsert(
                    array_values($rows),
                    ['document_number'],
                    ['polling_place_id', 'dane_department_code', 'dane_municipality_code', 'zone_code', 'place_code', 'polling_station_name', 'table_number', 'imported_at'],
                );
            });

        $percentage = $total > 0 ? round($unmatched / $total * 100, 2) : 0.0;

        $this->info("Importadas: {$total} filas.");
        $this->warn("Sin puesto de votación coincidente: {$unmatched} ({$percentage}%).");

        return self::SUCCESS;
    }
}

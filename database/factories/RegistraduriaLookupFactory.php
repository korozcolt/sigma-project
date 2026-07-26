<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RegistraduriaLookup;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RegistraduriaLookup> */
class RegistraduriaLookupFactory extends Factory
{
    protected $model = RegistraduriaLookup::class;

    public function definition(): array
    {
        return [
            'document_number' => fake()->unique()->numerify('##########'),
            'puesto_nombre' => 'IE '.fake()->lastName(),
            'puesto_codigo' => (string) fake()->numberBetween(1, 99),
            'zona_codigo' => (string) fake()->numberBetween(1, 9),
            'mesa_numero' => (string) fake()->numberBetween(1, 30),
            'departamento' => fake()->state(),
            'municipio' => fake()->city(),
            'direccion' => fake()->address(),
            'source' => 'live',
            'campaign_id' => null,
        ];
    }
}

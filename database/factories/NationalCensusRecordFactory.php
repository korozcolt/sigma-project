<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\NationalCensusRecord>
 */
class NationalCensusRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_number' => fake()->unique()->numerify('##########'),
            'polling_place_id' => null,
            'dane_department_code' => fake()->numberBetween(1, 99),
            'dane_municipality_code' => fake()->numberBetween(1, 999),
            'zone_code' => fake()->numberBetween(1, 9),
            'place_code' => fake()->numberBetween(1, 999),
            'polling_station_name' => fake()->company(),
            'table_number' => (string) fake()->numberBetween(1, 20),
            'imported_at' => now(),
        ];
    }
}

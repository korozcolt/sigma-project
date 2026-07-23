<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Municipality;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PollingPlace>
 */
class PollingPlaceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'municipality_id' => Municipality::factory(),
            'dane_department_code' => fake()->numberBetween(1, 99),
            'dane_municipality_code' => fake()->numberBetween(1, 999),
            'zone_code' => fake()->numberBetween(1, 9),
            'place_code' => fake()->unique()->numberBetween(1, 999),
            'name' => fake()->company().' - Puesto de Votación',
            'address' => fake()->address(),
            'commune' => null,
            'max_tables' => fake()->numberBetween(1, 20),
        ];
    }
}

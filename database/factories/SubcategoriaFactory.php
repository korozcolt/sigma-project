<?php

namespace Database\Factories;

use App\Models\Gremio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subcategoria>
 */
class SubcategoriaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gremio_id' => Gremio::factory(),
            'name' => fake()->unique()->word(),
        ];
    }
}

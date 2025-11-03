<?php

namespace Database\Factories;

use App\Models\Campaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CensusRecord>
 */
class CensusRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'document_number' => fake()->unique()->numerify('##########'),
            'full_name' => fake()->name(),
            'municipality_code' => fake()->numerify('#####'),
            'polling_station' => fake()->boolean(80) ? fake()->numerify('Mesa ###') : null,
            'table_number' => fake()->boolean(80) ? fake()->numerify('##') : null,
            'imported_at' => now(),
        ];
    }
}

<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RevalidationRun>
 */
class RevalidationRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => null,
            'leader_id' => null,
            'started_at' => now(),
            'finished_at' => null,
            'total' => 0,
            'processed' => 0,
            'changed' => 0,
        ];
    }
}

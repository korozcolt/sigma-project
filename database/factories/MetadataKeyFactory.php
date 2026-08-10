<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MetadataKey>
 */
class MetadataKeyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2, false),
            'label' => fake()->words(2, true),
            'type' => fake()->randomElement(['numeric', 'text', 'date', 'select']),
            'options' => null,
            'is_active' => true,
        ];
    }
}

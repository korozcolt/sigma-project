<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Campaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ElectionEvent>
 */
class ElectionEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(['simulation', 'real']);

        return [
            'campaign_id' => Campaign::factory(),
            'name' => $type === 'simulation'
                ? 'Simulacro '.fake()->numberBetween(1, 10)
                : 'Día D - '.fake()->date('Y-m-d'),
            'type' => $type,
            'date' => fake()->dateTimeBetween('-1 month', '+1 month'),
            'start_time' => fake()->boolean(60) ? fake()->time('H:i:s') : null,
            'end_time' => fake()->boolean(60) ? fake()->time('H:i:s') : null,
            'is_active' => false,
            'simulation_number' => $type === 'simulation' ? fake()->numberBetween(1, 20) : null,
            'notes' => fake()->boolean(40) ? fake()->sentence() : null,
            'settings' => fake()->boolean(30) ? ['allow_duplicate_votes' => false] : null,
        ];
    }

    public function simulation(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'simulation',
            'name' => 'Simulacro '.fake()->numberBetween(1, 10),
            'simulation_number' => fake()->numberBetween(1, 20),
        ]);
    }

    public function real(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'real',
            'name' => 'Día D - '.fake()->date('Y-m-d'),
            'simulation_number' => null,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function today(): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => now()->toDateString(),
        ]);
    }

    public function future(): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => fake()->dateTimeBetween('+1 day', '+1 month'),
        ]);
    }

    public function past(): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => fake()->dateTimeBetween('-1 month', '-1 day'),
        ]);
    }
}

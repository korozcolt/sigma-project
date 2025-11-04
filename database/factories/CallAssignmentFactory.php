<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CallAssignment>
 */
class CallAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'voter_id' => \App\Models\Voter::factory(),
            'assigned_to' => \App\Models\User::factory(),
            'assigned_by' => \App\Models\User::factory(),
            'campaign_id' => \App\Models\Campaign::factory(),
            'status' => fake()->randomElement(['pending', 'in_progress', 'completed', 'reassigned']),
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'urgent']),
            'assigned_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'completed_at' => null,
        ];
    }

    /**
     * Indicate that the assignment is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the assignment is in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_progress',
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the assignment is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'completed_at' => fake()->dateTimeBetween($attributes['assigned_at'] ?? '-1 day', 'now'),
        ]);
    }

    /**
     * Indicate that the assignment was reassigned.
     */
    public function reassigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'reassigned',
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the assignment has low priority.
     */
    public function lowPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'low',
        ]);
    }

    /**
     * Indicate that the assignment has medium priority.
     */
    public function mediumPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'medium',
        ]);
    }

    /**
     * Indicate that the assignment has high priority.
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'high',
        ]);
    }

    /**
     * Indicate that the assignment is urgent.
     */
    public function urgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'urgent',
        ]);
    }
}

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
            // 'select' is deliberately excluded from the random pool: the
            // MetadataKeyForm Repeater requires minItems(1) for select-typed
            // keys, so a randomly-select-typed row with options => null below
            // would be an internally-invalid catalog row. Use the dedicated
            // select() state to get a valid select-typed key with options.
            'type' => fake()->randomElement(['numeric', 'text', 'date']),
            'options' => null,
            'is_active' => true,
        ];
    }

    /**
     * Valid select-typed state: type + non-empty options together, so the row
     * never violates the form's minItems(1) constraint on the options Repeater.
     */
    public function select(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => 'select',
            'options' => ['si', 'no'],
        ]);
    }
}

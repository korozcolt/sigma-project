<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TwoCaptchaBalanceSnapshot>
 */
class TwoCaptchaBalanceSnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'balance' => $this->faker->randomFloat(5, 0, 50),
            'checked_at' => now(),
        ];
    }
}

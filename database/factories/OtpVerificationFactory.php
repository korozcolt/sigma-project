<?php

namespace Database\Factories;

use App\Models\Campaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OtpVerification>
 */
class OtpVerificationFactory extends Factory
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
            'phone' => fake()->numerify('3#########'),
            'code' => fake()->numerify('######'),
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
            'verified_at' => null,
        ];
    }
}

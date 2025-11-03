<?php

namespace Database\Factories;

use App\Models\Municipality;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= 'password',
            'remember_token' => Str::random(10),
            'two_factor_secret' => Str::random(10),
            'two_factor_recovery_codes' => Str::random(10),
            'two_factor_confirmed_at' => now(),
            'phone' => fake()->boolean(80) ? fake()->numerify('3## ### ####') : null,
            'secondary_phone' => fake()->boolean(30) ? fake()->numerify('3## ### ####') : null,
            'document_number' => fake()->boolean(90) ? fake()->unique()->numerify('##########') : null,
            'birth_date' => fake()->boolean(70) ? fake()->dateTimeBetween('-70 years', '-18 years') : null,
            'address' => fake()->boolean(60) ? fake()->address() : null,
            'municipality_id' => fake()->boolean(50) ? Municipality::inRandomOrder()->first()?->id : null,
            'neighborhood_id' => null, // Will be set manually when needed
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model does not have two-factor authentication configured.
     */
    public function withoutTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);
    }
}

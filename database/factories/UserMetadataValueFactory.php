<?php

namespace Database\Factories;

use App\Models\MetadataKey;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserMetadataValue>
 */
class UserMetadataValueFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'metadata_key_id' => MetadataKey::factory(),
            'value' => (string) fake()->numberBetween(10000, 200000),
            'assigned_by' => User::factory(),
            'assigned_at' => now(),
        ];
    }
}

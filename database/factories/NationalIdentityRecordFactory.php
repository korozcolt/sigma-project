<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\NationalIdentityRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<NationalIdentityRecord> */
class NationalIdentityRecordFactory extends Factory
{
    protected $model = NationalIdentityRecord::class;

    public function definition(): array
    {
        return [
            'cedula' => fake()->unique()->numerify('##########'),
            'nombre1' => fake()->firstName(),
            'nombre2' => fake()->optional()->firstName(),
            'apellido1' => fake()->lastName(),
            'apellido2' => fake()->optional()->lastName(),
        ];
    }
}

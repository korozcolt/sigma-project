<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\Neighborhood;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TerritorialAssignment>
 */
class TerritorialAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'campaign_id' => Campaign::factory(),
            'department_id' => null,
            'municipality_id' => null,
            'neighborhood_id' => Neighborhood::factory(),
            'assigned_by' => User::factory(),
            'assigned_at' => now(),
        ];
    }

    /**
     * Indicate that the assignment is for a department
     */
    public function forDepartment(?int $departmentId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'department_id' => $departmentId ?? Department::factory(),
            'municipality_id' => null,
            'neighborhood_id' => null,
        ]);
    }

    /**
     * Indicate that the assignment is for a municipality
     */
    public function forMunicipality(?int $municipalityId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'department_id' => null,
            'municipality_id' => $municipalityId ?? Municipality::factory(),
            'neighborhood_id' => null,
        ]);
    }

    /**
     * Indicate that the assignment is for a neighborhood
     */
    public function forNeighborhood(?int $neighborhoodId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'department_id' => null,
            'municipality_id' => null,
            'neighborhood_id' => $neighborhoodId ?? Neighborhood::factory(),
        ]);
    }
}

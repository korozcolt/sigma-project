<?php

namespace Database\Factories;

use App\Enums\CallResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VerificationCall>
 */
class VerificationCallFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $callDate = fake()->dateTimeBetween('-30 days', 'now');

        return [
            'voter_id' => \App\Models\Voter::factory(),
            'assignment_id' => \App\Models\CallAssignment::factory(),
            'caller_id' => \App\Models\User::factory(),
            'attempt_number' => fake()->numberBetween(1, 3),
            'call_date' => $callDate,
            'call_duration' => fake()->numberBetween(30, 600), // 30 seconds to 10 minutes
            'call_result' => fake()->randomElement(CallResult::cases())->value,
            'notes' => fake()->boolean(60) ? fake()->sentence() : null,
            'survey_id' => null,
            'survey_completed' => false,
            'next_attempt_at' => null,
        ];
    }

    /**
     * Indicate that the call was answered.
     */
    public function answered(): static
    {
        return $this->state(fn (array $attributes) => [
            'call_result' => CallResult::ANSWERED->value,
            'call_duration' => fake()->numberBetween(60, 300),
        ]);
    }

    /**
     * Indicate that the call was not answered.
     */
    public function noAnswer(): static
    {
        return $this->state(fn (array $attributes) => [
            'call_result' => CallResult::NO_ANSWER->value,
            'call_duration' => fake()->numberBetween(15, 60),
            'next_attempt_at' => fake()->dateTimeBetween('now', '+3 days'),
        ]);
    }

    /**
     * Indicate that the line was busy.
     */
    public function busy(): static
    {
        return $this->state(fn (array $attributes) => [
            'call_result' => CallResult::BUSY->value,
            'call_duration' => fake()->numberBetween(10, 30),
            'next_attempt_at' => fake()->dateTimeBetween('now', '+1 day'),
        ]);
    }

    /**
     * Indicate that the number was wrong.
     */
    public function wrongNumber(): static
    {
        return $this->state(fn (array $attributes) => [
            'call_result' => CallResult::WRONG_NUMBER->value,
            'call_duration' => fake()->numberBetween(20, 60),
            'next_attempt_at' => null,
        ]);
    }

    /**
     * Indicate that the call was rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'call_result' => CallResult::REJECTED->value,
            'call_duration' => fake()->numberBetween(10, 45),
        ]);
    }

    /**
     * Indicate that a callback was requested.
     */
    public function callbackRequested(): static
    {
        return $this->state(fn (array $attributes) => [
            'call_result' => CallResult::CALLBACK_REQUESTED->value,
            'call_duration' => fake()->numberBetween(30, 120),
            'next_attempt_at' => fake()->dateTimeBetween('now', '+2 days'),
        ]);
    }

    /**
     * Indicate that the voter was not interested.
     */
    public function notInterested(): static
    {
        return $this->state(fn (array $attributes) => [
            'call_result' => CallResult::NOT_INTERESTED->value,
            'call_duration' => fake()->numberBetween(30, 120),
        ]);
    }

    /**
     * Indicate that the voter was confirmed.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'call_result' => CallResult::CONFIRMED->value,
            'call_duration' => fake()->numberBetween(120, 600),
        ]);
    }

    /**
     * Indicate that the number was invalid.
     */
    public function invalidNumber(): static
    {
        return $this->state(fn (array $attributes) => [
            'call_result' => CallResult::INVALID_NUMBER->value,
            'call_duration' => 0,
        ]);
    }

    /**
     * Indicate that a survey was applied during the call.
     */
    public function withSurvey(bool $completed = true): static
    {
        return $this->state(fn (array $attributes) => [
            'survey_id' => \App\Models\Survey::factory(),
            'survey_completed' => $completed,
            'call_duration' => fake()->numberBetween(180, 900), // 3-15 minutes
        ]);
    }

    /**
     * Indicate that this is a first attempt.
     */
    public function firstAttempt(): static
    {
        return $this->state(fn (array $attributes) => [
            'attempt_number' => 1,
        ]);
    }

    /**
     * Indicate that this is a follow-up attempt.
     */
    public function followUp(int $attemptNumber = 2): static
    {
        return $this->state(fn (array $attributes) => [
            'attempt_number' => $attemptNumber,
        ]);
    }
}

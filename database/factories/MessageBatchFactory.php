<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\MessageBatch;
use App\Models\MessageTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageBatchFactory extends Factory
{
    protected $model = MessageBatch::class;

    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'template_id' => null,
            'name' => fake()->words(3, true),
            'type' => fake()->randomElement(['birthday', 'reminder', 'custom', 'campaign']),
            'channel' => fake()->randomElement(['whatsapp', 'sms', 'email']),
            'status' => 'pending',
            'total_recipients' => 0,
            'sent_count' => 0,
            'failed_count' => 0,
            'delivered_count' => 0,
            'scheduled_for' => null,
            'started_at' => null,
            'completed_at' => null,
            'filters' => null,
            'metadata' => null,
            'created_by' => User::factory(),
        ];
    }

    public function birthday(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Envío Masivo Cumpleaños',
            'type' => 'birthday',
        ]);
    }

    public function reminder(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Recordatorio Masivo',
            'type' => 'reminder',
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'total_recipients' => fake()->numberBetween(10, 100),
        ]);
    }

    public function processing(): static
    {
        return $this->state(function (array $attributes) {
            $total = fake()->numberBetween(100, 500);
            $sent = fake()->numberBetween(0, $total);

            return [
                'status' => 'processing',
                'total_recipients' => $total,
                'sent_count' => $sent,
                'failed_count' => fake()->numberBetween(0, $total - $sent),
                'started_at' => fake()->dateTimeBetween('-1 hour', 'now'),
            ];
        });
    }

    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $total = fake()->numberBetween(100, 500);
            $sent = fake()->numberBetween(80, $total);
            $failed = $total - $sent;

            return [
                'status' => 'completed',
                'total_recipients' => $total,
                'sent_count' => $sent,
                'failed_count' => $failed,
                'delivered_count' => fake()->numberBetween((int) ($sent * 0.8), $sent),
                'started_at' => fake()->dateTimeBetween('-2 hours', '-1 hour'),
                'completed_at' => fake()->dateTimeBetween('-1 hour', 'now'),
            ];
        });
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'total_recipients' => fake()->numberBetween(10, 100),
            'failed_count' => fake()->numberBetween(1, 10),
            'started_at' => fake()->dateTimeBetween('-1 hour', 'now'),
            'completed_at' => fake()->dateTimeBetween('-1 hour', 'now'),
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'scheduled_for' => fake()->dateTimeBetween('now', '+1 week'),
            'total_recipients' => fake()->numberBetween(10, 100),
        ]);
    }

    public function withTemplate(): static
    {
        return $this->state(fn (array $attributes) => [
            'template_id' => MessageTemplate::factory(),
        ]);
    }
}

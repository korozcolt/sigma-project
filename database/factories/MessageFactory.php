<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\Message;
use App\Models\MessageBatch;
use App\Models\MessageTemplate;
use App\Models\Voter;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'voter_id' => Voter::factory(),
            'template_id' => null,
            'batch_id' => null,
            'type' => fake()->randomElement(['birthday', 'reminder', 'custom', 'campaign']),
            'channel' => fake()->randomElement(['whatsapp', 'sms', 'email']),
            'subject' => fake()->boolean(30) ? fake()->sentence() : null,
            'content' => fake()->paragraph(),
            'status' => 'pending',
            'scheduled_for' => null,
            'sent_at' => null,
            'delivered_at' => null,
            'read_at' => null,
            'clicked_at' => null,
            'error_message' => null,
            'external_id' => null,
            'metadata' => null,
        ];
    }

    public function birthday(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'birthday',
            'subject' => '¡Feliz Cumpleaños!',
            'content' => '¡Feliz cumpleaños {{nombre}}! Te deseamos un día maravilloso. Saludos de {{candidato}}',
        ]);
    }

    public function reminder(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'reminder',
            'content' => 'Hola {{nombre}}, te recordamos que el día de las elecciones es {{fecha}}. Tu voto cuenta!',
        ]);
    }

    public function whatsapp(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => 'whatsapp',
            'subject' => null,
        ]);
    }

    public function sms(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => 'sms',
            'subject' => null,
        ]);
    }

    public function email(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => 'email',
            'subject' => fake()->sentence(),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'scheduled',
            'scheduled_for' => fake()->dateTimeBetween('now', '+1 week'),
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'sent',
            'sent_at' => fake()->dateTimeBetween('-1 week', 'now'),
            'external_id' => fake()->uuid(),
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'delivered',
            'sent_at' => fake()->dateTimeBetween('-1 week', 'now'),
            'delivered_at' => fake()->dateTimeBetween('-1 week', 'now'),
            'external_id' => fake()->uuid(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'error_message' => fake()->sentence(),
        ]);
    }

    public function withTemplate(): static
    {
        return $this->state(fn (array $attributes) => [
            'template_id' => MessageTemplate::factory(),
        ]);
    }

    public function withBatch(): static
    {
        return $this->state(fn (array $attributes) => [
            'batch_id' => MessageBatch::factory(),
        ]);
    }
}

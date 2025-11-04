<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\MessageTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageTemplateFactory extends Factory
{
    protected $model = MessageTemplate::class;

    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'name' => fake()->words(3, true),
            'type' => fake()->randomElement(['birthday', 'reminder', 'custom', 'campaign']),
            'channel' => fake()->randomElement(['whatsapp', 'sms', 'email']),
            'subject' => null,
            'content' => 'Hola {{nombre}}, mensaje de {{candidato}}',
            'is_active' => true,
            'max_per_voter_per_day' => 3,
            'max_per_campaign_per_hour' => 100,
            'allowed_start_time' => '08:00:00',
            'allowed_end_time' => '20:00:00',
            'allowed_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
            'created_by' => User::factory(),
        ];
    }

    public function birthday(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Plantilla de Cumpleaños',
            'type' => 'birthday',
            'content' => '¡Feliz cumpleaños {{nombre}}! Te deseamos un día maravilloso. Saludos de {{candidato}}',
        ]);
    }

    public function reminder(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Recordatorio de Elecciones',
            'type' => 'reminder',
            'content' => 'Hola {{nombre}}, recuerda que el día {{fecha}} son las elecciones. Tu voto es importante!',
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
            'subject' => 'Mensaje de {{candidato}}',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function restrictive(): static
    {
        return $this->state(fn (array $attributes) => [
            'max_per_voter_per_day' => 1,
            'max_per_campaign_per_hour' => 10,
            'allowed_start_time' => '09:00:00',
            'allowed_end_time' => '18:00:00',
            'allowed_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        ]);
    }
}

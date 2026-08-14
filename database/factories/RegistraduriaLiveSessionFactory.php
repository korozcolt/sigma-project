<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RegistraduriaLiveSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RegistraduriaLiveSession> */
class RegistraduriaLiveSessionFactory extends Factory
{
    protected $model = RegistraduriaLiveSession::class;

    public function definition(): array
    {
        return [
            'document_number' => fake()->unique()->numerify('##########'),
            'session_id' => fake()->uuid(),
            'adapter_class' => \App\Services\RegistraduriaService::class,
            'voter_id' => null,
            'campaign_id' => null,
            'resolved_via' => 'reconciliation',
            'expires_at' => now()->addMinutes(10),
        ];
    }
}

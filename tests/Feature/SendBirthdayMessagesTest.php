<?php

declare(strict_types=1);

use App\Console\Commands\SendBirthdayMessages;
use App\Models\Campaign;
use App\Models\MessageTemplate;
use App\Models\Voter;
use Illuminate\Support\Facades\Config;

test('command sends birthday messages to voters with birthdays today', function () {
    Config::set('queue.default', 'sync');

    $campaign = Campaign::factory()->create(['status' => \App\Enums\CampaignStatus::ACTIVE]);

    $template = MessageTemplate::factory()
        ->for($campaign)
        ->birthday()
        ->whatsapp()
        ->create([
            'allowed_start_time' => '00:00:00',
            'allowed_end_time' => '23:59:59',
        ]);

    // Votante con cumpleaños hoy
    $voter = Voter::factory()->for($campaign)->confirmed()->create([
        'birth_date' => today()->subYears(25),
    ]);

    // Votante con cumpleaños otro día
    Voter::factory()->for($campaign)->confirmed()->create([
        'birth_date' => today()->subYears(30)->addDay(),
    ]);

    $this->artisan(SendBirthdayMessages::class)
        ->assertSuccessful();

    // Verificar que el mensaje fue creado y enviado
    expect(\App\Models\Message::count())->toBeGreaterThan(0);
});

test('command respects campaign filter', function () {
    Config::set('queue.default', 'sync');

    $campaign1 = Campaign::factory()->create(['status' => \App\Enums\CampaignStatus::ACTIVE]);
    $campaign2 = Campaign::factory()->create(['status' => \App\Enums\CampaignStatus::ACTIVE]);

    MessageTemplate::factory()->for($campaign1)->birthday()->whatsapp()->create([
        'allowed_start_time' => '00:00:00',
        'allowed_end_time' => '23:59:59',
    ]);
    MessageTemplate::factory()->for($campaign2)->birthday()->whatsapp()->create([
        'allowed_start_time' => '00:00:00',
        'allowed_end_time' => '23:59:59',
    ]);

    Voter::factory()->for($campaign1)->confirmed()->create([
        'birth_date' => today()->subYears(25),
    ]);
    Voter::factory()->for($campaign2)->confirmed()->create([
        'birth_date' => today()->subYears(30),
    ]);

    $this->artisan(SendBirthdayMessages::class, ['--campaign' => $campaign1->id])
        ->assertSuccessful();

    expect(\App\Models\Message::where('campaign_id', $campaign1->id)->count())->toBeGreaterThan(0)
        ->and(\App\Models\Message::where('campaign_id', $campaign2->id)->count())->toBe(0);
});

test('command skips when no active template exists', function () {
    $campaign = Campaign::factory()->active()->create();

    Voter::factory()->for($campaign)->confirmed()->create([
        'birth_date' => today()->subYears(25),
    ]);

    $this->artisan(SendBirthdayMessages::class)
        ->expectsOutputToContain('Procesando campaña: '.$campaign->name)
        ->assertSuccessful();

    expect(\App\Models\Message::count())->toBe(0);
});

test('command skips when no voters have birthdays today', function () {
    $campaign = Campaign::factory()->active()->create();

    MessageTemplate::factory()->for($campaign)->birthday()->create();

    Voter::factory()->for($campaign)->confirmed()->create([
        'birth_date' => today()->subYears(25)->addDay(),
    ]);

    $this->artisan(SendBirthdayMessages::class)
        ->assertSuccessful();

    expect(\App\Models\Message::count())->toBe(0);
});

test('command respects rate limiting', function () {
    $campaign = Campaign::factory()->active()->create();
    $voter = Voter::factory()->for($campaign)->confirmed()->create([
        'birth_date' => today()->subYears(25),
    ]);

    $template = MessageTemplate::factory()
        ->for($campaign)
        ->birthday()
        ->create(['max_per_voter_per_day' => 1]);

    // Crear mensaje previo hoy
    \App\Models\Message::factory()
        ->for($campaign)
        ->for($voter)
        ->for($template, 'template')
        ->create(['created_at' => now()]);

    $this->artisan(SendBirthdayMessages::class)
        ->assertSuccessful();

    // No debe crear nuevo mensaje por rate limit
    expect(\App\Models\Message::count())->toBe(1);
});

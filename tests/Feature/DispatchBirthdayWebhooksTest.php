<?php

declare(strict_types=1);

use App\Models\Campaign;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    Carbon::setTestNow(Carbon::create(2026, 5, 14, 13, 0, 0, 'UTC')); // 08:00 Colombia
});

afterEach(function () {
    Carbon::setTestNow(null);
});

test('dispatches webhook when voter has birthday today and time matches', function () {
    $campaign = Campaign::factory()->active()->create([
        'settings' => [
            'birthday_webhook_enabled' => true,
            'birthday_webhook_url' => 'https://test.example.com/hook',
            'birthday_webhook_time' => '08:00',
        ],
    ]);

    Voter::factory()->for($campaign)->create([
        'birth_date' => '1990-05-14',
        'first_name' => 'Juan',
        'last_name' => 'Pérez',
    ]);

    Http::fake(['*' => Http::response([], 200)]);

    $this->artisan('birthday:dispatch-webhooks')->assertSuccessful();

    Http::assertSent(fn ($request) => $request->url() === 'https://test.example.com/hook'
        && $request['campaign_id'] === $campaign->id
        && count($request['people']) === 1
        && $request['people'][0]['type'] === 'voter'
    );
});

test('dispatches webhook when coordinator has birthday today', function () {
    $campaign = Campaign::factory()->active()->create([
        'settings' => [
            'birthday_webhook_enabled' => true,
            'birthday_webhook_url' => 'https://test.example.com/hook',
            'birthday_webhook_time' => '08:00',
        ],
    ]);

    $user = User::factory()->create([
        'birth_date' => '1985-05-14',
    ]);
    $user->assignRole('coordinator');
    $campaign->users()->attach($user->id);

    Http::fake(['*' => Http::response([], 200)]);

    $this->artisan('birthday:dispatch-webhooks')->assertSuccessful();

    Http::assertSent(fn ($request) => $request['people'][0]['type'] === 'coordinator');
});

test('skips when time does not match', function () {
    $campaign = Campaign::factory()->active()->create([
        'settings' => [
            'birthday_webhook_enabled' => true,
            'birthday_webhook_url' => 'https://test.example.com/hook',
            'birthday_webhook_time' => '10:00', // current Colombia time is 08:00
        ],
    ]);

    Voter::factory()->for($campaign)->create([
        'birth_date' => '1990-05-14',
    ]);

    Http::fake(['*' => Http::response([], 200)]);

    $this->artisan('birthday:dispatch-webhooks')->assertSuccessful();

    Http::assertNothingSent();
});

test('skips when birthday_webhook_enabled is false', function () {
    $campaign = Campaign::factory()->active()->create([
        'settings' => [
            'birthday_webhook_enabled' => false,
            'birthday_webhook_url' => 'https://test.example.com/hook',
            'birthday_webhook_time' => '08:00',
        ],
    ]);

    Voter::factory()->for($campaign)->create([
        'birth_date' => '1990-05-14',
    ]);

    Http::fake(['*' => Http::response([], 200)]);

    $this->artisan('birthday:dispatch-webhooks')->assertSuccessful();

    Http::assertNothingSent();
});

test('no HTTP call when nobody has birthday today', function () {
    $campaign = Campaign::factory()->active()->create([
        'settings' => [
            'birthday_webhook_enabled' => true,
            'birthday_webhook_url' => 'https://test.example.com/hook',
            'birthday_webhook_time' => '08:00',
        ],
    ]);

    // Voter with birthday on a different day
    Voter::factory()->for($campaign)->create([
        'birth_date' => '1990-06-20',
    ]);

    Http::fake(['*' => Http::response([], 200)]);

    $this->artisan('birthday:dispatch-webhooks')->assertSuccessful();

    Http::assertNothingSent();
});

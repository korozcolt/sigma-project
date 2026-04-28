<?php

declare(strict_types=1);

use App\Models\Voter;
use Illuminate\Support\Carbon;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::create(2000, 6, 15, 12, 0, 0, 'America/Bogota'));
});

afterEach(function (): void {
    Carbon::setTestNow(null);
});

it('returns voters born today as a JSON array of full names', function (): void {
    Voter::factory()->create([
        'first_name' => 'Ana',
        'last_name' => 'Gomez',
        'birth_date' => '1985-06-15',
    ]);

    $this->getJson('/api/cumpleanos')
        ->assertOk()
        ->assertJsonFragment(['Ana Gomez']);
});

it('excludes voters not born today', function (): void {
    $voter = Voter::factory()->create([
        'first_name' => 'Carlos',
        'last_name' => 'Perez',
        'birth_date' => '1990-06-14',
    ]);

    $response = $this->getJson('/api/cumpleanos')->assertOk();

    expect($response->json())->not->toContain('Carlos Perez');
});

it('returns an empty array when no voters have a birthday today', function (): void {
    $this->getJson('/api/cumpleanos')
        ->assertOk()
        ->assertExactJson([]);
});

it('is accessible without authentication', function (): void {
    $this->getJson('/api/cumpleanos')->assertOk();
});

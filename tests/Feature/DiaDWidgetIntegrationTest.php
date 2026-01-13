<?php

declare(strict_types=1);

use App\Models\Campaign;
use App\Models\User;
use App\Models\Voter;

use function Pest\Laravel\actingAs;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('super_admin');
});

it('shows dia-d stats overview widget on the page', function () {
    actingAs($this->user);

    $campaign = Campaign::factory()->create(['status' => 'active']);

    // Create voters with different statuses
    Voter::factory()->for($campaign)->confirmed()->create();
    Voter::factory()->for($campaign)->voted()->create();
    Voter::factory()->for($campaign)->didNotVote()->create();

    $response = $this->get('/admin/dia-d');

    $response->assertStatus(200);

    // The widget is lazy-loaded in Filament; ensure the page references the widget component
    $response->assertSee('app.filament.widgets.dia-d-stats-overview');

    // Also test the widget directly via Livewire to ensure it renders the stats
    Livewire\Livewire::test(\App\Filament\Widgets\DiaDStatsOverview::class)
        ->assertSee('Total Votantes')
        ->assertSee('Confirmados')
        ->assertSee('Votaron')
        ->assertSee('No Votaron')
        ->assertSee('3');
});

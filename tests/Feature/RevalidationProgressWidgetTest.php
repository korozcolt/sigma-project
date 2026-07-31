<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Widgets\RevalidationProgressWidget;
use App\Models\Campaign;
use App\Models\RevalidationRun;
use App\Models\User;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses()->group('dashboard-widgets');

beforeEach(function () {
    Role::firstOrCreate(['name' => UserRole::SUPER_ADMIN->value, 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole(UserRole::SUPER_ADMIN->value);
    $this->actingAs($user);

    $this->campaign = Campaign::factory()->create(['status' => 'active']);
    Session::put('campaign_context.campaign_id', $this->campaign->id);
    Session::put('campaign_context.mode', 'single');
});

test('renders nothing when no revalidation run exists for the campaign', function () {
    Livewire::test(RevalidationProgressWidget::class)
        ->assertOk()
        ->assertDontSee('Revalidación en progreso')
        ->assertDontSee('Última revalidación finalizada');
});

test('shows the in-progress banner with started_at and processed/total when the latest run is unfinished', function () {
    RevalidationRun::factory()->create([
        'campaign_id' => $this->campaign->id,
        'started_at' => now(),
        'finished_at' => null,
        'total' => 10,
        'processed' => 4,
        'changed' => 0,
    ]);

    Livewire::test(RevalidationProgressWidget::class)
        ->assertOk()
        ->assertSee('Revalidación en progreso')
        ->assertSee('4 / 10');
});

test('shows the finished summary with processed/changed counts when the latest run has finished', function () {
    RevalidationRun::factory()->create([
        'campaign_id' => $this->campaign->id,
        'started_at' => now()->subMinutes(5),
        'finished_at' => now(),
        'total' => 8,
        'processed' => 8,
        'changed' => 3,
    ]);

    Livewire::test(RevalidationProgressWidget::class)
        ->assertOk()
        ->assertSee('Última revalidación finalizada')
        ->assertSee('8 apoyos revisados')
        ->assertSee('3 cambiaron de estado');
});

test('scopes to the current campaign, ignoring a run from a different campaign', function () {
    $otherCampaign = Campaign::factory()->create(['status' => 'active']);

    RevalidationRun::factory()->create([
        'campaign_id' => $otherCampaign->id,
        'started_at' => now(),
        'finished_at' => null,
        'total' => 5,
        'processed' => 1,
    ]);

    Livewire::test(RevalidationProgressWidget::class)
        ->assertOk()
        ->assertDontSee('Revalidación en progreso');
});

test('the in-progress banner never has a dismiss button', function () {
    RevalidationRun::factory()->create([
        'campaign_id' => $this->campaign->id,
        'started_at' => now(),
        'finished_at' => null,
        'total' => 10,
        'processed' => 4,
        'changed' => 0,
    ]);

    Livewire::test(RevalidationProgressWidget::class)
        ->assertOk()
        ->assertDontSee('Cerrar');
});

test('the finished banner has a dismiss button keyed to the run id', function () {
    $run = RevalidationRun::factory()->create([
        'campaign_id' => $this->campaign->id,
        'started_at' => now()->subMinutes(5),
        'finished_at' => now(),
        'total' => 8,
        'processed' => 8,
        'changed' => 3,
    ]);

    Livewire::test(RevalidationProgressWidget::class)
        ->assertOk()
        ->assertSee('Cerrar')
        ->assertSee("wire:key=\"revalidation-run-{$run->id}\"", false);
});

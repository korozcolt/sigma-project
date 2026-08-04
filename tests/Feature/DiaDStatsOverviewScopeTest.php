<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Widgets\DiaDStatsOverview;
use App\Models\Campaign;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

    $this->campaign = Campaign::factory()->create(['status' => 'active']);

    $this->coordinatorA = User::factory()->create();
    $this->coordinatorA->assignRole(UserRole::COORDINATOR->value);
    $this->coordinatorA->campaigns()->attach($this->campaign);

    $this->leaderA = User::factory()->create(['coordinator_user_id' => $this->coordinatorA->id]);
    $this->leaderA->assignRole(UserRole::LEADER->value);
    $this->leaderA->campaigns()->attach($this->campaign);
    Voter::factory()->count(3)->create(['campaign_id' => $this->campaign->id, 'registered_by' => $this->leaderA->id]);

    $this->coordinatorB = User::factory()->create();
    $this->coordinatorB->assignRole(UserRole::COORDINATOR->value);
    $this->coordinatorB->campaigns()->attach($this->campaign);

    $this->leaderB = User::factory()->create(['coordinator_user_id' => $this->coordinatorB->id]);
    $this->leaderB->assignRole(UserRole::LEADER->value);
    $this->leaderB->campaigns()->attach($this->campaign);
    Voter::factory()->count(5)->create(['campaign_id' => $this->campaign->id, 'registered_by' => $this->leaderB->id]);
});

function actAsWithActiveCampaign(User $user, Campaign $campaign): void
{
    test()->actingAs($user);
    Session::put('campaign_context.campaign_id', $campaign->id);
    Session::put('campaign_context.mode', 'single');
}

it('un líder ve solo el total de sus propios apoyos en DiaDStatsOverview', function () {
    actAsWithActiveCampaign($this->leaderA, $this->campaign);

    Livewire::test(DiaDStatsOverview::class)->assertOk()->assertSeeText('3');
});

it('un coordinador ve solo el total de apoyos de su propio equipo, no el de otro coordinador', function () {
    actAsWithActiveCampaign($this->coordinatorA, $this->campaign);

    Livewire::test(DiaDStatsOverview::class)
        ->assertOk()
        ->assertSeeText('3')
        ->assertDontSeeText('8'); // 3 (equipo A) + 5 (equipo B) combinado
});

it('un super_admin ve el total campaña-completa sin restricción', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(UserRole::SUPER_ADMIN->value);
    actAsWithActiveCampaign($superAdmin, $this->campaign);

    Livewire::test(DiaDStatsOverview::class)->assertOk()->assertSeeText('8');
});

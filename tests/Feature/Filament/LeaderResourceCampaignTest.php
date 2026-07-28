<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Resources\Leaders\Pages\CreateLeader;
use App\Filament\Resources\Leaders\Pages\EditLeader;
use App\Models\Campaign;
use App\Models\Municipality;
use App\Models\User;
use App\Services\CampaignContext;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::SUPER_ADMIN->value);
    $this->admin->forceFill([
        'municipality_id' => null,
        'neighborhood_id' => null,
    ])->save();

    actingAs($this->admin);

    $this->campaign = Campaign::factory()->create(['created_by' => $this->admin->id]);
    $this->municipality = Municipality::factory()->create();
});

function leaderFormData(User $coordinator, array $overrides = []): array
{
    return array_merge([
        'coordinator_user_id' => $coordinator->id,
        'name' => 'Lider Test',
        'email' => 'lider@example.com',
        'document_number' => '900200300',
        'phone' => '3001234567',
        'password' => 'password123',
    ], $overrides);
}

test('creating a leader inherits the coordinator campaign attachments', function () {
    $coordinator = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $coordinator->assignRole(UserRole::COORDINATOR->value);
    $coordinator->campaigns()->attach($this->campaign->id);

    CampaignContext::setCampaignId($this->campaign->id);

    Livewire::test(CreateLeader::class)
        ->fillForm(leaderFormData($coordinator))
        ->call('create')
        ->assertHasNoFormErrors();

    $leader = User::where('email', 'lider@example.com')->first();

    expect($leader)->not->toBeNull();
    expect($leader->campaigns->pluck('id'))->toContain($this->campaign->id);
});

test('creating a leader falls back to the active campaign when the coordinator has none', function () {
    // The coordinator has zero campaign_user rows, so it's only selectable
    // while browsing in "view all" mode (the global scope hides it under any
    // specific-campaign filter) — matches the reported production repro one
    // level down (broken coordinator -> broken leader).
    $activeCampaign = Campaign::factory()->active()->create(['created_by' => $this->admin->id]);

    $coordinator = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $coordinator->assignRole(UserRole::COORDINATOR->value);

    CampaignContext::setCampaignId(null);

    Livewire::test(CreateLeader::class)
        ->fillForm(leaderFormData($coordinator))
        ->call('create')
        ->assertHasNoFormErrors();

    $leader = User::where('email', 'lider@example.com')->first();

    expect($leader)->not->toBeNull();
    expect($leader->campaigns->pluck('id'))->toContain($activeCampaign->id);
});

test('editing a leader falls back to the active campaign when the coordinator has none', function () {
    $activeCampaign = Campaign::factory()->active()->create(['created_by' => $this->admin->id]);

    $coordinator = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $coordinator->assignRole(UserRole::COORDINATOR->value);

    $leader = User::factory()->create([
        'coordinator_user_id' => $coordinator->id,
        'municipality_id' => $this->municipality->id,
        'phone' => '3001234567',
        'document_number' => '900200300',
    ]);
    $leader->assignRole(UserRole::LEADER->value);

    expect($leader->campaigns()->count())->toBe(0);

    // Zero campaign_user rows means the leader is only reachable via the
    // Edit route in "view all" mode (global scope hides it otherwise).
    CampaignContext::setCampaignId(null);

    Livewire::test(EditLeader::class, ['record' => $leader->id])
        ->call('save')
        ->assertHasNoFormErrors();

    $leader->refresh();

    expect($leader->campaigns->pluck('id'))->toContain($activeCampaign->id);
});

<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Resources\Voters\Pages\CreateVoter;
use App\Models\Campaign;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    collect(UserRole::values())->each(function ($role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    });

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::SUPER_ADMIN->value);

    actingAs($this->admin);

    // Super admin in "view all" mode - no specific campaign active in session.
    Session::put('campaign_context.mode', 'all');
    Session::forget('campaign_context.campaign_id');
});

function makeCoordinatorAndLeader(Municipality $municipality): array
{
    $coordinator = User::factory()->create(['municipality_id' => $municipality->id]);
    $coordinator->assignRole(UserRole::COORDINATOR->value);
    $coordinator->update(['coordinator_user_id' => $coordinator->id]);

    $leader = User::factory()->create([
        'municipality_id' => $municipality->id,
        'coordinator_user_id' => $coordinator->id,
    ]);
    $leader->assignRole(UserRole::LEADER->value);

    return [$coordinator, $leader];
}

test('creating a voter as super_admin in view-all mode resolves campaign_id from the single active campaign in the system', function () {
    $municipality = Municipality::factory()->create();
    $campaign = Campaign::factory()->active()->create(['created_by' => $this->admin->id]);
    [$coordinator, $leader] = makeCoordinatorAndLeader($municipality);

    Livewire::test(CreateVoter::class)
        ->assertFormSet(['campaign_id' => $campaign->id])
        ->assertFormFieldDisabled('campaign_id')
        ->fillForm([
            'coordinator_user_id' => $coordinator->id,
            'registered_by' => $leader->id,
            'first_name' => 'Juan',
            'last_name' => 'Pérez',
            'document_number' => '12345678',
            'phone' => '3001234567',
            'municipality_id' => $municipality->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('voters', [
        'document_number' => '12345678',
        'campaign_id' => $campaign->id,
        'registered_by' => $leader->id,
    ]);
});

test('creating a voter as super_admin derives campaign_id from the selected leader coordinator when otherwise ambiguous', function () {
    $municipality = Municipality::factory()->create();

    // Two active campaigns system-wide -> resolveUnambiguousCampaignId() alone cannot resolve.
    $campaignA = Campaign::factory()->active()->create(['created_by' => $this->admin->id]);
    Campaign::factory()->active()->create(['created_by' => $this->admin->id]);

    [$coordinator, $leader] = makeCoordinatorAndLeader($municipality);
    $coordinator->campaigns()->attach($campaignA);

    Livewire::test(CreateVoter::class)
        ->assertFormFieldEnabled('campaign_id')
        ->assertFormSet(['campaign_id' => null])
        ->fillForm([
            'coordinator_user_id' => $coordinator->id,
            'registered_by' => $leader->id,
        ])
        ->assertFormSet(['campaign_id' => $campaignA->id])
        ->assertFormFieldDisabled('campaign_id')
        ->fillForm([
            'first_name' => 'Ana',
            'last_name' => 'Gómez',
            'document_number' => '87654321',
            'phone' => '3007654321',
            'municipality_id' => $municipality->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('voters', [
        'document_number' => '87654321',
        'campaign_id' => $campaignA->id,
        'registered_by' => $leader->id,
    ]);
});

test('campaign_id stays enabled for manual selection when it cannot be resolved unambiguously', function () {
    $municipality = Municipality::factory()->create();

    // Two active campaigns, no leader selected yet -> genuinely ambiguous.
    $campaignA = Campaign::factory()->active()->create(['created_by' => $this->admin->id]);
    Campaign::factory()->active()->create(['created_by' => $this->admin->id]);

    [$coordinator, $leader] = makeCoordinatorAndLeader($municipality);

    Livewire::test(CreateVoter::class)
        ->assertFormFieldEnabled('campaign_id')
        ->assertFormSet(['campaign_id' => null])
        ->fillForm([
            'campaign_id' => $campaignA->id,
            'coordinator_user_id' => $coordinator->id,
            'registered_by' => $leader->id,
            'first_name' => 'Luis',
            'last_name' => 'Ramírez',
            'document_number' => '11223344',
            'phone' => '3009998877',
            'municipality_id' => $municipality->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('voters', [
        'document_number' => '11223344',
        'campaign_id' => $campaignA->id,
    ]);
});

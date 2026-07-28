<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Resources\Coordinators\Pages\CreateCoordinator;
use App\Filament\Resources\Coordinators\Pages\EditCoordinator;
use App\Filament\Resources\Coordinators\Pages\ListCoordinators;
use App\Models\Campaign;
use App\Models\Municipality;
use App\Models\User;
use App\Services\CampaignContext;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

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

function coordinatorFormData(Municipality $municipality, array $overrides = []): array
{
    return array_merge([
        'name' => 'Coordinador Test',
        'email' => 'coordinador@example.com',
        'document_number' => '900100200',
        'phone' => '3001234567',
        'password' => 'password123',
        'municipality_id' => $municipality->id,
    ], $overrides);
}

test('creating a coordinator attaches it to the active campaign', function () {
    CampaignContext::setCampaignId($this->campaign->id);

    Livewire::test(CreateCoordinator::class)
        ->fillForm(coordinatorFormData($this->municipality))
        ->call('create')
        ->assertHasNoFormErrors();

    $coordinator = User::where('email', 'coordinador@example.com')->first();

    expect($coordinator)->not->toBeNull();
    expect($coordinator->hasRole(UserRole::COORDINATOR->value))->toBeTrue();
    expect($coordinator->campaigns->pluck('id'))->toContain($this->campaign->id);

    $pivot = $coordinator->campaigns()->whereKey($this->campaign->id)->first()->pivot;
    expect($pivot->role_id)->toBe(Role::where('name', UserRole::COORDINATOR->value)->first()->id);
    expect($pivot->assigned_by)->toBe($this->admin->id);
});

test('coordinator is visible in the list when filtering by the active campaign', function () {
    CampaignContext::setCampaignId($this->campaign->id);

    Livewire::test(CreateCoordinator::class)
        ->fillForm(coordinatorFormData($this->municipality))
        ->call('create')
        ->assertHasNoFormErrors();

    $coordinator = User::where('email', 'coordinador@example.com')->first();

    Livewire::test(ListCoordinators::class)
        ->assertCanSeeTableRecords([$coordinator]);
});

test('cannot create a coordinator without an active campaign selected', function () {
    CampaignContext::setCampaignId(null);

    Livewire::test(CreateCoordinator::class)
        ->fillForm(coordinatorFormData($this->municipality))
        ->call('create')
        ->assertNotified();

    $this->assertDatabaseMissing('users', ['email' => 'coordinador@example.com']);
});

test('editing a coordinator without campaign attachment self-heals under the single active campaign', function () {
    // The coordinator's campaign_user rows are empty, so it's only reachable
    // via the Edit route in "view all" mode (the global scope hides it under
    // any specific-campaign filter) — matches the reported production repro.
    $activeCampaign = Campaign::factory()->active()->create(['created_by' => $this->admin->id]);

    $coordinator = User::factory()->create([
        'municipality_id' => $this->municipality->id,
        'phone' => '3001234567',
        'document_number' => '900100200',
    ]);
    $coordinator->assignRole(UserRole::COORDINATOR->value);

    expect($coordinator->campaigns()->count())->toBe(0);

    CampaignContext::setCampaignId(null);

    Livewire::test(EditCoordinator::class, ['record' => $coordinator->id])
        ->call('save')
        ->assertHasNoFormErrors();

    $coordinator->refresh();

    expect($coordinator->campaigns->pluck('id'))->toContain($activeCampaign->id);
});

<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Resources\AreaCoordinators\Pages\CreateAreaCoordinator;
use App\Filament\Resources\AreaCoordinators\Pages\EditAreaCoordinator;
use App\Filament\Resources\AreaCoordinators\Pages\ListAreaCoordinators;
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

function areaCoordinatorFormData(Municipality $municipality, array $overrides = []): array
{
    return array_merge([
        'name' => 'Articulador Test',
        'email' => 'articulador@example.com',
        'document_number' => '900200300',
        'phone' => '3009876543',
        'password' => 'password123',
        'municipality_id' => $municipality->id,
    ], $overrides);
}

test('creating an area coordinator attaches it to the active campaign', function () {
    CampaignContext::setCampaignId($this->campaign->id);

    Livewire::test(CreateAreaCoordinator::class)
        ->fillForm(areaCoordinatorFormData($this->municipality))
        ->call('create')
        ->assertHasNoFormErrors();

    $areaCoordinator = User::where('email', 'articulador@example.com')->first();

    expect($areaCoordinator)->not->toBeNull();
    expect($areaCoordinator->hasRole(UserRole::AREA_COORDINATOR->value))->toBeTrue();
    expect($areaCoordinator->campaigns->pluck('id'))->toContain($this->campaign->id);

    $pivot = $areaCoordinator->campaigns()->whereKey($this->campaign->id)->first()->pivot;
    expect($pivot->role_id)->toBe(Role::where('name', UserRole::AREA_COORDINATOR->value)->first()->id);
    expect($pivot->assigned_by)->toBe($this->admin->id);
});

test('area coordinator is visible in the list when filtering by the active campaign', function () {
    CampaignContext::setCampaignId($this->campaign->id);

    Livewire::test(CreateAreaCoordinator::class)
        ->fillForm(areaCoordinatorFormData($this->municipality))
        ->call('create')
        ->assertHasNoFormErrors();

    $areaCoordinator = User::where('email', 'articulador@example.com')->first();

    Livewire::test(ListAreaCoordinators::class)
        ->assertCanSeeTableRecords([$areaCoordinator]);
});

test('cannot create an area coordinator without an active campaign selected', function () {
    CampaignContext::setCampaignId(null);

    Livewire::test(CreateAreaCoordinator::class)
        ->fillForm(areaCoordinatorFormData($this->municipality))
        ->call('create')
        ->assertNotified();

    $this->assertDatabaseMissing('users', ['email' => 'articulador@example.com']);
});

test('list table shows the count of coordinadores assigned to each area coordinator', function () {
    CampaignContext::setCampaignId($this->campaign->id);

    $areaCoordinator = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $areaCoordinator->assignRole(UserRole::AREA_COORDINATOR->value);
    $areaCoordinator->campaigns()->attach($this->campaign->id);

    // Each coordinator must also be attached to the active campaign: the
    // coordinators() HasMany relation carries User's CampaignMembershipScope
    // global scope, so an unattached coordinator would silently drop out of
    // the counts('coordinators') aggregate below (Rule 1 fix).
    User::factory()->count(3)->create(['area_coordinator_user_id' => $areaCoordinator->id])
        ->each(function (User $coordinator) {
            $coordinator->assignRole(UserRole::COORDINATOR->value);
            $coordinator->campaigns()->attach($this->campaign->id);
        });

    // Pass the record's key (not the in-memory $areaCoordinator instance) so
    // Filament re-resolves it through the table's own query — the in-memory
    // instance was never fetched with the counts('coordinators') aggregate
    // applied and would otherwise report a null state (Rule 1 fix).
    Livewire::test(ListAreaCoordinators::class)
        ->assertTableColumnStateSet('coordinators_count', 3, $areaCoordinator->id);
});

test('super_admin is not blocked by CoordinatorPolicy when editing an area coordinator record', function () {
    CampaignContext::setCampaignId($this->campaign->id);

    $areaCoordinator = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $areaCoordinator->assignRole(UserRole::AREA_COORDINATOR->value);
    $areaCoordinator->campaigns()->attach($this->campaign->id);

    Livewire::test(EditAreaCoordinator::class, ['record' => $areaCoordinator->id])
        ->assertFormSet(['email' => $areaCoordinator->email])
        ->call('save')
        ->assertHasNoFormErrors();
});

<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Resources\AreaCoordinators\Pages\CreateAreaCoordinator;
use App\Filament\Resources\AreaCoordinators\Pages\EditAreaCoordinator;
use App\Filament\Resources\Coordinators\Pages\CreateCoordinator;
use App\Filament\Resources\Coordinators\Pages\EditCoordinator;
use App\Filament\Resources\Leaders\Pages\CreateLeader;
use App\Filament\Resources\Leaders\Pages\EditLeader;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Campaign;
use App\Models\MetadataKey;
use App\Models\User;
use App\Models\UserMetadataValue;
use App\Services\CampaignContext;
use App\Services\MetadataAssignmentService;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    collect(UserRole::values())->each(
        fn ($role) => Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])
    );

    $this->superAdmin = User::factory()->create([
        'document_number' => '900100200',
        'phone' => '3001234567',
    ]);
    $this->superAdmin->assignRole(UserRole::SUPER_ADMIN->value);

    actingAs($this->superAdmin);

    $this->campaign = Campaign::factory()->create(['created_by' => $this->superAdmin->id, 'status' => 'active']);
    CampaignContext::setCampaignId($this->campaign->id);
});

afterEach(function () {
    CampaignContext::setCampaignId(null);
});

function metadataAssignmentSectionAction(): TestAction
{
    return TestAction::make('assignMetadata')->schemaComponent('metadataAssignmentSection');
}

function makeSectionCoordinator(Campaign $campaign): User
{
    $coordinator = User::factory()->create([
        'document_number' => (string) fake()->unique()->numerify('##########'),
        'phone' => fake()->numerify('3#########'),
    ]);
    $coordinator->assignRole(UserRole::COORDINATOR->value);
    $coordinator->campaigns()->attach($campaign->id);

    return $coordinator;
}

it('shows the metadata section only when editing a coordinador', function () {
    $coordinator = makeSectionCoordinator($this->campaign);

    Livewire::test(EditCoordinator::class, ['record' => $coordinator->getRouteKey()])
        ->assertActionVisible(metadataAssignmentSectionAction());

    Livewire::test(CreateCoordinator::class)
        ->assertActionDoesNotExist(metadataAssignmentSectionAction());
});

it('assigns a numeric value to a coordinador from the edit form', function () {
    $coordinator = makeSectionCoordinator($this->campaign);
    $key = MetadataKey::factory()->create(['type' => 'numeric', 'is_active' => true]);

    Livewire::test(EditCoordinator::class, ['record' => $coordinator->getRouteKey()])
        ->callAction(
            metadataAssignmentSectionAction(),
            ['metadata_key_id' => $key->id, 'value' => '150000'],
        );

    $row = UserMetadataValue::where('user_id', $coordinator->id)
        ->where('metadata_key_id', $key->id)
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->value)->toBe('150000')
        ->and($row->assigned_by)->toBe($this->superAdmin->id)
        ->and($row->assigned_at)->not->toBeNull();

    expect(UserMetadataValue::where('user_id', $coordinator->id)->where('metadata_key_id', $key->id)->count())->toBe(1);
});

it('assigns a select value constrained to the key options', function () {
    $coordinator = makeSectionCoordinator($this->campaign);
    $key = MetadataKey::factory()->create(['type' => 'select', 'options' => ['si', 'no'], 'is_active' => true]);

    Livewire::test(EditCoordinator::class, ['record' => $coordinator->getRouteKey()])
        ->callAction(
            metadataAssignmentSectionAction(),
            ['metadata_key_id' => $key->id, 'value' => 'si'],
        );

    $row = UserMetadataValue::where('user_id', $coordinator->id)
        ->where('metadata_key_id', $key->id)
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->value)->toBe('si');

    expect(fn () => app(MetadataAssignmentService::class)->validateValue($key, 'quizas'))
        ->toThrow(InvalidArgumentException::class);
});

it('shows the metadata section on the líder edit form', function () {
    $coordinator = makeSectionCoordinator($this->campaign);

    $leader = User::factory()->create([
        'document_number' => (string) fake()->unique()->numerify('##########'),
        'phone' => fake()->numerify('3#########'),
        'coordinator_user_id' => $coordinator->id,
        'municipality_id' => $coordinator->municipality_id,
    ]);
    $leader->assignRole(UserRole::LEADER->value);
    $leader->campaigns()->attach($this->campaign->id);

    Livewire::test(EditLeader::class, ['record' => $leader->getRouteKey()])
        ->assertActionVisible(metadataAssignmentSectionAction());

    Livewire::test(CreateLeader::class)
        ->assertActionDoesNotExist(metadataAssignmentSectionAction());
});

it('shows the metadata section on the articulador edit form', function () {
    $areaCoordinator = User::factory()->create([
        'document_number' => (string) fake()->unique()->numerify('##########'),
        'phone' => fake()->numerify('3#########'),
    ]);
    $areaCoordinator->assignRole(UserRole::AREA_COORDINATOR->value);
    $areaCoordinator->campaigns()->attach($this->campaign->id);

    Livewire::test(EditAreaCoordinator::class, ['record' => $areaCoordinator->getRouteKey()])
        ->assertActionVisible(metadataAssignmentSectionAction());

    Livewire::test(CreateAreaCoordinator::class)
        ->assertActionDoesNotExist(metadataAssignmentSectionAction());
});

it('shows the metadata section on the generic user edit form', function () {
    $plainUser = User::factory()->create([
        'document_number' => (string) fake()->unique()->numerify('##########'),
        'phone' => fake()->numerify('3#########'),
    ]);
    $plainUser->campaigns()->attach($this->campaign->id);

    Livewire::test(EditUser::class, ['record' => $plainUser->getRouteKey()])
        ->assertActionVisible(metadataAssignmentSectionAction());

    Livewire::test(CreateUser::class)
        ->assertActionDoesNotExist(metadataAssignmentSectionAction());

    $key = MetadataKey::factory()->create(['type' => 'text', 'is_active' => true]);

    Livewire::test(EditUser::class, ['record' => $plainUser->getRouteKey()])
        ->callAction(
            metadataAssignmentSectionAction(),
            ['metadata_key_id' => $key->id, 'value' => 'nota libre'],
        );

    $row = UserMetadataValue::where('user_id', $plainUser->id)->where('metadata_key_id', $key->id)->first();

    expect($row)->not->toBeNull()
        ->and($row->value)->toBe('nota libre')
        ->and($row->assigned_by)->toBe($this->superAdmin->id);
});

it('displays only the current value per key with its assigner and timestamp', function () {
    $coordinator = makeSectionCoordinator($this->campaign);
    $key = MetadataKey::factory()->create(['type' => 'numeric', 'is_active' => true]);

    Livewire::test(EditCoordinator::class, ['record' => $coordinator->getRouteKey()])
        ->callAction(metadataAssignmentSectionAction(), ['metadata_key_id' => $key->id, 'value' => '100'])
        ->callAction(metadataAssignmentSectionAction(), ['metadata_key_id' => $key->id, 'value' => '200']);

    $currentValues = app(MetadataAssignmentService::class)->currentValues($coordinator);

    expect($currentValues)->toHaveCount(1)
        ->and($currentValues[$key->id]->value)->toBe('200')
        ->and($currentValues[$key->id]->assigned_by)->toBe($this->superAdmin->id)
        ->and($currentValues[$key->id]->assigned_at)->not->toBeNull();

    expect(UserMetadataValue::where('user_id', $coordinator->id)->where('metadata_key_id', $key->id)->count())->toBe(2);
});

it('excludes deactivated keys from the assignment options', function () {
    $inactiveKey = MetadataKey::factory()->create(['is_active' => false]);

    expect(app(MetadataAssignmentService::class)->activeKeyOptions())->not->toHaveKey($inactiveKey->id);
});

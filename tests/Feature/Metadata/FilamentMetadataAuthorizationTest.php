<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Resources\Coordinators\Pages\EditCoordinator;
use App\Filament\Resources\Coordinators\Pages\ListCoordinators;
use App\Models\Campaign;
use App\Models\MetadataKey;
use App\Models\User;
use App\Models\UserMetadataValue;
use App\Services\CampaignContext;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    collect(UserRole::values())->each(
        fn ($role) => Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])
    );

    $this->campaign = Campaign::factory()->create(['status' => 'active']);
    CampaignContext::setCampaignId($this->campaign->id);
});

afterEach(function () {
    CampaignContext::setCampaignId(null);
});

it('denies a reviewer the individual assignMetadata action on the admin panel', function () {
    $reviewer = User::factory()->create([
        'document_number' => (string) fake()->unique()->numerify('##########'),
        'phone' => fake()->numerify('3#########'),
    ]);
    $reviewer->assignRole(UserRole::REVIEWER->value);
    $reviewer->campaigns()->attach($this->campaign->id);

    $coordinator = User::factory()->create([
        'document_number' => (string) fake()->unique()->numerify('##########'),
        'phone' => fake()->numerify('3#########'),
    ]);
    $coordinator->assignRole(UserRole::COORDINATOR->value);
    $coordinator->campaigns()->attach($this->campaign->id);

    $key = MetadataKey::factory()->create(['type' => 'numeric', 'is_active' => true]);

    actingAs($reviewer);

    $action = TestAction::make('assignMetadata')->schemaComponent('metadataAssignmentSection');
    $component = Livewire::test(EditCoordinator::class, ['record' => $coordinator->getRouteKey()]);
    $component->assertActionDoesNotExist($action);

    $threw = false;

    try {
        $component->callAction($action, ['metadata_key_id' => $key->id, 'value' => '100']);
    } catch (\Throwable) {
        $threw = true;
    }

    expect($threw)->toBeTrue();
    expect(UserMetadataValue::count())->toBe(0);
});

it('denies a reviewer the bulk assignMetadata action on the admin panel', function () {
    $reviewer = User::factory()->create([
        'document_number' => (string) fake()->unique()->numerify('##########'),
        'phone' => fake()->numerify('3#########'),
    ]);
    $reviewer->assignRole(UserRole::REVIEWER->value);
    $reviewer->campaigns()->attach($this->campaign->id);

    $coordinators = collect(range(1, 2))->map(function () {
        $coordinator = User::factory()->create([
            'document_number' => (string) fake()->unique()->numerify('##########'),
            'phone' => fake()->numerify('3#########'),
        ]);
        $coordinator->assignRole(UserRole::COORDINATOR->value);
        $coordinator->campaigns()->attach($this->campaign->id);

        return $coordinator;
    });

    $key = MetadataKey::factory()->create(['type' => 'numeric', 'is_active' => true]);

    actingAs($reviewer);

    Livewire::test(ListCoordinators::class)
        ->callTableBulkAction('assignMetadata', $coordinators, [
            'metadata_key_id' => $key->id,
            'value' => '50',
        ]);

    expect(UserMetadataValue::where('metadata_key_id', $key->id)->count())->toBe(0);
});

it('still lets an admin_campaign assign metadata individually and in bulk on the admin panel', function () {
    $adminCampaign = User::factory()->create([
        'document_number' => (string) fake()->unique()->numerify('##########'),
        'phone' => fake()->numerify('3#########'),
    ]);
    $adminCampaign->assignRole(UserRole::ADMIN_CAMPAIGN->value);
    $adminCampaign->campaigns()->attach($this->campaign->id);

    $individualCoordinator = User::factory()->create([
        'document_number' => (string) fake()->unique()->numerify('##########'),
        'phone' => fake()->numerify('3#########'),
    ]);
    $individualCoordinator->assignRole(UserRole::COORDINATOR->value);
    $individualCoordinator->campaigns()->attach($this->campaign->id);

    $bulkCoordinators = collect(range(1, 2))->map(function () {
        $coordinator = User::factory()->create([
            'document_number' => (string) fake()->unique()->numerify('##########'),
            'phone' => fake()->numerify('3#########'),
        ]);
        $coordinator->assignRole(UserRole::COORDINATOR->value);
        $coordinator->campaigns()->attach($this->campaign->id);

        return $coordinator;
    });

    $key = MetadataKey::factory()->create(['type' => 'numeric', 'is_active' => true]);

    actingAs($adminCampaign);

    $action = TestAction::make('assignMetadata')->schemaComponent('metadataAssignmentSection');

    Livewire::test(EditCoordinator::class, ['record' => $individualCoordinator->getRouteKey()])
        ->callAction($action, ['metadata_key_id' => $key->id, 'value' => '100']);

    $individualRow = UserMetadataValue::where('user_id', $individualCoordinator->id)
        ->where('metadata_key_id', $key->id)
        ->first();

    expect($individualRow)->not->toBeNull()
        ->and($individualRow->assigned_by)->toBe($adminCampaign->id);

    Livewire::test(ListCoordinators::class)
        ->callTableBulkAction('assignMetadata', $bulkCoordinators, [
            'metadata_key_id' => $key->id,
            'value' => '75',
        ]);

    expect(
        UserMetadataValue::where('metadata_key_id', $key->id)
            ->where('assigned_by', $adminCampaign->id)
            ->count()
    )->toBe(3);
});

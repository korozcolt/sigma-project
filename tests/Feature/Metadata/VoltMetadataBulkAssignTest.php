<?php

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\MetadataKey;
use App\Models\Municipality;
use App\Models\User;
use App\Models\UserMetadataValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    collect(UserRole::values())->each(
        fn ($role) => Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])
    );

    $this->municipality = Municipality::factory()->create();
    $this->campaign = Campaign::factory()->create(['municipality_id' => $this->municipality->id, 'status' => 'active']);
});

function makeVoltMetadataUser(array $attributes = []): User
{
    $user = User::factory()->create(array_merge([
        'municipality_id' => test()->municipality->id,
        'document_number' => (string) fake()->unique()->numerify('##########'),
        'phone' => fake()->numerify('3#########'),
    ], $attributes));

    $user->campaigns()->attach(test()->campaign->id);

    return $user;
}

it('assigns the same value to several líderes in one bulk action', function () {
    $coordinadorA = makeVoltMetadataUser();
    $coordinadorA->assignRole(UserRole::COORDINATOR->value);

    $leaders = collect(range(1, 3))->map(function () use ($coordinadorA) {
        $leader = makeVoltMetadataUser(['coordinator_user_id' => $coordinadorA->id]);
        $leader->assignRole(UserRole::LEADER->value);

        return $leader;
    });

    $key = MetadataKey::factory()->create(['type' => 'numeric', 'is_active' => true]);

    actingAs($coordinadorA);

    Volt::test('coordinator.leaders')
        ->set('selectedLeaderIds', $leaders->pluck('id')->map(fn ($id) => (string) $id)->all())
        ->set('bulkMetadataKeyId', $key->id)
        ->set('bulkMetadataValue', '30000')
        ->call('assignBulkMetadata')
        ->assertHasNoErrors();

    $rows = UserMetadataValue::where('metadata_key_id', $key->id)->get();

    expect($rows)->toHaveCount(3);

    foreach ($leaders as $leader) {
        $row = $rows->firstWhere('user_id', $leader->id);

        expect($row)->not->toBeNull()
            ->and($row->value)->toBe('30000')
            ->and($row->assigned_by)->toBe($coordinadorA->id);
    }
});

it('ignores selected líderes outside the coordinador team', function () {
    $coordinadorA = makeVoltMetadataUser();
    $coordinadorA->assignRole(UserRole::COORDINATOR->value);

    $coordinadorB = makeVoltMetadataUser();
    $coordinadorB->assignRole(UserRole::COORDINATOR->value);

    $ownLeaderOne = makeVoltMetadataUser(['coordinator_user_id' => $coordinadorA->id]);
    $ownLeaderOne->assignRole(UserRole::LEADER->value);

    $ownLeaderTwo = makeVoltMetadataUser(['coordinator_user_id' => $coordinadorA->id]);
    $ownLeaderTwo->assignRole(UserRole::LEADER->value);

    $foreignLeader = makeVoltMetadataUser(['coordinator_user_id' => $coordinadorB->id]);
    $foreignLeader->assignRole(UserRole::LEADER->value);

    $key = MetadataKey::factory()->create(['type' => 'text', 'is_active' => true]);

    actingAs($coordinadorA);

    Volt::test('coordinator.leaders')
        ->set('selectedLeaderIds', [
            (string) $ownLeaderOne->id,
            (string) $ownLeaderTwo->id,
            (string) $foreignLeader->id,
        ])
        ->set('bulkMetadataKeyId', $key->id)
        ->set('bulkMetadataValue', 'zona-norte')
        ->call('assignBulkMetadata')
        ->assertHasNoErrors();

    $rows = UserMetadataValue::where('metadata_key_id', $key->id)->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('user_id')->sort()->values()->all())
        ->toBe(collect([$ownLeaderOne->id, $ownLeaderTwo->id])->sort()->values()->all());

    expect(UserMetadataValue::where('user_id', $foreignLeader->id)->count())->toBe(0);
});

it('rejects a bulk assignment with nothing selected', function () {
    $coordinadorA = makeVoltMetadataUser();
    $coordinadorA->assignRole(UserRole::COORDINATOR->value);

    $key = MetadataKey::factory()->create(['type' => 'text', 'is_active' => true]);

    actingAs($coordinadorA);

    Volt::test('coordinator.leaders')
        ->set('selectedLeaderIds', [])
        ->set('bulkMetadataKeyId', $key->id)
        ->set('bulkMetadataValue', 'algo')
        ->call('assignBulkMetadata')
        ->assertHasErrors(['selectedLeaderIds']);

    expect(UserMetadataValue::count())->toBe(0);
});

it('rejects a bulk assignment against an inactive or unknown key', function () {
    $coordinadorA = makeVoltMetadataUser();
    $coordinadorA->assignRole(UserRole::COORDINATOR->value);

    $ownLeader = makeVoltMetadataUser(['coordinator_user_id' => $coordinadorA->id]);
    $ownLeader->assignRole(UserRole::LEADER->value);

    $inactiveKey = MetadataKey::factory()->create(['type' => 'text', 'is_active' => false]);

    actingAs($coordinadorA);

    Volt::test('coordinator.leaders')
        ->set('selectedLeaderIds', [(string) $ownLeader->id])
        ->set('bulkMetadataKeyId', $inactiveKey->id)
        ->set('bulkMetadataValue', 'algo')
        ->call('assignBulkMetadata')
        ->assertHasErrors(['bulkMetadataKeyId']);

    Volt::test('coordinator.leaders')
        ->set('selectedLeaderIds', [(string) $ownLeader->id])
        ->set('bulkMetadataKeyId', 999999)
        ->set('bulkMetadataValue', 'algo')
        ->call('assignBulkMetadata')
        ->assertHasErrors(['bulkMetadataKeyId']);

    expect(UserMetadataValue::count())->toBe(0);
});

it('restricts a select key bulk value to the catalog options', function () {
    $coordinadorA = makeVoltMetadataUser();
    $coordinadorA->assignRole(UserRole::COORDINATOR->value);

    $ownLeader = makeVoltMetadataUser(['coordinator_user_id' => $coordinadorA->id]);
    $ownLeader->assignRole(UserRole::LEADER->value);

    $selectKey = MetadataKey::factory()->create(['type' => 'select', 'options' => ['si', 'no'], 'is_active' => true]);

    actingAs($coordinadorA);

    Volt::test('coordinator.leaders')
        ->set('selectedLeaderIds', [(string) $ownLeader->id])
        ->set('bulkMetadataKeyId', $selectKey->id)
        ->set('bulkMetadataValue', 'quizas')
        ->call('assignBulkMetadata')
        ->assertHasErrors(['bulkMetadataValue']);

    expect(UserMetadataValue::count())->toBe(0);

    Volt::test('coordinator.leaders')
        ->set('selectedLeaderIds', [(string) $ownLeader->id])
        ->set('bulkMetadataKeyId', $selectKey->id)
        ->set('bulkMetadataValue', 'si')
        ->call('assignBulkMetadata')
        ->assertHasNoErrors();

    expect(UserMetadataValue::where('user_id', $ownLeader->id)->where('value', 'si')->count())->toBe(1);
});

it('clears the selection and flashes a confirmation after a bulk assignment', function () {
    $coordinadorA = makeVoltMetadataUser();
    $coordinadorA->assignRole(UserRole::COORDINATOR->value);

    $ownLeader = makeVoltMetadataUser(['coordinator_user_id' => $coordinadorA->id]);
    $ownLeader->assignRole(UserRole::LEADER->value);

    $key = MetadataKey::factory()->create(['type' => 'text', 'is_active' => true]);

    actingAs($coordinadorA);

    $component = Volt::test('coordinator.leaders')
        ->set('selectedLeaderIds', [(string) $ownLeader->id])
        ->set('bulkMetadataKeyId', $key->id)
        ->set('bulkMetadataValue', 'algo')
        ->set('showBulkMetadataModal', true)
        ->call('assignBulkMetadata')
        ->assertHasNoErrors()
        ->assertSet('selectedLeaderIds', [])
        ->assertSet('showBulkMetadataModal', false);

    expect($component->html())->toContain('Metadata asignada a 1 líder(es).');
});

it('selects every líder on the page with the select all control', function () {
    $coordinadorA = makeVoltMetadataUser();
    $coordinadorA->assignRole(UserRole::COORDINATOR->value);

    $leaders = collect(range(1, 3))->map(function () use ($coordinadorA) {
        $leader = makeVoltMetadataUser(['coordinator_user_id' => $coordinadorA->id]);
        $leader->assignRole(UserRole::LEADER->value);

        return $leader;
    });

    actingAs($coordinadorA);

    $component = Volt::test('coordinator.leaders')
        ->set('selectAllOnPage', true);

    $selected = collect($component->get('selectedLeaderIds'))->map(fn ($id) => (int) $id)->sort()->values();

    expect($selected->all())->toBe($leaders->pluck('id')->sort()->values()->all());

    $component->set('selectAllOnPage', false);

    expect($component->get('selectedLeaderIds'))->toBe([]);
});

it('assigns the same value to several coordinadores in one bulk action', function () {
    $articuladorX = makeVoltMetadataUser();
    $articuladorX->assignRole(UserRole::AREA_COORDINATOR->value);

    $coordinators = collect(range(1, 2))->map(function () use ($articuladorX) {
        $coordinator = makeVoltMetadataUser(['area_coordinator_user_id' => $articuladorX->id]);
        $coordinator->assignRole(UserRole::COORDINATOR->value);

        return $coordinator;
    });

    $key = MetadataKey::factory()->create(['type' => 'numeric', 'is_active' => true]);

    actingAs($articuladorX);

    Volt::test('articulador.coordinators')
        ->set('selectedCoordinatorIds', $coordinators->pluck('id')->map(fn ($id) => (string) $id)->all())
        ->set('bulkMetadataKeyId', $key->id)
        ->set('bulkMetadataValue', '50000')
        ->call('assignBulkMetadata')
        ->assertHasNoErrors();

    $rows = UserMetadataValue::where('metadata_key_id', $key->id)->get();

    expect($rows)->toHaveCount(2);

    foreach ($coordinators as $coordinator) {
        $row = $rows->firstWhere('user_id', $coordinator->id);

        expect($row)->not->toBeNull()
            ->and($row->value)->toBe('50000')
            ->and($row->assigned_by)->toBe($articuladorX->id);
    }
});

it('ignores selected coordinadores outside the articulador team', function () {
    $articuladorX = makeVoltMetadataUser();
    $articuladorX->assignRole(UserRole::AREA_COORDINATOR->value);

    $articuladorY = makeVoltMetadataUser();
    $articuladorY->assignRole(UserRole::AREA_COORDINATOR->value);

    $ownCoordinator = makeVoltMetadataUser(['area_coordinator_user_id' => $articuladorX->id]);
    $ownCoordinator->assignRole(UserRole::COORDINATOR->value);

    $foreignCoordinator = makeVoltMetadataUser(['area_coordinator_user_id' => $articuladorY->id]);
    $foreignCoordinator->assignRole(UserRole::COORDINATOR->value);

    $key = MetadataKey::factory()->create(['type' => 'text', 'is_active' => true]);

    actingAs($articuladorX);

    Volt::test('articulador.coordinators')
        ->set('selectedCoordinatorIds', [(string) $ownCoordinator->id, (string) $foreignCoordinator->id])
        ->set('bulkMetadataKeyId', $key->id)
        ->set('bulkMetadataValue', 'zona-sur')
        ->call('assignBulkMetadata')
        ->assertHasNoErrors();

    $rows = UserMetadataValue::where('metadata_key_id', $key->id)->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->user_id)->toBe($ownCoordinator->id);

    expect(UserMetadataValue::where('user_id', $foreignCoordinator->id)->count())->toBe(0);
});

it('does not change the existing líder list scoping', function () {
    $coordinadorA = makeVoltMetadataUser();
    $coordinadorA->assignRole(UserRole::COORDINATOR->value);

    $coordinadorB = makeVoltMetadataUser();
    $coordinadorB->assignRole(UserRole::COORDINATOR->value);

    $ownLeader = makeVoltMetadataUser(['coordinator_user_id' => $coordinadorA->id]);
    $ownLeader->assignRole(UserRole::LEADER->value);

    $foreignLeader = makeVoltMetadataUser(['coordinator_user_id' => $coordinadorB->id]);
    $foreignLeader->assignRole(UserRole::LEADER->value);

    actingAs($coordinadorA);

    Volt::test('coordinator.leaders')
        ->assertSeeText($ownLeader->name)
        ->assertDontSeeText($foreignLeader->name);
});

it('does not change the existing coordinador list scoping', function () {
    $articuladorX = makeVoltMetadataUser();
    $articuladorX->assignRole(UserRole::AREA_COORDINATOR->value);

    $articuladorY = makeVoltMetadataUser();
    $articuladorY->assignRole(UserRole::AREA_COORDINATOR->value);

    $ownCoordinator = makeVoltMetadataUser(['area_coordinator_user_id' => $articuladorX->id]);
    $ownCoordinator->assignRole(UserRole::COORDINATOR->value);

    $foreignCoordinator = makeVoltMetadataUser(['area_coordinator_user_id' => $articuladorY->id]);
    $foreignCoordinator->assignRole(UserRole::COORDINATOR->value);

    actingAs($articuladorX);

    Volt::test('articulador.coordinators')
        ->assertSeeText($ownCoordinator->name)
        ->assertDontSeeText($foreignCoordinator->name);
});

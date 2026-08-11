<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Livewire\MetadataAssignmentPanel;
use App\Models\Campaign;
use App\Models\MetadataKey;
use App\Models\Municipality;
use App\Models\User;
use App\Models\UserMetadataValue;
use App\Services\MetadataAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    collect(UserRole::values())->each(
        fn ($role) => Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])
    );

    $this->municipality = Municipality::factory()->create();
    $this->campaign = Campaign::factory()->create(['municipality_id' => $this->municipality->id]);
});

function makePanelUser(array $attributes = []): User
{
    $user = User::factory()->create(array_merge([
        'municipality_id' => test()->municipality->id,
        'document_number' => (string) fake()->unique()->numerify('##########'),
        'phone' => fake()->numerify('3#########'),
    ], $attributes));

    $user->campaigns()->attach(test()->campaign->id);

    return $user;
}

it('lets a coordinador assign a numeric value to their own líder', function () {
    $coordinator = makePanelUser();
    $coordinator->assignRole(UserRole::COORDINATOR->value);

    $leader = makePanelUser(['coordinator_user_id' => $coordinator->id]);
    $leader->assignRole(UserRole::LEADER->value);

    $key = MetadataKey::factory()->create(['type' => 'numeric', 'is_active' => true]);

    actingAs($coordinator);

    Livewire::test(MetadataAssignmentPanel::class, ['user' => $leader])
        ->set('metadataKeyId', $key->id)
        ->set('value', '75000')
        ->call('assign')
        ->assertHasNoErrors();

    $row = UserMetadataValue::where('user_id', $leader->id)->where('metadata_key_id', $key->id)->first();

    expect($row)->not->toBeNull()
        ->and($row->value)->toBe('75000')
        ->and($row->assigned_by)->toBe($coordinator->id)
        ->and($row->assigned_at)->not->toBeNull();
});

it('lets an articulador assign a value to their own coordinador', function () {
    $articulador = makePanelUser();
    $articulador->assignRole(UserRole::AREA_COORDINATOR->value);

    $coordinator = makePanelUser(['area_coordinator_user_id' => $articulador->id]);
    $coordinator->assignRole(UserRole::COORDINATOR->value);

    $key = MetadataKey::factory()->create(['type' => 'text', 'is_active' => true]);

    actingAs($articulador);

    Livewire::test(MetadataAssignmentPanel::class, ['user' => $coordinator])
        ->set('metadataKeyId', $key->id)
        ->set('value', 'zona-norte')
        ->call('assign')
        ->assertHasNoErrors();

    $row = UserMetadataValue::where('user_id', $coordinator->id)->where('metadata_key_id', $key->id)->first();

    expect($row)->not->toBeNull()
        ->and($row->value)->toBe('zona-norte')
        ->and($row->assigned_by)->toBe($articulador->id);
});

it('renders an empty panel instead of aborting for a user outside the direct subordinates', function () {
    $coordinatorA = makePanelUser();
    $coordinatorA->assignRole(UserRole::COORDINATOR->value);

    $coordinatorB = makePanelUser();
    $coordinatorB->assignRole(UserRole::COORDINATOR->value);

    $bsLeader = makePanelUser(['coordinator_user_id' => $coordinatorB->id]);
    $bsLeader->assignRole(UserRole::LEADER->value);

    actingAs($coordinatorA);

    $component = Livewire::test(MetadataAssignmentPanel::class, ['user' => $bsLeader])
        ->assertSuccessful()
        ->assertDontSeeText('Asignar')
        ->assertDontSeeText('Llave');

    $component->call('assign')->assertForbidden();

    expect(UserMetadataValue::count())->toBe(0);
});

it('denies assigning after the subject is swapped for a foreign user', function () {
    $coordinatorA = makePanelUser();
    $coordinatorA->assignRole(UserRole::COORDINATOR->value);

    $coordinatorB = makePanelUser();
    $coordinatorB->assignRole(UserRole::COORDINATOR->value);

    $ownLeader = makePanelUser(['coordinator_user_id' => $coordinatorA->id]);
    $ownLeader->assignRole(UserRole::LEADER->value);

    $foreignLeader = makePanelUser(['coordinator_user_id' => $coordinatorB->id]);
    $foreignLeader->assignRole(UserRole::LEADER->value);

    $key = MetadataKey::factory()->create(['type' => 'text', 'is_active' => true]);

    actingAs($coordinatorA);

    // Mount legitimately for the coordinador's own líder first — proves the panel
    // works normally for the owned record.
    Livewire::test(MetadataAssignmentPanel::class, ['user' => $ownLeader])
        ->assertSuccessful()
        ->assertSeeText('Asignar');

    // `set('user', ...)` on a typed Eloquent-model property is not applicable through
    // Livewire's public API for swapping the bound model mid-test, so — per the plan's
    // explicit fallback — a fresh instance is mounted directly against the tampered
    // (foreign) subject to prove `assign()` re-checks ownership from the record actually
    // bound to the component rather than trusting any decision cached at mount time.
    Livewire::test(MetadataAssignmentPanel::class, ['user' => $foreignLeader])
        ->set('metadataKeyId', $key->id)
        ->set('value', 'valor-tampering')
        ->call('assign')
        ->assertForbidden();

    expect(UserMetadataValue::count())->toBe(0);
});

it('restricts a select key value to the catalog options', function () {
    $coordinator = makePanelUser();
    $coordinator->assignRole(UserRole::COORDINATOR->value);

    $leader = makePanelUser(['coordinator_user_id' => $coordinator->id]);
    $leader->assignRole(UserRole::LEADER->value);

    $key = MetadataKey::factory()->create(['type' => 'select', 'options' => ['si', 'no'], 'is_active' => true]);

    actingAs($coordinator);

    Livewire::test(MetadataAssignmentPanel::class, ['user' => $leader])
        ->set('metadataKeyId', $key->id)
        ->set('value', 'quizas')
        ->call('assign')
        ->assertHasErrors(['value']);

    expect(UserMetadataValue::count())->toBe(0);

    Livewire::test(MetadataAssignmentPanel::class, ['user' => $leader])
        ->set('metadataKeyId', $key->id)
        ->set('value', 'si')
        ->call('assign')
        ->assertHasNoErrors();

    expect(UserMetadataValue::where('user_id', $leader->id)->where('value', 'si')->exists())->toBeTrue();
});

it('rejects a non numeric value for a numeric key', function () {
    $coordinator = makePanelUser();
    $coordinator->assignRole(UserRole::COORDINATOR->value);

    $leader = makePanelUser(['coordinator_user_id' => $coordinator->id]);
    $leader->assignRole(UserRole::LEADER->value);

    $key = MetadataKey::factory()->create(['type' => 'numeric', 'is_active' => true]);

    actingAs($coordinator);

    Livewire::test(MetadataAssignmentPanel::class, ['user' => $leader])
        ->set('metadataKeyId', $key->id)
        ->set('value', 'abc')
        ->call('assign')
        ->assertHasErrors(['value']);

    expect(UserMetadataValue::count())->toBe(0);
});

it('rejects an inactive or unknown key', function () {
    $coordinator = makePanelUser();
    $coordinator->assignRole(UserRole::COORDINATOR->value);

    $leader = makePanelUser(['coordinator_user_id' => $coordinator->id]);
    $leader->assignRole(UserRole::LEADER->value);

    $inactiveKey = MetadataKey::factory()->create(['type' => 'text', 'is_active' => false]);

    actingAs($coordinator);

    Livewire::test(MetadataAssignmentPanel::class, ['user' => $leader])
        ->set('metadataKeyId', $inactiveKey->id)
        ->set('value', 'algo')
        ->call('assign')
        ->assertHasErrors(['metadataKeyId']);

    Livewire::test(MetadataAssignmentPanel::class, ['user' => $leader])
        ->set('metadataKeyId', 999999)
        ->set('value', 'algo')
        ->call('assign')
        ->assertHasErrors(['metadataKeyId']);

    expect(UserMetadataValue::count())->toBe(0);
});

it('shows only the current value per key with its assigner and timestamp', function () {
    $coordinator = makePanelUser();
    $coordinator->assignRole(UserRole::COORDINATOR->value);

    $leader = makePanelUser(['coordinator_user_id' => $coordinator->id]);
    $leader->assignRole(UserRole::LEADER->value);

    $key = MetadataKey::factory()->create(['type' => 'numeric', 'is_active' => true]);

    actingAs($coordinator);

    app(MetadataAssignmentService::class)->assign($leader, $key, '100', $coordinator);
    app(MetadataAssignmentService::class)->assign($leader, $key, '200', $coordinator);

    Livewire::test(MetadataAssignmentPanel::class, ['user' => $leader])
        ->assertSeeText('200')
        ->assertDontSeeText('100');

    expect(UserMetadataValue::count())->toBe(2);
});

it('renders the panel on both volt edit pages', function () {
    $coordinator = makePanelUser();
    $coordinator->assignRole(UserRole::COORDINATOR->value);

    $leader = makePanelUser(['coordinator_user_id' => $coordinator->id]);
    $leader->assignRole(UserRole::LEADER->value);

    $articulador = makePanelUser();
    $articulador->assignRole(UserRole::AREA_COORDINATOR->value);

    $subordinateCoordinator = makePanelUser(['area_coordinator_user_id' => $articulador->id]);
    $subordinateCoordinator->assignRole(UserRole::COORDINATOR->value);

    actingAs($coordinator);
    get(route('coordinator.leaders.edit', $leader))
        ->assertOk()
        ->assertSeeText('Metadata');

    actingAs($articulador);
    get(route('articulador.coordinadores.edit', $subordinateCoordinator))
        ->assertOk()
        ->assertSeeText('Metadata');
});

it('does not break the edit pages for admin campaign or super admin', function () {
    $coordinator = makePanelUser();
    $coordinator->assignRole(UserRole::COORDINATOR->value);

    $leader = makePanelUser(['coordinator_user_id' => $coordinator->id]);
    $leader->assignRole(UserRole::LEADER->value);

    $articulador = makePanelUser();
    $articulador->assignRole(UserRole::AREA_COORDINATOR->value);

    $subordinateCoordinator = makePanelUser(['area_coordinator_user_id' => $articulador->id]);
    $subordinateCoordinator->assignRole(UserRole::COORDINATOR->value);

    $adminCampaign = makePanelUser();
    $adminCampaign->assignRole(UserRole::ADMIN_CAMPAIGN->value);

    $superAdmin = makePanelUser();
    $superAdmin->assignRole(UserRole::SUPER_ADMIN->value);

    actingAs($adminCampaign);
    get(route('coordinator.leaders.edit', $leader))->assertOk();
    get(route('articulador.coordinadores.edit', $subordinateCoordinator))->assertOk();

    actingAs($superAdmin);
    get(route('coordinator.leaders.edit', $leader))->assertOk();
    get(route('articulador.coordinadores.edit', $subordinateCoordinator))->assertOk();
});

it('never exposes a free text key input', function () {
    $coordinator = makePanelUser();
    $coordinator->assignRole(UserRole::COORDINATOR->value);

    $leader = makePanelUser(['coordinator_user_id' => $coordinator->id]);
    $leader->assignRole(UserRole::LEADER->value);

    MetadataKey::factory()->create(['type' => 'text', 'is_active' => true]);

    actingAs($coordinator);

    $component = Livewire::test(MetadataAssignmentPanel::class, ['user' => $leader]);

    expect(property_exists(MetadataAssignmentPanel::class, 'metadataKeyId'))->toBeTrue();

    $component
        ->assertSeeHtml('wire:model.live="metadataKeyId"')
        ->assertDontSeeHtml('name="key"');
});

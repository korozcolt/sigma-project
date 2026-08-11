<?php

// Nota: phpunit.xml usa sqlite :memory: (conexión única). No es posible simular escrituras concurrentes reales; estos tests cubren META-06 mediante determinismo de orden, independencia por llave y ruta de escritura append-only.

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\MetadataKey;
use App\Models\User;
use App\Models\UserMetadataValue;
use App\Services\MetadataAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Session;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    collect(UserRole::values())->each(
        fn ($role) => Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])
    );

    $this->campaign = Campaign::factory()->create(['status' => 'active']);
});

function actingAsInMetadataCampaign(User $user): void
{
    test()->actingAs($user);
    Session::put('campaign_context.campaign_id', test()->campaign->id);
    Session::put('campaign_context.mode', 'single');
}

function makeMetadataUser(array $attributes = []): User
{
    $user = User::factory()->create(array_merge([
        'document_number' => (string) fake()->unique()->numerify('##########'),
        'phone' => fake()->numerify('3#########'),
    ], $attributes));

    $user->campaigns()->attach(test()->campaign->id);

    return $user;
}

it('resolves only a coordinador direct leaders', function () {
    $coordinadorA = makeMetadataUser();
    $coordinadorA->assignRole(UserRole::COORDINATOR->value);

    $coordinadorB = makeMetadataUser();
    $coordinadorB->assignRole(UserRole::COORDINATOR->value);

    $leaderA1 = makeMetadataUser(['coordinator_user_id' => $coordinadorA->id]);
    $leaderA1->assignRole(UserRole::LEADER->value);

    $leaderA2 = makeMetadataUser(['coordinator_user_id' => $coordinadorA->id]);
    $leaderA2->assignRole(UserRole::LEADER->value);

    $leaderB1 = makeMetadataUser(['coordinator_user_id' => $coordinadorB->id]);
    $leaderB1->assignRole(UserRole::LEADER->value);

    actingAsInMetadataCampaign($coordinadorA);

    $ids = app(MetadataAssignmentService::class)->directSubordinatesQuery($coordinadorA)->pluck('id');

    expect($ids)->toHaveCount(2)
        ->and($ids->contains($leaderA1->id))->toBeTrue()
        ->and($ids->contains($leaderA2->id))->toBeTrue()
        ->and($ids->contains($leaderB1->id))->toBeFalse();
});

it('resolves only an articulador direct coordinadores', function () {
    $articuladorX = makeMetadataUser();
    $articuladorX->assignRole(UserRole::AREA_COORDINATOR->value);

    $coordinadorX1 = makeMetadataUser(['area_coordinator_user_id' => $articuladorX->id]);
    $coordinadorX1->assignRole(UserRole::COORDINATOR->value);

    $coordinadorX2 = makeMetadataUser(['area_coordinator_user_id' => $articuladorX->id]);
    $coordinadorX2->assignRole(UserRole::COORDINATOR->value);

    $unrelatedCoordinador = makeMetadataUser();
    $unrelatedCoordinador->assignRole(UserRole::COORDINATOR->value);

    actingAsInMetadataCampaign($articuladorX);

    $ids = app(MetadataAssignmentService::class)->directSubordinatesQuery($articuladorX)->pluck('id');

    expect($ids)->toHaveCount(2)
        ->and($ids->contains($coordinadorX1->id))->toBeTrue()
        ->and($ids->contains($coordinadorX2->id))->toBeTrue()
        ->and($ids->contains($unrelatedCoordinador->id))->toBeFalse();
});

it('resolves any campaign user for super admin and admin campaign', function () {
    $superAdmin = makeMetadataUser();
    $superAdmin->assignRole(UserRole::SUPER_ADMIN->value);

    $adminCampaign = makeMetadataUser();
    $adminCampaign->assignRole(UserRole::ADMIN_CAMPAIGN->value);

    makeMetadataUser();
    makeMetadataUser();

    actingAsInMetadataCampaign($superAdmin);
    $service = app(MetadataAssignmentService::class);

    expect($service->directSubordinatesQuery($superAdmin)->count())->toBe(User::query()->count());

    actingAsInMetadataCampaign($adminCampaign);

    expect($service->directSubordinatesQuery($adminCampaign)->count())->toBe(User::query()->count());
});

it('resolves nothing for a leader', function () {
    $leader = makeMetadataUser();
    $leader->assignRole(UserRole::LEADER->value);

    actingAsInMetadataCampaign($leader);

    expect(app(MetadataAssignmentService::class)->directSubordinatesQuery($leader)->count())->toBe(0);
});

it('refuses to assign to a user outside the direct subordinates', function () {
    $coordinadorA = makeMetadataUser();
    $coordinadorA->assignRole(UserRole::COORDINATOR->value);

    $coordinadorB = makeMetadataUser();
    $coordinadorB->assignRole(UserRole::COORDINATOR->value);

    $ownLeader = makeMetadataUser(['coordinator_user_id' => $coordinadorA->id]);
    $ownLeader->assignRole(UserRole::LEADER->value);

    $coordinadorBsLeader = makeMetadataUser(['coordinator_user_id' => $coordinadorB->id]);
    $coordinadorBsLeader->assignRole(UserRole::LEADER->value);

    actingAsInMetadataCampaign($coordinadorA);
    $service = app(MetadataAssignmentService::class);

    expect($service->canAssignTo($coordinadorA, $coordinadorBsLeader))->toBeFalse()
        ->and($service->canAssignTo($coordinadorA, $ownLeader))->toBeTrue();
});

it('excludes inactive keys from the catalog lookup', function () {
    $active = MetadataKey::factory()->create(['is_active' => true]);
    $inactive = MetadataKey::factory()->create(['is_active' => false]);

    $service = app(MetadataAssignmentService::class);

    expect($service->activeKeyOptions())->toHaveKey($active->id)
        ->and($service->activeKeyOptions())->not->toHaveKey($inactive->id)
        ->and($service->findActiveKey($inactive->id))->toBeNull()
        ->and($service->findActiveKey(999999))->toBeNull();
});

it('rejects assignment against an inactive key', function () {
    $user = makeMetadataUser();
    $admin = makeMetadataUser();
    $inactiveKey = MetadataKey::factory()->create(['is_active' => false, 'type' => 'text']);

    $service = app(MetadataAssignmentService::class);

    expect(fn () => $service->assign($user, $inactiveKey, '5', $admin))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects a select value outside the key options', function () {
    $user = makeMetadataUser();
    $admin = makeMetadataUser();
    $selectKey = MetadataKey::factory()->create(['type' => 'select', 'options' => ['si', 'no'], 'is_active' => true]);
    $numericKey = MetadataKey::factory()->create(['type' => 'numeric', 'is_active' => true]);

    $service = app(MetadataAssignmentService::class);

    expect(fn () => $service->assign($user, $selectKey, 'quizas', $admin))
        ->toThrow(InvalidArgumentException::class);

    $assigned = $service->assign($user, $selectKey, 'si', $admin);
    expect($assigned->value)->toBe('si');

    expect(fn () => $service->assign($user, $numericKey, 'abc', $admin))
        ->toThrow(InvalidArgumentException::class);
});

it('records who assigned what to whom and when', function () {
    $subject = makeMetadataUser();
    $assigner = makeMetadataUser();
    $key = MetadataKey::factory()->create(['type' => 'numeric', 'is_active' => true]);

    $row = app(MetadataAssignmentService::class)->assign($subject, $key, '150', $assigner);

    expect($row->user_id)->toBe($subject->id)
        ->and($row->metadata_key_id)->toBe($key->id)
        ->and($row->value)->toBe('150')
        ->and($row->assigned_by)->toBe($assigner->id)
        ->and($row->assigned_at)->not->toBeNull()
        ->and($row->assignedByUser->is($assigner))->toBeTrue();
});

it('keeps assignment history append-only', function () {
    $subject = makeMetadataUser();
    $assigner = makeMetadataUser();
    $keyA = MetadataKey::factory()->create(['type' => 'numeric', 'is_active' => true]);

    $service = app(MetadataAssignmentService::class);

    $first = $service->assign($subject, $keyA, '100', $assigner);
    $service->assign($subject, $keyA, '200', $assigner);

    expect(UserMetadataValue::where('user_id', $subject->id)->where('metadata_key_id', $keyA->id)->count())->toBe(2);

    $firstReloaded = UserMetadataValue::find($first->id);

    expect($firstReloaded->value)->toBe('100')
        ->and($firstReloaded->assigned_by)->toBe($first->assigned_by)
        ->and($firstReloaded->assigned_at->eq($first->assigned_at))->toBeTrue();
});

it('resolves the current value deterministically when assigned_at collides', function () {
    $subject = makeMetadataUser();
    $assigner = makeMetadataUser();
    $key = MetadataKey::factory()->create(['type' => 'text', 'is_active' => true]);

    $collidingTimestamp = Carbon::parse('2026-08-10 12:00:00');

    UserMetadataValue::create([
        'user_id' => $subject->id,
        'metadata_key_id' => $key->id,
        'value' => 'primero',
        'assigned_by' => $assigner->id,
        'assigned_at' => $collidingTimestamp,
    ]);

    UserMetadataValue::create([
        'user_id' => $subject->id,
        'metadata_key_id' => $key->id,
        'value' => 'segundo',
        'assigned_by' => $assigner->id,
        'assigned_at' => $collidingTimestamp,
    ]);

    $currentValues = app(MetadataAssignmentService::class)->currentValues($subject);

    expect($currentValues[$key->id]->value)->toBe('segundo');
});

it('assigns the same value to many subordinates preserving attribution', function () {
    $coordinador = makeMetadataUser();
    $coordinador->assignRole(UserRole::COORDINATOR->value);

    $leaders = collect(range(1, 3))->map(function () use ($coordinador) {
        $leader = makeMetadataUser(['coordinator_user_id' => $coordinador->id]);
        $leader->assignRole(UserRole::LEADER->value);

        return $leader;
    });

    $key = MetadataKey::factory()->create(['type' => 'text', 'is_active' => true]);

    $count = app(MetadataAssignmentService::class)->assignMany($leaders, $key, 'zona-norte', $coordinador);

    expect($count)->toBe(3);

    $rows = UserMetadataValue::where('metadata_key_id', $key->id)->get();

    expect($rows)->toHaveCount(3);

    foreach ($leaders as $leader) {
        $row = $rows->firstWhere('user_id', $leader->id);

        expect($row)->not->toBeNull()
            ->and($row->value)->toBe('zona-norte')
            ->and($row->assigned_by)->toBe($coordinador->id);
    }
});

it('re-filters client supplied ids through the subordinate scope', function () {
    $coordinadorA = makeMetadataUser();
    $coordinadorA->assignRole(UserRole::COORDINATOR->value);

    $coordinadorB = makeMetadataUser();
    $coordinadorB->assignRole(UserRole::COORDINATOR->value);

    $ownLeader = makeMetadataUser(['coordinator_user_id' => $coordinadorA->id]);
    $ownLeader->assignRole(UserRole::LEADER->value);

    $foreignLeader = makeMetadataUser(['coordinator_user_id' => $coordinadorB->id]);
    $foreignLeader->assignRole(UserRole::LEADER->value);

    actingAsInMetadataCampaign($coordinadorA);

    $result = app(MetadataAssignmentService::class)
        ->subordinatesByIds($coordinadorA, [$ownLeader->id, $foreignLeader->id]);

    expect($result)->toHaveCount(1)
        ->and($result->first()->is($ownLeader))->toBeTrue();
});

it('keeps assignments to different keys independent', function () {
    // Criterio 5 del ROADMAP. Es estructuralmente imposible de violar mientras la escritura sea un INSERT puro con metadata_key_id distinto; este test es la guarda de regresión contra un futuro updateOrCreate, no una prueba de concurrencia.
    $subject = makeMetadataUser();
    $assigner = makeMetadataUser();

    $keyA = MetadataKey::factory()->create(['type' => 'numeric', 'is_active' => true]);
    $keyB = MetadataKey::factory()->create(['type' => 'text', 'is_active' => true]);

    $service = app(MetadataAssignmentService::class);

    $service->assign($subject, $keyA, '100', $assigner);
    $service->assign($subject, $keyB, 'texto', $assigner);
    $service->assign($subject, $keyA, '200', $assigner);

    expect(UserMetadataValue::where('user_id', $subject->id)->count())->toBe(3);

    $currentValues = $service->currentValues($subject);

    expect($currentValues[$keyA->id]->value)->toBe('200')
        ->and($currentValues[$keyB->id]->value)->toBe('texto');
});

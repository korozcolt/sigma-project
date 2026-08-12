<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Resources\AreaCoordinators\Pages\ListAreaCoordinators;
use App\Filament\Resources\Coordinators\Pages\ListCoordinators;
use App\Filament\Resources\Leaders\Pages\ListLeaders;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Campaign;
use App\Models\MetadataKey;
use App\Models\User;
use App\Services\CampaignContext;
use App\Services\MetadataAssignmentService;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    collect(UserRole::values())->each(
        fn ($role) => Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])
    );

    // CampaignContext keeps its selection in private statics that survive across
    // test files in the same process — pin explicitly (mirroring FilamentMetadataBulkActionTest).
    $this->campaign = Campaign::factory()->create(['status' => 'active']);
    CampaignContext::setCampaignId($this->campaign->id);
});

afterEach(function () {
    CampaignContext::setCampaignId(null);
});

function metadataTableSuperAdmin(): User
{
    $user = User::factory()->create([
        'document_number' => '900100200',
        'phone' => '3001234567',
    ]);
    $user->assignRole(UserRole::SUPER_ADMIN->value);
    $user->campaigns()->attach(test()->campaign->id);

    actingAs($user);

    return $user;
}

function metadataTableUser(?string $role = null): User
{
    $user = User::factory()->create([
        'document_number' => (string) fake()->unique()->numerify('##########'),
        'phone' => fake()->numerify('3#########'),
    ]);
    $user->campaigns()->attach(test()->campaign->id);

    if ($role !== null) {
        $user->assignRole($role);
    }

    return $user;
}

it('filters the {table} table by an exact metadata value', function (string $pageClass, ?string $role) {
    metadataTableSuperAdmin();

    $matching = metadataTableUser($role);
    $other = metadataTableUser($role);
    $assigner = metadataTableUser();

    $key = MetadataKey::factory()->create(['type' => 'text', 'is_active' => true]);
    $service = app(MetadataAssignmentService::class);
    $service->assign($matching, $key, 'zona-norte', $assigner);
    $service->assign($other, $key, 'zona-sur', $assigner);

    Livewire::test($pageClass)
        ->filterTable('metadata', ['metadata_key_id' => $key->id, 'value' => 'zona-norte'])
        ->assertCanSeeTableRecords([$matching])
        ->assertCanNotSeeTableRecords([$other]);
})->with([
    'usuarios' => [ListUsers::class, null],
    'coordinadores' => [ListCoordinators::class, UserRole::COORDINATOR->value],
    'líderes' => [ListLeaders::class, UserRole::LEADER->value],
    'articuladores' => [ListAreaCoordinators::class, UserRole::AREA_COORDINATOR->value],
]);

it('exposes the metadata column and filter on the {table} table', function (string $pageClass) {
    metadataTableSuperAdmin();

    $key = MetadataKey::factory()->create(['type' => 'text', 'is_active' => true]);

    Livewire::test($pageClass)
        ->assertTableFilterExists('metadata')
        ->assertTableColumnExists("metadata_{$key->id}");
})->with([
    'usuarios' => [ListUsers::class],
    'coordinadores' => [ListCoordinators::class],
    'líderes' => [ListLeaders::class],
    'articuladores' => [ListAreaCoordinators::class],
]);

it('sorts the usuarios table by a numeric metadata key numerically, not alphabetically', function () {
    metadataTableSuperAdmin();

    $userTwo = metadataTableUser();
    $userTen = metadataTableUser();
    $assigner = metadataTableUser();

    $key = MetadataKey::factory()->create(['type' => 'numeric', 'is_active' => true]);
    $service = app(MetadataAssignmentService::class);
    $service->assign($userTwo, $key, '2', $assigner);
    $service->assign($userTen, $key, '10', $assigner);

    Livewire::test(ListUsers::class)
        ->sortTable("metadata_{$key->id}")
        ->assertCanSeeTableRecords([$userTwo, $userTen], inOrder: true);
});

it('renders the current (latest) metadata value in the dynamic column, not a stale historical one', function () {
    metadataTableSuperAdmin();

    $coordinador = metadataTableUser(UserRole::COORDINATOR->value);
    $assigner = metadataTableUser();

    $key = MetadataKey::factory()->create(['type' => 'text', 'is_active' => true]);
    $service = app(MetadataAssignmentService::class);
    $service->assign($coordinador, $key, 'valor-viejo', $assigner);
    $service->assign($coordinador, $key, 'valor-actual', $assigner);

    Livewire::test(ListCoordinators::class)
        ->assertTableColumnStateSet("metadata_{$key->id}", 'valor-actual', $coordinador->getKey());
});

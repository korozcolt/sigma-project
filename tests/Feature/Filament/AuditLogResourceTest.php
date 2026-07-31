<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    collect(UserRole::values())->each(
        fn ($role) => Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])
    );
});

test('super admin can access the audit log index', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(UserRole::SUPER_ADMIN->value);

    actingAs($superAdmin);
    Session::put('campaign_context.mode', 'all');

    $this->get(AuditLogResource::getUrl('index', panel: 'admin'))->assertOk();
});

test('non-super-admin roles cannot access the audit log index', function (string $role) {
    $user = User::factory()->create();
    $user->assignRole($role);

    actingAs($user);

    $this->get(AuditLogResource::getUrl('index', panel: 'admin'))->assertForbidden();
})->with([
    UserRole::ADMIN_CAMPAIGN->value,
    UserRole::COORDINATOR->value,
    UserRole::REVIEWER->value,
]);

test('super admin can list audit log records', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(UserRole::SUPER_ADMIN->value);
    actingAs($superAdmin);
    Session::put('campaign_context.mode', 'all');

    $logs = AuditLog::factory()->count(3)->create();

    Livewire::test(ListAuditLogs::class)
        ->assertCanSeeTableRecords($logs);
});

test('audit log table filters by user', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(UserRole::SUPER_ADMIN->value);
    actingAs($superAdmin);
    Session::put('campaign_context.mode', 'all');

    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $logA = AuditLog::factory()->create(['user_id' => $userA->id]);
    $logB = AuditLog::factory()->create(['user_id' => $userB->id]);

    Livewire::test(ListAuditLogs::class)
        ->filterTable('user_id', $userA->id)
        ->assertCanSeeTableRecords([$logA])
        ->assertCanNotSeeTableRecords([$logB]);
});

test('audit log table filters by action', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(UserRole::SUPER_ADMIN->value);
    actingAs($superAdmin);
    Session::put('campaign_context.mode', 'all');

    $created = AuditLog::factory()->create(['action' => 'created']);
    $deleted = AuditLog::factory()->create(['action' => 'deleted']);

    Livewire::test(ListAuditLogs::class)
        ->filterTable('action', ['deleted'])
        ->assertCanSeeTableRecords([$deleted])
        ->assertCanNotSeeTableRecords([$created]);
});

test('audit log table filters by created_at date range', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(UserRole::SUPER_ADMIN->value);
    actingAs($superAdmin);
    Session::put('campaign_context.mode', 'all');

    $inRange = AuditLog::factory()->create(['created_at' => now()->subDays(2)]);
    $outOfRange = AuditLog::factory()->create(['created_at' => now()->subDays(10)]);

    Livewire::test(ListAuditLogs::class)
        ->filterTable('created_at', [
            'created_from' => now()->subDays(3)->toDateString(),
            'created_until' => now()->toDateString(),
        ])
        ->assertCanSeeTableRecords([$inRange])
        ->assertCanNotSeeTableRecords([$outOfRange]);
});

test('audit log resource registers only index and view pages', function () {
    expect(array_keys(AuditLogResource::getPages()))->toBe(['index', 'view']);
});

test('audit log table has no edit/delete record actions or bulk delete action', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(UserRole::SUPER_ADMIN->value);
    actingAs($superAdmin);
    Session::put('campaign_context.mode', 'all');

    AuditLog::factory()->create();

    Livewire::test(ListAuditLogs::class)
        ->assertTableActionDoesNotExist('edit')
        ->assertTableActionDoesNotExist('delete')
        ->assertTableBulkActionDoesNotExist('delete');
});

test('super admin can view an audit log with mixed int/string new_values without a 500 error', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(UserRole::SUPER_ADMIN->value);
    actingAs($superAdmin);
    Session::put('campaign_context.mode', 'all');

    $log = AuditLog::factory()->create([
        'old_values' => null,
        'new_values' => [
            'id' => 32,
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'active' => 1,
        ],
    ]);

    $this->get(AuditLogResource::getUrl('view', ['record' => $log], panel: 'admin'))
        ->assertOk()
        ->assertSee('Jane Doe');
});

<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    collect(UserRole::values())->each(function (string $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    });
});

test('super admin bypasses maintenance mode', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(UserRole::SUPER_ADMIN->value);

    try {
        Artisan::call('down');

        $response = $this->actingAs($superAdmin)->get('/admin');

        $response->assertStatus(200);
    } finally {
        Artisan::call('up');
    }
});

test('non-super-admin roles see the maintenance page while it is active', function (string $role) {
    $user = User::factory()->create();
    $user->assignRole($role);

    try {
        Artisan::call('down');

        $response = $this->actingAs($user)->get('/admin');

        $response->assertStatus(503);
    } finally {
        Artisan::call('up');
    }
})->with([
    UserRole::ADMIN_CAMPAIGN->value,
    UserRole::COORDINATOR->value,
    UserRole::LEADER->value,
]);

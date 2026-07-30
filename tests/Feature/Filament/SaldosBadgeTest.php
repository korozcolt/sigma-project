<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\TwoCaptchaBalanceSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => UserRole::SUPER_ADMIN->value, 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => UserRole::ADMIN_CAMPAIGN->value, 'guard_name' => 'web']);

    TwoCaptchaBalanceSnapshot::factory()->create([
        'balance' => 12.5,
        'checked_at' => now(),
    ]);
});

test('a super admin sees the saldos badge on the admin dashboard', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRole::SUPER_ADMIN->value);

    $response = $this->actingAs($user)->get('/admin');

    $response->assertOk();
    $response->assertSee('saldos-badge', false);
});

test('a non-super-admin does not see the saldos badge on the admin dashboard', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRole::ADMIN_CAMPAIGN->value);

    $response = $this->actingAs($user)->get('/admin');

    $response->assertOk();
    $response->assertDontSee('saldos-badge', false);
});

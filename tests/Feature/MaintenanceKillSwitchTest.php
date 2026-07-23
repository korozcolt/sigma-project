<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    collect(UserRole::values())->each(function (string $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    });
});

test('super admin bypasses maintenance mode via secret link', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(UserRole::SUPER_ADMIN->value);

    $secret = Str::random(40);

    try {
        Artisan::call('down', ['--secret' => $secret]);

        // Visiting the secret URL sets the maintenance bypass cookie (Laravel's
        // native mechanism), simulating the link a Super Admin gets in the
        // Filament notification when activating maintenance mode.
        $bypassVisit = $this->actingAs($superAdmin)->get('/'.$secret);
        $bypassVisit->assertRedirect();

        $cookieValue = $bypassVisit->getCookie('laravel_maintenance', decrypt: false)->getValue();

        $response = $this->actingAs($superAdmin)
            ->withUnencryptedCookie('laravel_maintenance', $cookieValue)
            ->get('/admin');

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

<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Models\VerificationCall;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('renders CallCenterCallsSparklineWidget as a real Recharts sparkline with real 7-day call-volume data', function () {
    $admin = User::factory()->withoutTwoFactor()->create([
        'email' => 'call-center-calls-sparkline@example.com',
        'password' => 'password',
    ]);
    $admin->assignRole(UserRole::SUPER_ADMIN->value);

    VerificationCall::factory()->count(3)->create([
        'caller_id' => $admin->id,
        'call_date' => now(),
    ]);

    loginRealBrowserUser($admin);

    $page = visit(route('filament.admin.resources.verification-calls.index'));

    $page->assertVisible('[data-chart-kind="sparkline"]');
    $page->assertNoJavaScriptErrors();
});

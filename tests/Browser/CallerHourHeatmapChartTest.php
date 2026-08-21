<?php

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\User;
use App\Models\VerificationCall;
use App\Models\Voter;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('renders CallerHourHeatmapChart as a real CSS-grid heatmap with a real positioned tooltip on hover', function () {
    $campaign = Campaign::factory()->create(['status' => 'active']);
    $admin = User::factory()->withoutTwoFactor()->create([
        'email' => 'caller-hour-heatmap@example.com',
        'password' => 'password',
    ]);
    $admin->assignRole(UserRole::SUPER_ADMIN->value);
    $admin->campaigns()->attach($campaign->id);

    $caller = User::factory()->create(['name' => 'Encuestador De Prueba']);
    $voter = Voter::factory()->create(['campaign_id' => $campaign->id, 'registered_by' => $admin->id]);

    VerificationCall::factory()->for($voter)->answered()->create([
        'caller_id' => $caller->id,
        'call_date' => now()->setTime(10, 0),
    ]);

    loginRealBrowserUser($admin);

    $page = visit(route('filament.admin.pages.dashboard'));

    foreach (range(1, 12) as $i) {
        $page->script('window.scrollTo(0, document.body.scrollHeight)');
        $page->wait(1);
    }

    $page->assertVisible('[data-chart-kind="heatmap"]');
    // The caller-name row label is always-visible static text, not tooltip-gated.
    $page->assertSee('Encuestador De Prueba');

    // D-17: hovering a specific cell must show a real positioned React tooltip, never a native
    // title= attribute. Target via the data-caller-id/data-hour attributes HeatmapChart.jsx
    // exposes specifically for this (no static per-cell text exists otherwise).
    $page->script(sprintf(
        'document.querySelector(\'[data-chart-kind="heatmap"] [data-caller-id="%d"][data-hour="10"]\')'
        .'.dispatchEvent(new MouseEvent("mousemove", { bubbles: true }))',
        $caller->id
    ));
    $page->wait(1);
    $page->assertSee('Efectividad');
    $page->assertNoJavaScriptErrors();
});

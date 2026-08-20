<?php

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('mounts the React island PoC widget with real rendered content and refreshes it after a real wire:poll tick without remounting or console errors', function () {
    $campaign = Campaign::factory()->create(['status' => 'active']);
    $admin = User::factory()->withoutTwoFactor()->create([
        'email' => 'react-island-poc@example.com',
        'password' => 'password',
    ]);
    $admin->assignRole(UserRole::SUPER_ADMIN->value);
    $admin->campaigns()->attach($campaign->id);

    loginRealBrowserUser($admin);

    $page = visit(route('filament.admin.pages.dashboard'));

    $page->assertVisible('[data-testid="react-chart-poc"]');

    $firstValue = $page->script("document.querySelector('[data-testid=\"react-chart-poc-value\"]').textContent");

    // ReactIslandPocWidget polls every 10s (protected ?string $pollingInterval = '10s';) —
    // wait past a full tick so a genuine wire:poll -> dispatch('updateChartData') round trip
    // has happened before re-reading the rendered value.
    $page->wait(11);

    $page->assertVisible('[data-testid="react-chart-poc"]');
    $secondValue = $page->script("document.querySelector('[data-testid=\"react-chart-poc-value\"]').textContent");

    expect($secondValue)->not->toBe($firstValue);

    // Confirms the poll tick reused the existing React root via root.render() rather than
    // ever calling createRoot() a second time on the same container — a duplicate-root
    // call logs a distinctive console error this assertion would catch (PITFALLS.md
    // Pitfall 1's documented warning signature).
    $page->assertNoJavaScriptErrors();
});

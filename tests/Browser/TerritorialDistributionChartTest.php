<?php

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Municipality;
use App\Models\User;
use App\Models\Voter;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('renders TerritorialDistributionChart as a real Recharts ranked bar chart with real municipio names', function () {
    $campaign = Campaign::factory()->create(['status' => 'active']);
    $admin = User::factory()->withoutTwoFactor()->create([
        'email' => 'territorial-distribution-chart@example.com',
        'password' => 'password',
    ]);
    $admin->assignRole(UserRole::SUPER_ADMIN->value);
    $admin->campaigns()->attach($campaign->id);

    $municipality = Municipality::factory()->create(['name' => 'Municipio De Prueba']);
    Voter::factory()->count(4)->create([
        'campaign_id' => $campaign->id,
        'registered_by' => $admin->id,
        'municipality_id' => $municipality->id,
    ]);

    loginRealBrowserUser($admin);

    $page = visit(route('filament.admin.pages.dashboard'));

    // TerritorialDistributionChart is a lazy-loaded Filament widget (x-intersect):
    // it only fetches/renders its content once its placeholder scrolls into the
    // viewport. Scroll to the bottom of the (long, many-widget) dashboard so its
    // IntersectionObserver fires, then give the resulting Livewire AJAX round trip
    // time to complete before asserting on its rendered content.
    $page->script('window.scrollTo(0, document.body.scrollHeight)');
    $page->wait(2);

    $page->assertVisible('[data-chart-kind="bar"]');
    $page->assertSee('Municipio De Prueba');
    $page->assertNoJavaScriptErrors();
});

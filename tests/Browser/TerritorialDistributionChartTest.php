<?php

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\User;
use App\Models\Voter;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('renders TerritorialDistributionChart as a real Recharts drill-down treemap with 2-level drill-through', function () {
    $campaign = Campaign::factory()->create(['status' => 'active']);
    $admin = User::factory()->withoutTwoFactor()->create([
        'email' => 'territorial-distribution-treemap@example.com',
        'password' => 'password',
    ]);
    $admin->assignRole(UserRole::SUPER_ADMIN->value);
    $admin->campaigns()->attach($campaign->id);

    $department = Department::factory()->create(['name' => 'Departamento De Prueba']);
    $municipality = Municipality::factory()->create([
        'name' => 'Municipio De Prueba',
        'department_id' => $department->id,
    ]);

    // Pitfall 3: neighborhood_id explicitly null - proves the widget buckets it into "Sin barrio"
    // rather than silently dropping it from the municipio's total (LEFT JOIN, not INNER JOIN).
    Voter::factory()->count(3)->create([
        'campaign_id' => $campaign->id,
        'registered_by' => $admin->id,
        'municipality_id' => $municipality->id,
        'neighborhood_id' => null,
    ]);

    loginRealBrowserUser($admin);

    $page = visit(route('filament.admin.pages.dashboard'));

    // TerritorialDistributionChart is a lazy-loaded (x-intersect) widget sitting early on the
    // dashboard (sort 2) - a single scroll+wait is sufficient, matching this widget's own prior
    // Browser test precedent (unlike the sort-20+ widgets which need repeated scroll ticks).
    $page->script('window.scrollTo(0, document.body.scrollHeight)');
    $page->wait(2);

    $page->assertVisible('[data-chart-kind="treemap"]');
    $page->assertSee('Departamento De Prueba');

    // D-11: click the Departamento tile's rect (the element the click handler is actually bound
    // to, per Recharts' Treemap content-render wiring) to drill into its Municipios.
    $page->script(
        'document.querySelector(\'[data-chart-kind="treemap"] .recharts-treemap-depth-1 rect\')'
        .'.dispatchEvent(new MouseEvent("click", { bubbles: true }))'
    );
    $page->wait(1);
    $page->assertSee('Municipio De Prueba');

    // Drill one level further into Barrios - proves the "Sin barrio" fallback leaf renders for
    // the 3 null-neighborhood voters instead of being silently dropped from the total. Recharts'
    // nest-mode Treemap resets `depth` to 0 for the new root on every click (Treemap.js
    // `handleClick()` -> `computeNode({ depth: 0, ... })`), so the next level's tiles are ALSO
    // rendered under `.recharts-treemap-depth-1`, not a cumulative `.recharts-treemap-depth-2`.
    $page->script(
        'document.querySelector(\'[data-chart-kind="treemap"] .recharts-treemap-depth-1 rect\')'
        .'.dispatchEvent(new MouseEvent("click", { bubbles: true }))'
    );
    $page->wait(1);
    $page->assertSee('Sin barrio');

    $page->assertNoJavaScriptErrors();
});

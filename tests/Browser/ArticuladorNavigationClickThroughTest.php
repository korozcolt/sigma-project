<?php

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('clicks through the Filament panel dashboard, the shared Volt sidebar, and back with correct active-state highlighting on both nav surfaces', function () {
    $campaign = Campaign::factory()->create(['status' => 'active']);
    $areaCoordinator = User::factory()->withoutTwoFactor()->create([
        'email' => 'articulador-nav@example.com',
        'password' => 'password',
    ]);
    $areaCoordinator->assignRole(UserRole::AREA_COORDINATOR->value);
    $areaCoordinator->campaigns()->attach($campaign->id);

    loginRealBrowserUser($areaCoordinator);

    $page = visit(route('filament.area_coordinator.pages.dashboard'));
    $page->assertAttributeContains('li.fi-sidebar-item:has(a[href="'.route('filament.area_coordinator.pages.dashboard').'"])', 'class', 'fi-active');

    // Use the panel's own "Coordinadores" NavigationItem (Filament sidebar, not the Volt one)
    $page->click('a[href="'.route('articulador.coordinadores').'"]');
    $page->assertUrlIs(route('articulador.coordinadores'));

    // Now on a Volt page — the shared sidebar (sidebar.blade.php) is what's rendered here
    $page->assertDataAttribute('a[href="'.route('articulador.coordinadores').'"]', 'current', 'data-current');

    $page->click('a[href="/articulador/dia-d"]');
    $page->assertPathIs('/articulador/dia-d');

    // Back on a Filament page (Día D is a Filament page, so Filament's own sidebar —
    // not the shared Volt one — renders here). The panel's brand logo ALSO links to this
    // same dashboard URL, so scope the click to the actual sidebar nav item's anchor
    // (marked with Filament's own "fi-sidebar-item-btn" class) to avoid a strict-mode
    // multi-element match against the duplicate brand-logo anchor(s).
    $page->click('a.fi-sidebar-item-btn[href="'.route('filament.area_coordinator.pages.dashboard').'"]');
    $page->assertUrlIs(route('filament.area_coordinator.pages.dashboard'));
    $page->assertAttributeContains('li.fi-sidebar-item:has(a[href="'.route('filament.area_coordinator.pages.dashboard').'"])', 'class', 'fi-active');
});

it('marks the shared Volt sidebar\'s Coordinadores item current (not Dashboard) when landing directly on the coordinadores list', function () {
    $campaign = Campaign::factory()->create(['status' => 'active']);
    $areaCoordinator = User::factory()->withoutTwoFactor()->create([
        'email' => 'articulador-nav-2@example.com',
        'password' => 'password',
    ]);
    $areaCoordinator->assignRole(UserRole::AREA_COORDINATOR->value);
    $areaCoordinator->campaigns()->attach($campaign->id);

    loginRealBrowserUser($areaCoordinator);

    $page = visit(route('articulador.coordinadores'));
    // The brand logo shares the same href as the Dashboard nav item on this shared sidebar,
    // so scope the assertion to the actual flux:navlist.item anchor via its data marker.
    $page->assertAttributeMissing('a[data-flux-navlist-item][href="'.route('filament.area_coordinator.pages.dashboard').'"]', 'data-current');
    $page->assertDataAttribute('a[href="'.route('articulador.coordinadores').'"]', 'current', 'data-current');
});

<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Municipality;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    collect(UserRole::values())->each(fn ($role) => Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));

    $this->municipality = Municipality::factory()->create();
    $this->campaign = Campaign::factory()->create([
        'municipality_id' => $this->municipality->id,
        'logo_path' => 'campaign-logos/test-logo.webp',
    ]);
});

test('the articulador panel dashboard links to the coordinadores page', function () {
    $user = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $user->assignRole(UserRole::AREA_COORDINATOR->value);

    $response = $this->actingAs($user)->get('/articulador');

    $response->assertOk();
    $response->assertSee('/articulador/coordinadores', false);
    $response->assertSee('Coordinadores');
});

test('the coordinador panel registers no navigation item pointing at the articulador pages', function () {
    $items = collect(Filament::getPanel('coordinator')->getNavigationItems())
        ->map(fn (NavigationItem $item): ?string => $item->getUrl());

    expect($items)->not->toContain(route('articulador.coordinadores'));
});

test('an articulador sees the articulador sidebar group on the coordinadores page', function () {
    $user = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $user->assignRole(UserRole::AREA_COORDINATOR->value);
    $user->campaigns()->attach($this->campaign->id);

    $response = $this->actingAs($user)->get(route('articulador.coordinadores'));

    $response->assertOk();
    $response->assertSee('Articulación');
    $response->assertSee('Coordinadores');
    $response->assertSee('/articulador/coordinadores', false);
    $response->assertSee('href="'.route('filament.area_coordinator.pages.dashboard').'"', false);
});

test('an articulador does not see the generic Platform sidebar group', function () {
    $user = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $user->assignRole(UserRole::AREA_COORDINATOR->value);
    $user->campaigns()->attach($this->campaign->id);

    $response = $this->actingAs($user)->get(route('articulador.coordinadores'));

    $response->assertOk();
    $response->assertDontSee(__('Platform'));
});

test('the sidebar shows the Articulador role label', function () {
    $user = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $user->assignRole(UserRole::AREA_COORDINATOR->value);
    $user->campaigns()->attach($this->campaign->id);

    $response = $this->actingAs($user)->get(route('articulador.coordinadores'));

    $response->assertOk();
    $response->assertSee('Articulador');
});

test('a coordinador sidebar contains no articulador links', function () {
    $user = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $user->assignRole(UserRole::COORDINATOR->value);
    $user->campaigns()->attach($this->campaign->id);

    $response = $this->actingAs($user)->get(route('coordinator.dashboard'));

    $response->assertOk();
    $response->assertDontSee('/articulador/coordinadores', false);
});

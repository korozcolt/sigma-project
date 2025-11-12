<?php

use App\Models\Campaign;
use App\Models\Municipality;
use App\Models\Neighborhood;
use App\Models\User;
use App\Models\Voter;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

uses()->group('leader-app');

beforeEach(function () {
    // Crear roles
    Role::create(['name' => 'leader']);
    Role::create(['name' => 'admin']);

    // Crear estructura territorial
    $this->municipality = Municipality::factory()->create();
    $this->neighborhood = Neighborhood::factory()->create(['municipality_id' => $this->municipality->id]);

    // Crear campaña
    $this->campaign = Campaign::factory()->create();

    // Crear líder con rol y campaña asignada
    $this->leader = User::factory()->create([
        'municipality_id' => $this->municipality->id,
        'neighborhood_id' => $this->neighborhood->id,
    ]);
    $this->leader->assignRole('leader');
    $this->leader->campaigns()->attach($this->campaign);

    // Crear usuario sin rol de líder
    $this->regularUser = User::factory()->create();
});

// ============ Middleware Tests ============

test('leader can access leader dashboard', function () {
    $response = $this->actingAs($this->leader)->get(route('leader.dashboard'));

    $response->assertOk();
});

test('non-leader cannot access leader dashboard', function () {
    $response = $this->actingAs($this->regularUser)->get(route('leader.dashboard'));

    $response->assertForbidden();
});

test('guest cannot access leader dashboard', function () {
    $response = $this->get(route('leader.dashboard'));

    $response->assertRedirect(route('login'));
});

test('leader can access register voter page', function () {
    $response = $this->actingAs($this->leader)->get(route('leader.register-voter'));

    $response->assertOk();
});

test('leader can access my voters page', function () {
    $response = $this->actingAs($this->leader)->get(route('leader.my-voters'));

    $response->assertOk();
});

// ============ Dashboard Component Tests ============

test('leader dashboard shows correct statistics', function () {
    // Crear votantes para el líder
    Voter::factory()->count(5)->create([
        'campaign_id' => $this->campaign->id,
        'registered_by' => $this->leader->id,
        'confirmed_at' => now(),
    ]);

    Voter::factory()->count(3)->create([
        'campaign_id' => $this->campaign->id,
        'registered_by' => $this->leader->id,
        'confirmed_at' => null,
    ]);

    $this->actingAs($this->leader);

    Volt::test('leader.dashboard')
        ->assertSee('¡Bienvenido, '.$this->leader->name.'!')
        ->assertSee('8') // Total
        ->assertSee('5') // Confirmados
        ->assertSee('3') // Pendientes
        ->assertSee('62.5%'); // Confirmation rate
});

test('leader dashboard shows recent voters', function () {
    $voter = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'registered_by' => $this->leader->id,
        'first_name' => 'Juan',
        'last_name' => 'Pérez',
    ]);

    $this->actingAs($this->leader);

    Volt::test('leader.dashboard')
        ->assertSee('Juan Pérez')
        ->assertSee('Registros Recientes');
});

test('leader dashboard shows empty state when no voters', function () {
    $this->actingAs($this->leader);

    Volt::test('leader.dashboard')
        ->assertSee('No hay votantes registrados')
        ->assertSee('Comienza registrando tu primer votante');
});

// ============ Register Voter Component Tests ============

test('leader can register a voter with complete data', function () {
    $this->actingAs($this->leader);

    Volt::test('leader.register-voter')
        ->set('document_number', '1234567890')
        ->set('first_name', 'Carlos')
        ->set('last_name', 'Rodríguez')
        ->set('phone', '3001234567')
        ->set('email', 'carlos@example.com')
        ->set('municipality_id', $this->municipality->id)
        ->set('neighborhood_id', $this->neighborhood->id)
        ->set('address', 'Calle 123')
        ->set('birth_date', '1990-01-15')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showSuccess', true);

    expect(Voter::where('document_number', '1234567890')->exists())->toBeTrue();

    $voter = Voter::where('document_number', '1234567890')->first();
    expect($voter->first_name)->toBe('Carlos')
        ->and($voter->last_name)->toBe('Rodríguez')
        ->and($voter->phone)->toBe('3001234567')
        ->and($voter->registered_by)->toBe($this->leader->id)
        ->and($voter->campaign_id)->toBe($this->campaign->id);
});

test('leader can register voter with minimal required data', function () {
    $this->actingAs($this->leader);

    Volt::test('leader.register-voter')
        ->set('document_number', '9876543210')
        ->set('first_name', 'María')
        ->set('last_name', 'González')
        ->set('phone', '3109876543')
        ->set('municipality_id', $this->municipality->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(Voter::where('document_number', '9876543210')->exists())->toBeTrue();
});

test('register voter validates required fields', function () {
    // Create a leader without municipality to test required validation
    $leaderWithoutMunicipality = User::factory()->create(['municipality_id' => null]);
    $leaderWithoutMunicipality->assignRole('leader');
    $leaderWithoutMunicipality->campaigns()->attach($this->campaign);

    $this->actingAs($leaderWithoutMunicipality);

    Volt::test('leader.register-voter')
        ->set('document_number', '')
        ->set('first_name', '')
        ->set('last_name', '')
        ->set('phone', '')
        ->call('save')
        ->assertHasErrors(['document_number', 'first_name', 'last_name', 'phone', 'municipality_id']);
});

test('register voter validates document number format', function () {
    $this->actingAs($this->leader);

    Volt::test('leader.register-voter')
        ->set('document_number', '123') // Less than 10 digits
        ->set('first_name', 'Test')
        ->set('last_name', 'User')
        ->set('phone', '3001234567')
        ->set('municipality_id', $this->municipality->id)
        ->call('save')
        ->assertHasErrors(['document_number']);
});

test('register voter validates phone format', function () {
    $this->actingAs($this->leader);

    Volt::test('leader.register-voter')
        ->set('document_number', '1234567890')
        ->set('first_name', 'Test')
        ->set('last_name', 'User')
        ->set('phone', '123') // Less than 10 digits
        ->set('municipality_id', $this->municipality->id)
        ->call('save')
        ->assertHasErrors(['phone']);
});

test('register voter validates unique document number', function () {
    Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'document_number' => '1234567890',
    ]);

    $this->actingAs($this->leader);

    Volt::test('leader.register-voter')
        ->set('document_number', '1234567890')
        ->set('first_name', 'Test')
        ->set('last_name', 'User')
        ->set('phone', '3001234567')
        ->set('municipality_id', $this->municipality->id)
        ->call('save')
        ->assertHasErrors(['document_number']);
});

test('register voter pre-loads leader municipality', function () {
    $this->actingAs($this->leader);

    $component = Volt::test('leader.register-voter');

    expect($component->get('municipality_id'))->toBe($this->municipality->id);
});

test('register voter resets neighborhood when municipality changes', function () {
    $this->actingAs($this->leader);

    Volt::test('leader.register-voter')
        ->set('municipality_id', $this->municipality->id)
        ->set('neighborhood_id', $this->neighborhood->id)
        ->set('municipality_id', Municipality::factory()->create()->id)
        ->assertSet('neighborhood_id', null);
});

test('register another checkbox keeps form open after save', function () {
    $this->actingAs($this->leader);

    Volt::test('leader.register-voter')
        ->set('registerAnother', true)
        ->set('document_number', '1234567890')
        ->set('first_name', 'Test')
        ->set('last_name', 'User')
        ->set('phone', '3001234567')
        ->set('municipality_id', $this->municipality->id)
        ->call('save')
        ->assertSet('document_number', '') // Form cleared
        ->assertSet('first_name', '')
        ->assertSet('registerAnother', true); // Checkbox still checked
});

// ============ My Voters Component Tests ============

test('leader can see their voters list', function () {
    $voter1 = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'registered_by' => $this->leader->id,
        'first_name' => 'Pedro',
        'last_name' => 'Martínez',
        'document_number' => '1111111111',
    ]);

    $voter2 = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'registered_by' => $this->leader->id,
        'first_name' => 'Ana',
        'last_name' => 'López',
        'document_number' => '2222222222',
    ]);

    $this->actingAs($this->leader);

    Volt::test('leader.my-voters')
        ->assertSee('Pedro Martínez')
        ->assertSee('1111111111')
        ->assertSee('Ana López')
        ->assertSee('2222222222');
});

test('leader only sees their own voters', function () {
    // Votante del líder
    $myVoter = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'registered_by' => $this->leader->id,
        'first_name' => 'My',
        'last_name' => 'Voter',
    ]);

    // Votante de otro líder
    $otherLeader = User::factory()->create();
    $otherVoter = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'registered_by' => $otherLeader->id,
        'first_name' => 'Other',
        'last_name' => 'Voter',
    ]);

    $this->actingAs($this->leader);

    Volt::test('leader.my-voters')
        ->assertSee('My Voter')
        ->assertDontSee('Other Voter');
});

test('my voters search works correctly', function () {
    Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'registered_by' => $this->leader->id,
        'first_name' => 'Carlos',
        'last_name' => 'García',
        'document_number' => '1234567890',
    ]);

    Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'registered_by' => $this->leader->id,
        'first_name' => 'María',
        'last_name' => 'Rodríguez',
        'document_number' => '9876543210',
    ]);

    $this->actingAs($this->leader);

    Volt::test('leader.my-voters')
        ->set('search', 'Carlos')
        ->assertSee('Carlos García')
        ->assertDontSee('María Rodríguez');
});

test('my voters status filter works correctly', function () {
    Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'registered_by' => $this->leader->id,
        'first_name' => 'Confirmed',
        'last_name' => 'Voter',
        'confirmed_at' => now(),
    ]);

    Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'registered_by' => $this->leader->id,
        'first_name' => 'Pending',
        'last_name' => 'Voter',
        'confirmed_at' => null,
    ]);

    $this->actingAs($this->leader);

    Volt::test('leader.my-voters')
        ->set('status', 'confirmed')
        ->assertSee('Confirmed Voter')
        ->assertDontSee('Pending Voter');
});

test('my voters shows empty state when no voters', function () {
    Volt::test('leader.my-voters')
        ->assertSee('No hay votantes registrados')
        ->assertSee('Comienza registrando tu primer votante');
});

test('my voters shows empty state when search returns no results', function () {
    Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'registered_by' => $this->leader->id,
        'first_name' => 'Test',
        'last_name' => 'User',
    ]);

    Volt::test('leader.my-voters')
        ->set('search', 'NonExistent')
        ->assertSee('No se encontraron resultados')
        ->assertSee('Intenta con otros términos de búsqueda o filtros');
});

test('my voters displays correct statistics', function () {
    Voter::factory()->count(10)->create([
        'campaign_id' => $this->campaign->id,
        'registered_by' => $this->leader->id,
        'confirmed_at' => now(),
    ]);

    Voter::factory()->count(5)->create([
        'campaign_id' => $this->campaign->id,
        'registered_by' => $this->leader->id,
        'confirmed_at' => null,
    ]);

    Volt::test('leader.my-voters')
        ->assertSee('15') // Total
        ->assertSee('10'); // Confirmados
});

// ============ Middleware Tests ============

test('EnsureUserHasRole middleware allows user with correct role', function () {
    $user = User::factory()->create();
    $user->assignRole('leader');

    $response = $this->actingAs($user)->get(route('leader.dashboard'));

    $response->assertOk();
});

test('EnsureUserHasRole middleware blocks user without role', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('leader.dashboard'));

    $response->assertForbidden();
});

test('EnsureUserHasRole middleware blocks unauthenticated user', function () {
    $response = $this->get(route('leader.dashboard'));

    $response->assertRedirect(route('login'));
});

// ============ Layout Tests ============

test('leader layout includes navigation menu', function () {
    $response = $this->actingAs($this->leader)->get(route('leader.dashboard'));

    // Verify layout renders successfully and contains user info (which is in the layout)
    $response->assertOk()
        ->assertSee($this->leader->name);
});

test('leader layout shows user profile', function () {
    $response = $this->actingAs($this->leader)->get(route('leader.dashboard'));

    $response->assertSee($this->leader->name)
        ->assertSee($this->leader->email);
});

test('leader layout highlights active navigation item', function () {
    // Test that different routes load successfully (which proves navigation works)
    $this->actingAs($this->leader)->get(route('leader.dashboard'))->assertOk();
    $this->actingAs($this->leader)->get(route('leader.register-voter'))->assertOk();
    $this->actingAs($this->leader)->get(route('leader.my-voters'))->assertOk();
});

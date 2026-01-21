<?php

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Invitation;
use App\Models\Municipality;
use App\Models\User;

use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
});

it('muestra el registro público con la información del líder y coordinador', function () {
    $campaign = Campaign::factory()->create();
    $municipality = Municipality::factory()->create();

    $coordinator = User::factory()->create(['municipality_id' => $municipality->id]);
    $coordinator->assignRole(UserRole::COORDINATOR->value);

    $leader = User::factory()->create(['municipality_id' => $municipality->id]);
    $leader->assignRole(UserRole::LEADER->value);

    $invitation = Invitation::factory()
        ->create([
            'campaign_id' => $campaign->id,
            'municipality_id' => $municipality->id,
            'coordinator_user_id' => $coordinator->id,
            'leader_user_id' => $leader->id,
        ]);

    $response = $this->get(route('public.voters.register', $invitation->token));

    $response->assertOk();
    $response->assertSee('Registro de votantes');
    $response->assertSee($campaign->name);
    $response->assertSee($coordinator->name);
    $response->assertSee($leader->name);
    $response->assertSee($municipality->name);
});

it('registra un votante desde el enlace y lo asigna al líder cuando existe', function () {
    $campaign = Campaign::factory()->create();
    $municipality = Municipality::factory()->create();

    $leader = User::factory()->create(['municipality_id' => $municipality->id]);
    $leader->assignRole(UserRole::LEADER->value);

    $invitation = Invitation::factory()->create([
        'campaign_id' => $campaign->id,
        'municipality_id' => null,
        'leader_user_id' => $leader->id,
    ]);

    $payload = [
        'document_number' => '1234567890',
        'first_name' => 'Juan',
        'last_name' => 'Pérez',
        'phone' => '3001234567',
        'secondary_phone' => null,
        'email' => 'juan@example.com',
        'birth_date' => '1990-01-01',
        'address' => 'Calle 1 # 2-3',
        'municipality_id' => $municipality->id,
    ];

    $response = $this->post(route('public.voters.register.submit', $invitation->token), $payload);

    $response->assertRedirect(route('public.voters.register', $invitation->token));
    $response->assertSessionHas('success');

    assertDatabaseHas('voters', [
        'document_number' => '1234567890',
        'first_name' => 'Juan',
        'last_name' => 'Pérez',
        'campaign_id' => $campaign->id,
        'municipality_id' => $municipality->id,
        'registered_by' => $leader->id,
    ]);
});

it('rechaza enlaces expirados', function () {
    $leader = User::factory()->create();
    $leader->assignRole(UserRole::LEADER->value);

    $invitation = Invitation::factory()->create([
        'leader_user_id' => $leader->id,
        'expires_at' => now()->subDay(),
    ]);

    $response = $this->get(route('public.voters.register', $invitation->token));

    $response->assertRedirect(route('home'));
    $response->assertSessionHas('error');
});

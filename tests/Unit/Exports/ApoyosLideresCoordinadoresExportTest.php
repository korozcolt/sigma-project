<?php

// Column order contract for ApoyosLideresCoordinadoresExport::map() (D-03 combined CSV plano).
// Plan 04.1-04's implementation of the export MUST return exactly this array shape/order:
//   [0]  id                     - Voter id
//   [1]  document_number        - Voter document_number
//   [2]  full_name              - Voter full name (first_name + last_name)
//   [3]  phone                  - Voter phone
//   [4]  email                  - Voter email
//   [5]  municipality           - Voter municipality name ('N/A' if null)
//   [6]  address                - Voter address
//   [7]  status                 - Voter status label
//   [8]  registered_at          - Voter created_at formatted 'd/m/Y H:i'
//   [9]  lider_nombre           - registeredBy's name if registeredBy is a LEADER, else 'N/A'
//   [10] lider_telefono         - registeredBy's phone if registeredBy is a LEADER, else 'N/A'
//   [11] lider_email            - registeredBy's email if registeredBy is a LEADER, else 'N/A'
//   [12] coordinador_nombre     - the team's coordinator name (leader's coordinator, or the
//                                 registering coordinator directly), else 'N/A'
//   [13] coordinador_telefono   - same rule as above, phone
//   [14] coordinador_email      - same rule as above, email

use App\Enums\UserRole;
use App\Exports\ApoyosLideresCoordinadoresExport;
use App\Models\Campaign;
use App\Models\User;
use App\Models\Voter;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    collect(UserRole::values())->each(function ($role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    });
});

it('filters the query to the given campaign_id', function () {
    $campaign = Campaign::factory()->create();

    $builder = (new ApoyosLideresCoordinadoresExport($campaign->id))->query()->toBase();

    $where = collect($builder->wheres)->first(fn ($w) => isset($w['column']) && $w['column'] === 'campaign_id');

    expect($where)->not->toBeNull();
    expect($where['value'])->toBe($campaign->id);
});

it('maps a voter registered by a LEADER to lider columns and the leader coordinator to coordinador columns', function () {
    $coordinator = User::factory()->create([
        'name' => 'Coordinadora Ana',
        'phone' => '3001111111',
        'email' => 'ana@example.com',
    ]);
    $coordinator->assignRole(UserRole::COORDINATOR->value);

    $leader = User::factory()->create([
        'name' => 'Lider Beto',
        'phone' => '3002222222',
        'email' => 'beto@example.com',
        'coordinator_user_id' => $coordinator->id,
    ]);
    $leader->assignRole(UserRole::LEADER->value);

    $voter = Voter::factory()->create([
        'registered_by' => $leader->id,
    ]);

    $row = (new ApoyosLideresCoordinadoresExport)->map($voter);

    expect($row[9])->toBe('Lider Beto');
    expect($row[10])->toBe('3002222222');
    expect($row[11])->toBe('beto@example.com');
    expect($row[12])->toBe('Coordinadora Ana');
    expect($row[13])->toBe('3001111111');
    expect($row[14])->toBe('ana@example.com');
});

it('maps a voter registered directly by a COORDINATOR to coordinador columns and N/A lider columns', function () {
    $coordinator = User::factory()->create([
        'name' => 'Coordinador Carlos',
        'phone' => '3003333333',
        'email' => 'carlos@example.com',
    ]);
    $coordinator->assignRole(UserRole::COORDINATOR->value);

    $voter = Voter::factory()->create([
        'registered_by' => $coordinator->id,
    ]);

    $row = (new ApoyosLideresCoordinadoresExport)->map($voter);

    expect($row[9])->toBe('N/A');
    expect($row[10])->toBe('N/A');
    expect($row[11])->toBe('N/A');
    expect($row[12])->toBe('Coordinador Carlos');
    expect($row[13])->toBe('3003333333');
    expect($row[14])->toBe('carlos@example.com');
});

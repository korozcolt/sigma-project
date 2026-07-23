<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Enums\VoterStatus;
use App\Filament\Resources\Voters\Pages\EditVoter;
use App\Models\Campaign;
use App\Models\Municipality;
use App\Models\User;
use App\Models\Voter;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

// NOTE: This file scaffolds tests for D-02/D-03/D-10.
// `duplicate_sequence` column and `VoterStatus::DUPLICATE` were implemented in plan 02.1-03
// (sequence-assignment tests below are green). The reassignment action
// (`reassignDuplicateOwner`) is implemented in plan 02.1-08 - the two reassignment-audit
// tests below remain red/erroring until that plan lands.

beforeEach(function () {
    collect(UserRole::values())->each(function ($role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    });

    $this->campaign = Campaign::factory()->create();
    $this->municipality = Municipality::factory()->create();
});

it('assigns duplicate_sequence 0 to the first voter registered with a document_number', function () {
    $voter = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'document_number' => '1111111111',
    ]);

    expect($voter->duplicate_sequence)->toBe(0);
});

it('assigns duplicate_sequence 1 and status DUPLICATE to a second voter registered with the same document_number', function () {
    $first = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'document_number' => '2222222222',
    ]);

    $second = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'document_number' => '2222222222',
    ]);

    expect($first->fresh()->duplicate_sequence)->toBe(0)
        ->and($second->duplicate_sequence)->toBe(1)
        ->and($second->status)->toBe(VoterStatus::DUPLICATE);
});

it('assigns duplicate_sequence 2 to a third voter registered with the same document_number, keeping sequence 0 and 1 untouched', function () {
    $first = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'document_number' => '3333333333',
    ]);

    $second = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'document_number' => '3333333333',
    ]);

    $third = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'document_number' => '3333333333',
    ]);

    expect($third->duplicate_sequence)->toBe(2)
        ->and($third->status)->toBe(VoterStatus::DUPLICATE)
        ->and($first->fresh()->duplicate_sequence)->toBe(0)
        ->and($second->fresh()->duplicate_sequence)->toBe(1);
});

it('the reassignDuplicateOwner action is visible to an admin_campaign on a DUPLICATE voter', function () {
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::ADMIN_CAMPAIGN->value);

    Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'document_number' => '6666666666',
    ]);

    $duplicate = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'document_number' => '6666666666',
    ]);

    actingAs($admin);

    Livewire::test(EditVoter::class, ['record' => $duplicate->id])
        ->assertActionVisible('reassignDuplicateOwner');
});

it('the reassignDuplicateOwner action is hidden when the voter status is not DUPLICATE', function () {
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::ADMIN_CAMPAIGN->value);

    $voter = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'document_number' => '7777777777',
        'status' => VoterStatus::PENDING_REVIEW,
    ]);

    actingAs($admin);

    Livewire::test(EditVoter::class, ['record' => $voter->id])
        ->assertActionHidden('reassignDuplicateOwner');
});

it('the reassignDuplicateOwner action is hidden from leader and coordinator roles even when the voter is DUPLICATE', function () {
    Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'document_number' => '8888888888',
    ]);

    $duplicate = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'document_number' => '8888888888',
    ]);

    $leader = User::factory()->create();
    $leader->assignRole(UserRole::LEADER->value);

    actingAs($leader);

    Livewire::test(EditVoter::class, ['record' => $duplicate->id])
        ->assertActionHidden('reassignDuplicateOwner');

    $coordinator = User::factory()->create();
    $coordinator->assignRole(UserRole::COORDINATOR->value);

    actingAs($coordinator);

    Livewire::test(EditVoter::class, ['record' => $duplicate->id])
        ->assertActionHidden('reassignDuplicateOwner');
});

it('reassigning ownership to the duplicate (sequence-1) row transfers registered_by, flips the sibling to DUPLICATE, and audits both rows', function () {
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::ADMIN_CAMPAIGN->value);

    $original = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'document_number' => '4444444444',
    ]);

    $duplicate = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'document_number' => '4444444444',
    ]);

    actingAs($admin);

    Livewire::test(EditVoter::class, ['record' => $duplicate->id])
        ->callAction('reassignDuplicateOwner', [
            'new_owner_user_id' => $duplicate->registered_by,
            'notes' => 'Se confirmó con el coordinador que este apoyo pertenece al segundo líder.',
        ])
        ->assertHasNoActionErrors();

    expect($duplicate->fresh()->status)->toBe(VoterStatus::PENDING_REVIEW)
        ->and($duplicate->fresh()->registered_by)->toBe($duplicate->registered_by)
        ->and($original->fresh()->status)->toBe(VoterStatus::DUPLICATE);

    assertDatabaseHas('validation_histories', [
        'voter_id' => $duplicate->id,
        'validation_type' => 'duplicate_reassignment',
        'validated_by' => $admin->id,
    ]);

    assertDatabaseHas('validation_histories', [
        'voter_id' => $original->id,
        'validation_type' => 'duplicate_reassignment',
        'validated_by' => $admin->id,
    ]);
});

it('reassigning the owner of a duplicate requires a mandatory note even when a valid new_owner_user_id is submitted', function () {
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::ADMIN_CAMPAIGN->value);

    Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'document_number' => '9999999999',
    ]);

    $duplicate = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'document_number' => '9999999999',
    ]);

    actingAs($admin);

    Livewire::test(EditVoter::class, ['record' => $duplicate->id])
        ->callAction('reassignDuplicateOwner', [
            'new_owner_user_id' => $duplicate->registered_by,
            'notes' => '',
        ])
        ->assertHasActionErrors(['notes' => 'required']);
});

it('reassigning a duplicate never changes duplicate_sequence on any row when the original owner is confirmed as winner (D-10 immutability, no-op ownership)', function () {
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::ADMIN_CAMPAIGN->value);

    $original = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'document_number' => '5555555555',
    ]);

    $duplicate = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'document_number' => '5555555555',
    ]);

    actingAs($admin);

    Livewire::test(EditVoter::class, ['record' => $duplicate->id])
        ->callAction('reassignDuplicateOwner', [
            'new_owner_user_id' => $original->registered_by,
            'notes' => 'Reasignación de prueba para verificar inmutabilidad del sufijo.',
        ])
        ->assertHasNoActionErrors();

    expect($duplicate->fresh()->duplicate_sequence)->toBe(1)
        ->and($original->fresh()->duplicate_sequence)->toBe(0);

    assertDatabaseHas('validation_histories', [
        'voter_id' => $duplicate->id,
        'validation_type' => 'duplicate_reassignment',
        'validated_by' => $admin->id,
    ]);

    assertDatabaseHas('validation_histories', [
        'voter_id' => $original->id,
        'validation_type' => 'duplicate_reassignment',
        'validated_by' => $admin->id,
    ]);
});

it('rejects a new_owner_user_id that does not correspond to any sibling row registered_by', function () {
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::ADMIN_CAMPAIGN->value);

    Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'document_number' => '1010101010',
    ]);

    $duplicate = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'document_number' => '1010101010',
    ]);

    $unrelatedUser = User::factory()->create();

    actingAs($admin);

    Livewire::test(EditVoter::class, ['record' => $duplicate->id])
        ->callAction('reassignDuplicateOwner', [
            'new_owner_user_id' => $unrelatedUser->id,
            'notes' => 'Intento inválido de reasignación a un usuario ajeno a la cédula.',
        ])
        ->assertHasActionErrors(['new_owner_user_id']);
});

it('reassigning ownership from the original (sequence-0) row to the duplicate (sequence-1) row still never changes duplicate_sequence on either row', function () {
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::ADMIN_CAMPAIGN->value);

    $original = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'document_number' => '1212121212',
    ]);

    $duplicate = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'document_number' => '1212121212',
    ]);

    actingAs($admin);

    Livewire::test(EditVoter::class, ['record' => $duplicate->id])
        ->callAction('reassignDuplicateOwner', [
            'new_owner_user_id' => $duplicate->registered_by,
            'notes' => 'La cédula pasa a ser propiedad del segundo líder tras el debate interno.',
        ])
        ->assertHasNoActionErrors();

    expect($duplicate->fresh()->duplicate_sequence)->toBe(1)
        ->and($original->fresh()->duplicate_sequence)->toBe(0);
});

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

it('reassigning the owner of a duplicate requires a mandatory note and writes a validation_histories row with validation_type duplicate_reassignment', function () {
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::ADMIN_CAMPAIGN->value);

    Voter::factory()->create([
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

    // Submitting without a note must fail form validation (D-03: note is mandatory).
    Livewire::test(EditVoter::class, ['record' => $duplicate->id])
        ->callAction('reassignDuplicateOwner', ['notes' => ''])
        ->assertHasActionErrors(['notes' => 'required']);

    // Submitting with a note must succeed and write a fully audited validation_histories row.
    Livewire::test(EditVoter::class, ['record' => $duplicate->id])
        ->callAction('reassignDuplicateOwner', [
            'notes' => 'Se confirmó con el coordinador que este apoyo pertenece al segundo líder.',
        ])
        ->assertHasNoActionErrors();

    expect($duplicate->fresh()->status)->not->toBe(VoterStatus::DUPLICATE);

    assertDatabaseHas('validation_histories', [
        'voter_id' => $duplicate->id,
        'validation_type' => 'duplicate_reassignment',
        'validated_by' => $admin->id,
        'notes' => 'Se confirmó con el coordinador que este apoyo pertenece al segundo líder.',
    ]);
});

it('reassigning a duplicate never changes duplicate_sequence on any row (D-10 immutability)', function () {
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

    $sequenceBefore = $duplicate->duplicate_sequence;

    actingAs($admin);

    Livewire::test(EditVoter::class, ['record' => $duplicate->id])
        ->callAction('reassignDuplicateOwner', [
            'notes' => 'Reasignación de prueba para verificar inmutabilidad del sufijo.',
        ]);

    expect($duplicate->fresh()->duplicate_sequence)->toBe($sequenceBefore)
        ->and($original->fresh()->duplicate_sequence)->toBe(0);
});

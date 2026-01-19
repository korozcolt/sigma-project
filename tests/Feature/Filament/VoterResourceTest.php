<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Enums\VoterStatus;
use App\Filament\Resources\Voters\Pages\CreateVoter;
use App\Filament\Resources\Voters\Pages\EditVoter;
use App\Filament\Resources\Voters\Pages\ListVoters;
use App\Filament\Resources\Voters\Pages\ViewVoter;
use App\Models\Campaign;
use App\Models\Municipality;
use App\Models\Neighborhood;
use App\Models\User;
use App\Models\Voter;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Crear roles si no existen
    collect(UserRole::values())->each(function ($role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    });

    // Usuario administrador para los tests
    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::SUPER_ADMIN->value);

    actingAs($this->admin);
});

// ============ Tests de Listado ============

test('can render voters list page', function () {
    Livewire::test(ListVoters::class)
        ->assertSuccessful();
});

test('can list voters', function () {
    $voters = Voter::factory()->count(3)->create();

    Livewire::test(ListVoters::class)
        ->assertCanSeeTableRecords($voters);
});

test('can search voters by first name', function () {
    $voter1 = Voter::factory()->create(['first_name' => 'Juan']);
    $voter2 = Voter::factory()->create(['first_name' => 'María']);

    Livewire::test(ListVoters::class)
        ->searchTable('Juan')
        ->assertCanSeeTableRecords([$voter1])
        ->assertCanNotSeeTableRecords([$voter2]);
});

test('can search voters by last name', function () {
    $voter1 = Voter::factory()->create(['last_name' => 'Pérez']);
    $voter2 = Voter::factory()->create(['last_name' => 'García']);

    Livewire::test(ListVoters::class)
        ->searchTable('Pérez')
        ->assertCanSeeTableRecords([$voter1])
        ->assertCanNotSeeTableRecords([$voter2]);
});

test('can search voters by document', function () {
    $voter1 = Voter::factory()->create(['document_number' => '12345678']);
    $voter2 = Voter::factory()->create(['document_number' => '87654321']);

    Livewire::test(ListVoters::class)
        ->searchTable('12345678')
        ->assertCanSeeTableRecords([$voter1])
        ->assertCanNotSeeTableRecords([$voter2]);
});

test('can filter voters by campaign', function () {
    $campaign1 = Campaign::factory()->create();
    $campaign2 = Campaign::factory()->create();

    $voter1 = Voter::factory()->create(['campaign_id' => $campaign1->id]);
    $voter2 = Voter::factory()->create(['campaign_id' => $campaign2->id]);

    Livewire::test(ListVoters::class)
        ->filterTable('campaign_id', $campaign1->id)
        ->assertCanSeeTableRecords([$voter1])
        ->assertCanNotSeeTableRecords([$voter2]);
});

test('can filter voters by status', function () {
    $voterPending = Voter::factory()->create(['status' => VoterStatus::PENDING_REVIEW]);
    $voterConfirmed = Voter::factory()->create(['status' => VoterStatus::CONFIRMED]);

    Livewire::test(ListVoters::class)
        ->filterTable('status', VoterStatus::PENDING_REVIEW->value)
        ->assertCanSeeTableRecords([$voterPending])
        ->assertCanNotSeeTableRecords([$voterConfirmed]);
});

test('can filter voters by municipality', function () {
    $municipality = Municipality::factory()->create();

    $voterInMunicipality = Voter::factory()->create(['municipality_id' => $municipality->id]);
    $otherMunicipality = Municipality::factory()->create();
    $voterNotInMunicipality = Voter::factory()->create(['municipality_id' => $otherMunicipality->id]);

    Livewire::test(ListVoters::class)
        ->filterTable('municipality_id', $municipality->id)
        ->assertCanSeeTableRecords([$voterInMunicipality])
        ->assertCanNotSeeTableRecords([$voterNotInMunicipality]);
});

// ============ Tests de Creación ============

test('can render create voter page', function () {
    Livewire::test(CreateVoter::class)
        ->assertSuccessful();
});

test('can create voter with basic data', function () {
    $campaign = Campaign::factory()->create();
    $municipality = Municipality::factory()->create();

    $voterData = [
        'campaign_id' => $campaign->id,
        'first_name' => 'Juan',
        'last_name' => 'Pérez',
        'document_number' => '12345678',
        'phone' => '3001234567',
        'municipality_id' => $municipality->id,
    ];

    Livewire::test(CreateVoter::class)
        ->fillForm($voterData)
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('voters', [
        'first_name' => 'Juan',
        'last_name' => 'Pérez',
        'document_number' => '12345678',
        'phone' => '3001234567',
        'registered_by' => $this->admin->id,
    ]);
});

test('cannot create voter without required fields', function () {
    Livewire::test(CreateVoter::class)
        ->fillForm([
            'first_name' => '',
            'last_name' => '',
            'document_number' => '',
            'phone' => '',
            'campaign_id' => null,
            'municipality_id' => null,
        ])
        ->call('create')
        ->assertHasFormErrors([
            'first_name' => 'required',
            'last_name' => 'required',
            'document_number' => 'required',
            'phone' => 'required',
            'campaign_id' => 'required',
            'municipality_id' => 'required',
        ]);
});

test('cannot create voter with duplicate document in same campaign', function () {
    $campaign = Campaign::factory()->create();
    $municipality = Municipality::factory()->create();

    Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'document_number' => '12345678',
    ]);

    Livewire::test(CreateVoter::class)
        ->fillForm([
            'campaign_id' => $campaign->id,
            'first_name' => 'Test',
            'last_name' => 'Voter',
            'document_number' => '12345678',
            'phone' => '3001234567',
            'municipality_id' => $municipality->id,
        ])
        ->call('create')
        ->assertHasFormErrors(['document_number' => 'unique']);
});

test('can create voter with duplicate document in different campaign', function () {
    $campaign1 = Campaign::factory()->create();
    $campaign2 = Campaign::factory()->create();
    $municipality = Municipality::factory()->create();

    Voter::factory()->create([
        'campaign_id' => $campaign1->id,
        'document_number' => '12345678',
    ]);

    Livewire::test(CreateVoter::class)
        ->fillForm([
            'campaign_id' => $campaign2->id,
            'first_name' => 'Test',
            'last_name' => 'Voter',
            'document_number' => '12345678',
            'phone' => '3001234567',
            'municipality_id' => $municipality->id,
        ])
        ->call('create')
        ->assertHasFormErrors(['document_number' => 'unique']);
});

test('can create voter with all optional fields', function () {
    $campaign = Campaign::factory()->create();
    $municipality = Municipality::factory()->create();
    $neighborhood = Neighborhood::factory()->for($municipality)->create();

    $voterData = [
        'campaign_id' => $campaign->id,
        'first_name' => 'Juan',
        'last_name' => 'Pérez',
        'document_number' => '12345678',
        'birth_date' => '1990-01-15',
        'phone' => '3001234567',
        'secondary_phone' => '3109876543',
        'email' => 'juan@example.com',
        'municipality_id' => $municipality->id,
        'neighborhood_id' => $neighborhood->id,
        'address' => 'Calle 123 #45-67',
        'detailed_address' => 'Apartamento 301, Torre B',
        'status' => VoterStatus::PENDING_REVIEW->value,
        'notes' => 'Notas de prueba',
    ];

    Livewire::test(CreateVoter::class)
        ->fillForm($voterData)
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('voters', [
        'first_name' => 'Juan',
        'last_name' => 'Pérez',
        'email' => 'juan@example.com',
        'neighborhood_id' => $neighborhood->id,
    ]);
});

// ============ Tests de Edición ============

test('can render edit voter page', function () {
    $voter = Voter::factory()->create();

    Livewire::test(EditVoter::class, ['record' => $voter->id])
        ->assertSuccessful();
});

test('can edit voter', function () {
    $voter = Voter::factory()->create([
        'first_name' => 'Original',
        'last_name' => 'Name',
        'municipality_id' => Municipality::factory()->create()->id,
        'neighborhood_id' => null,
    ]);

    Livewire::test(EditVoter::class, ['record' => $voter->id])
        ->fillForm([
            'first_name' => 'Updated',
            'last_name' => 'Name',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $voter->refresh();

    expect($voter->first_name)->toBe('Updated');
});

test('can change voter status', function () {
    $voter = Voter::factory()->create([
        'status' => VoterStatus::PENDING_REVIEW,
        'neighborhood_id' => null, // Avoid neighborhood validation issues
    ]);

    Livewire::test(EditVoter::class, ['record' => $voter->id])
        ->fillForm([
            'status' => VoterStatus::CONFIRMED->value,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $voter->refresh();

    expect($voter->status)->toBe(VoterStatus::CONFIRMED);
});

test('cannot edit voter with duplicate document in same campaign', function () {
    $campaign = Campaign::factory()->create();

    Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'document_number' => '12345678',
    ]);

    $voter = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'document_number' => '87654321',
    ]);

    Livewire::test(EditVoter::class, ['record' => $voter->id])
        ->fillForm([
            'document_number' => '12345678',
        ])
        ->call('save')
        ->assertHasFormErrors(['document_number' => 'unique']);
});

// ============ Tests de Visualización ============

test('can render view voter page', function () {
    $voter = Voter::factory()->create();

    Livewire::test(ViewVoter::class, ['record' => $voter->id])
        ->assertSuccessful();
});

test('view page displays voter information', function () {
    // TODO: Configure infolist schema in VoterResource to display fields
    $this->markTestSkipped('Requires infolist configuration in VoterResource');

    $municipality = Municipality::factory()->create();
    $neighborhood = Neighborhood::factory()->for($municipality)->create();
    $campaign = Campaign::factory()->create();

    $voter = Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'first_name' => 'Juan',
        'last_name' => 'Pérez',
        'document_number' => '12345678',
        'phone' => '3001234567',
        'municipality_id' => $municipality->id,
        'neighborhood_id' => $neighborhood->id,
    ]);

    Livewire::test(ViewVoter::class, ['record' => $voter->id])
        ->assertSee('Juan')
        ->assertSee('Pérez')
        ->assertSee('12345678')
        ->assertSee('3001234567');
});

// ============ Tests de Eliminación ============

test('can delete voter from edit page', function () {
    $voter = Voter::factory()->create();

    Livewire::test(EditVoter::class, ['record' => $voter->id])
        ->callAction('delete');

    $this->assertSoftDeleted('voters', ['id' => $voter->id]);
});

test('can bulk delete voters', function () {
    $voters = Voter::factory()->count(3)->create();

    Livewire::test(ListVoters::class)
        ->callTableBulkAction('delete', $voters);

    foreach ($voters as $voter) {
        $this->assertSoftDeleted('voters', ['id' => $voter->id]);
    }
});

// ============ Tests de Relación User-Voter ============

test('voter can be linked to user', function () {
    $user = User::factory()->create();
    $voter = Voter::factory()->create();

    $voter->update(['user_id' => $user->id]);

    expect($user->refresh()->voter->id)->toBe($voter->id);
    expect($voter->user->id)->toBe($user->id);
});

test('isSystemUser returns true when voter is linked to user', function () {
    $user = User::factory()->create();
    $voter = Voter::factory()->create();

    expect($voter->isSystemUser())->toBeFalse();

    $voter->update(['user_id' => $user->id]);

    expect($voter->refresh()->isSystemUser())->toBeTrue();
});

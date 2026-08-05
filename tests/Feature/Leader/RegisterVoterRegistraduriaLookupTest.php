<?php

use App\Enums\PollingPlaceSource;
use App\Enums\VoterStatus;
use App\Models\Campaign;
use App\Models\Municipality;
use App\Models\NationalIdentityRecord;
use App\Models\Neighborhood;
use App\Models\PollingPlace;
use App\Models\RegistraduriaLookup;
use App\Models\User;
use App\Models\Voter;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

uses()->group('leader-app');

beforeEach(function () {
    Role::create(['name' => 'leader']);
    Role::create(['name' => 'admin']);

    $this->municipality = Municipality::factory()->create();
    $this->neighborhood = Neighborhood::factory()->create(['municipality_id' => $this->municipality->id]);

    $this->campaign = Campaign::factory()->create();

    $this->leader = User::factory()->create([
        'municipality_id' => $this->municipality->id,
        'neighborhood_id' => $this->neighborhood->id,
    ]);
    $this->leader->assignRole('leader');
    $this->leader->campaigns()->attach($this->campaign);
});

function createVerifiedRegistraduriaLookup(string $documentNumber, Municipality $municipality): RegistraduriaLookup
{
    $pollingPlace = PollingPlace::factory()->create([
        'municipality_id' => $municipality->id,
        'name' => 'IE LA CAMPIÑA',
        'max_tables' => 20,
    ]);

    return RegistraduriaLookup::factory()->create([
        'document_number' => $documentNumber,
        'puesto_nombre' => $pollingPlace->name,
        'puesto_codigo' => '',
        'zona_codigo' => '',
        'mesa_numero' => '05',
        'departamento' => 'SUCRE',
        'municipio' => $municipality->name,
        'direccion' => 'CALLE FALSA 123',
    ]);
}

test('blurring a document number confirmed by Registraduría sets registraduriaVerified, autofills fields, and shows the green banner exclusively of the amber warning', function () {
    createVerifiedRegistraduriaLookup('1234567890', $this->municipality);

    $this->actingAs($this->leader);

    Volt::test('leader.register-voter')
        ->set('document_number', '1234567890')
        ->assertSet('registraduriaVerified', true)
        ->assertSet('censusNotFoundWarning', false)
        ->assertSet('polling_table_number', 5)
        ->assertSet('address', 'CALLE FALSA 123')
        ->assertSee('Verificado por Registraduría')
        ->assertDontSee('Esta cédula no aparece en el censo actual, revísala.');
});

test('blurring a document number found only in the national identity roll (not Registraduría) shows no banner at all', function () {
    NationalIdentityRecord::factory()->create([
        'cedula' => '1234567891',
    ]);

    $this->actingAs($this->leader);

    Volt::test('leader.register-voter')
        ->set('document_number', '1234567891')
        ->assertSet('registraduriaVerified', false)
        ->assertSet('censusNotFoundWarning', false)
        ->assertDontSee('Verificado por Registraduría')
        ->assertDontSee('Esta cédula no aparece en el censo actual, revísala.');
});

test('blurring a document number found in neither source shows only the amber warning', function () {
    $this->actingAs($this->leader);

    Volt::test('leader.register-voter')
        ->set('document_number', '1234567892')
        ->assertSet('registraduriaVerified', false)
        ->assertSet('censusNotFoundWarning', true)
        ->assertDontSee('Verificado por Registraduría')
        ->assertSee('Esta cédula no aparece en el censo actual, revísala.');
});

test('saving a cédula present in RegistraduriaLookup persists status VERIFIED_REGISTRADURIA', function () {
    createVerifiedRegistraduriaLookup('1234567893', $this->municipality);

    $this->actingAs($this->leader);

    Volt::test('leader.register-voter')
        ->set('document_number', '1234567893')
        ->set('first_name', 'Test')
        ->set('last_name', 'User')
        ->set('phone', '3001234567')
        ->set('municipality_id', $this->municipality->id)
        ->assertSet('registraduriaVerified', true)
        ->call('save')
        ->assertHasNoErrors();

    $voter = Voter::where('document_number', '1234567893')->first();

    expect($voter)->not->toBeNull()
        ->and($voter->status)->toBe(VoterStatus::VERIFIED_REGISTRADURIA);
});

// status-polling-place-source-desync: polling_place_source must be set to LIVE in the
// same Voter::create() call as status = VERIFIED_REGISTRADURIA, never left null for a
// later cron job to fill in.
test('saving a cédula present in RegistraduriaLookup also persists polling_place_source LIVE', function () {
    createVerifiedRegistraduriaLookup('1234567897', $this->municipality);

    $this->actingAs($this->leader);

    Volt::test('leader.register-voter')
        ->set('document_number', '1234567897')
        ->set('first_name', 'Test')
        ->set('last_name', 'User')
        ->set('phone', '3001234567')
        ->set('municipality_id', $this->municipality->id)
        ->assertSet('registraduriaVerified', true)
        ->call('save')
        ->assertHasNoErrors();

    $voter = Voter::where('document_number', '1234567897')->first();

    expect($voter)->not->toBeNull()
        ->and($voter->status)->toBe(VoterStatus::VERIFIED_REGISTRADURIA)
        ->and($voter->polling_place_source)->toBe(PollingPlaceSource::LIVE)
        ->and($voter->polling_place_resolved_at)->not->toBeNull();
});

test('saving a cédula found only in the national identity roll still persists PENDING_REVIEW', function () {
    NationalIdentityRecord::factory()->create([
        'cedula' => '1234567894',
    ]);

    $this->actingAs($this->leader);

    Volt::test('leader.register-voter')
        ->set('document_number', '1234567894')
        ->set('first_name', 'Test')
        ->set('last_name', 'User')
        ->set('phone', '3001234567')
        ->set('municipality_id', $this->municipality->id)
        ->assertSet('registraduriaVerified', false)
        ->call('save')
        ->assertHasNoErrors();

    $voter = Voter::where('document_number', '1234567894')->first();

    expect($voter)->not->toBeNull()
        ->and($voter->status)->toBe(VoterStatus::PENDING_REVIEW)
        ->and($voter->polling_place_source)->toBeNull();
});

test('saving a cédula found in neither source still persists CENSUS_NOT_FOUND', function () {
    $this->actingAs($this->leader);

    Volt::test('leader.register-voter')
        ->set('document_number', '1234567895')
        ->set('first_name', 'Test')
        ->set('last_name', 'User')
        ->set('phone', '3001234567')
        ->set('municipality_id', $this->municipality->id)
        ->assertSet('registraduriaVerified', false)
        ->assertSet('censusNotFoundWarning', true)
        ->call('save')
        ->assertHasNoErrors();

    $voter = Voter::where('document_number', '1234567895')->first();

    expect($voter)->not->toBeNull()
        ->and($voter->status)->toBe(VoterStatus::CENSUS_NOT_FOUND);
});

// NOTE: This is a regression/safety-net guard for the wire:key fix (260726-kg8) on the
// document-status banner. Livewire::test()'s ->set() calls mutate server-side component
// state directly and never execute the browser's morphdom algorithm, so this test cannot
// reproduce the actual DOM-morph value bleed a real browser blur triggers. Final
// confirmation requires a real browser session (see 260726-kg8 plan notes).
test('first_name, last_name, and birth_date stay independent after a Registraduría-verified document blur (regression guard; does not exercise real browser DOM morphing — see 260726-kg8 plan notes for the manual browser verification this requires)', function () {
    createVerifiedRegistraduriaLookup('1234567896', $this->municipality);

    $this->actingAs($this->leader);

    $component = Volt::test('leader.register-voter')
        ->set('document_number', '1234567896')
        ->assertSet('registraduriaVerified', true);

    $component
        ->set('first_name', 'Ana Maria')
        ->set('last_name', 'Restrepo Gomez')
        ->assertSet('first_name', 'Ana Maria')
        ->assertSet('last_name', 'Restrepo Gomez')
        ->assertSet('birth_date', null);
});

<?php

use App\Enums\VoterStatus;
use App\Models\Campaign;
use App\Models\CensusRecord;
use App\Models\Municipality;
use App\Models\Neighborhood;
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

test('blurring an unknown document number shows the census-not-found warning', function () {
    $this->actingAs($this->leader);

    Volt::test('leader.register-voter')
        ->set('document_number', '1234567890')
        ->assertSet('censusNotFoundWarning', true)
        ->assertSee('Esta cédula no aparece en el censo actual, revísala.');
});

test('blurring a document number found in the local census clears the warning', function () {
    CensusRecord::factory()->create([
        'campaign_id' => $this->campaign->id,
        'document_number' => '1234567890',
    ]);

    $this->actingAs($this->leader);

    Volt::test('leader.register-voter')
        ->set('document_number', '1234567890')
        ->assertSet('censusNotFoundWarning', false)
        ->assertDontSee('Esta cédula no aparece en el censo actual, revísala.');
});

test('an incomplete document number never triggers the census lookup', function () {
    $this->actingAs($this->leader);

    Volt::test('leader.register-voter')
        ->set('document_number', '123')
        ->assertSet('censusNotFoundWarning', false)
        ->assertDontSee('Esta cédula no aparece en el censo actual, revísala.');
});

test('saving despite the census warning persists status census not found', function () {
    $this->actingAs($this->leader);

    Volt::test('leader.register-voter')
        ->set('document_number', '1234567890')
        ->set('first_name', 'Test')
        ->set('last_name', 'User')
        ->set('phone', '3001234567')
        ->set('municipality_id', $this->municipality->id)
        ->assertSet('censusNotFoundWarning', true)
        ->call('save')
        ->assertHasNoErrors();

    $voter = Voter::where('document_number', '1234567890')->first();

    expect($voter)->not->toBeNull()
        ->and($voter->status)->toBe(VoterStatus::CENSUS_NOT_FOUND);
});

test('saving a document number found in the local census persists the default pending review status', function () {
    CensusRecord::factory()->create([
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
        ->assertSet('censusNotFoundWarning', false)
        ->call('save')
        ->assertHasNoErrors();

    $voter = Voter::where('document_number', '1234567890')->first();

    expect($voter)->not->toBeNull()
        ->and($voter->status)->toBe(VoterStatus::PENDING_REVIEW);
});

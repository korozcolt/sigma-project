<?php

use App\Enums\UserRole;
use App\Enums\VoterStatus;
use App\Filament\Widgets\TopPollingPlacesTable;
use App\Models\Campaign;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\PollingPlace;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses()->group('dashboard-widgets');

beforeEach(function () {
    Role::firstOrCreate(['name' => UserRole::SUPER_ADMIN->value, 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole(UserRole::SUPER_ADMIN->value);
    $this->actingAs($user);

    $department = Department::factory()->create();
    $this->municipality = Municipality::factory()->create(['department_id' => $department->id]);

    $this->campaign = Campaign::factory()->create(['status' => 'active']);
    Session::put('campaign_context.campaign_id', $this->campaign->id);
    Session::put('campaign_context.mode', 'single');
});

test('top polling places table excludes DUPLICATE status voters from apoyos_count (D-01)', function () {
    $placeA = PollingPlace::factory()->create(['municipality_id' => $this->municipality->id]);
    $placeB = PollingPlace::factory()->create(['municipality_id' => $this->municipality->id]);

    Voter::factory()->count(2)->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'polling_place_id' => $placeA->id,
    ]);

    Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'polling_place_id' => $placeA->id,
        'document_number' => '9200000001',
        'status' => VoterStatus::DUPLICATE,
        'duplicate_sequence' => 1,
    ]);

    Voter::factory()->count(2)->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'polling_place_id' => $placeB->id,
    ]);

    Livewire::test(TopPollingPlacesTable::class)
        ->assertCanSeeTableRecords([$placeA, $placeB])
        ->assertTableColumnStateSet('apoyos_count', 2, $placeA->getKey())
        ->assertTableColumnStateSet('apoyos_count', 2, $placeB->getKey());
});

test('top polling places table does not break when a voter has no polling_place_id', function () {
    Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'polling_place_id' => null,
    ]);

    Livewire::test(TopPollingPlacesTable::class)
        ->assertOk();
});

test('top polling places table export header action exists', function () {
    Livewire::test(TopPollingPlacesTable::class)
        ->assertTableHeaderActionsExistInOrder(['export']);
});

test('top polling places table shows absolute rank across pages, not per-page-relative rank', function () {
    // 12 polling places with a distinct, descending number of non-duplicate
    // voters each, so ranking order by apoyos_count desc is deterministic:
    // place #1 has 12 voters ... place #12 has 1 voter. Default per-page is
    // 10, so page 1 holds ranks 1-10 and page 2 holds ranks 11-12.
    collect(range(12, 1))->each(function (int $voterCount) {
        $place = PollingPlace::factory()->create(['municipality_id' => $this->municipality->id]);

        Voter::factory()->count($voterCount)->create([
            'campaign_id' => $this->campaign->id,
            'municipality_id' => $this->municipality->id,
            'polling_place_id' => $place->id,
        ]);
    });

    $tenthPlace = PollingPlace::query()->withCount('voters')->orderByDesc('voters_count')->get()->get(9);
    $twelfthPlace = PollingPlace::query()->withCount('voters')->orderByDesc('voters_count')->get()->get(11);

    $component = Livewire::test(TopPollingPlacesTable::class);

    // Filament's `assertTableColumnStateSet()` reads the ranking column's
    // cached `$rowLoop`, which is mutated in place to the LAST rendered row
    // of the page (not the row matching the asserted record). So the value
    // it reflects after a render is always the absolute position of the
    // page's LAST row — used here as an end-to-end check that the widget's
    // real Livewire instance correctly reports its own page/per-page state
    // through `TopPollingPlacesTable::resolveAbsolutePosition()`.
    $component->assertTableColumnStateSet('ranking', 10, $tenthPlace->getKey());

    $component->call('gotoPage', 2)
        ->assertTableColumnStateSet('ranking', 12, $twelfthPlace->getKey());

    // Directly verify the specific regression: the FIRST row of page 2
    // (overall rank 11) must resolve to absolute position 11, not 1.
    $livewire = $component->instance();

    expect(TopPollingPlacesTable::resolveAbsolutePosition($livewire, (object) ['iteration' => 1]))
        ->toBe(11)
        ->not->toBe(1);

    // And the first row of page 1 (overall rank 1) still resolves to 1.
    $component->call('gotoPage', 1);
    $livewire = $component->instance();

    expect(TopPollingPlacesTable::resolveAbsolutePosition($livewire, (object) ['iteration' => 1]))
        ->toBe(1);
});

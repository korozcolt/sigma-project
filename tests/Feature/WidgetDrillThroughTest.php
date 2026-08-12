<?php

use App\Enums\UserRole;
use App\Enums\VoterStatus;
use App\Filament\Resources\Voters\VoterResource;
use App\Filament\Widgets\ApoyosLideresCoordinadoresTable;
use App\Filament\Widgets\CampaignStatsOverview;
use App\Filament\Widgets\DuplicatesReportTable;
use App\Filament\Widgets\FollowUpBacklogOverview;
use App\Filament\Widgets\JurisdictionReportTable;
use App\Filament\Widgets\RejectionsReportTable;
use App\Filament\Widgets\TerritorialOwnershipTable;
use App\Filament\Widgets\TopCoordinatorsTable;
use App\Filament\Widgets\TopLeadersTable;
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
    Role::firstOrCreate(['name' => UserRole::COORDINATOR->value, 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => UserRole::LEADER->value, 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole(UserRole::SUPER_ADMIN->value);
    $this->actingAs($user);

    $department = Department::factory()->create();
    $this->municipality = Municipality::factory()->create(['department_id' => $department->id]);

    $this->campaign = Campaign::factory()->create(['status' => 'active']);
    Session::put('campaign_context.campaign_id', $this->campaign->id);
    Session::put('campaign_context.mode', 'single');
});

test('follow-up backlog overview pending validation stat links to filtered voter list', function () {
    Voter::factory()->count(2)->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'status' => VoterStatus::PENDING_REVIEW,
    ]);

    $instance = Livewire::test(FollowUpBacklogOverview::class)->instance();
    $stats = (new ReflectionMethod($instance, 'getStats'))->invoke($instance);

    $expectedUrl = VoterResource::getUrl('index', [
        'tableFilters' => [
            'status' => ['values' => [VoterStatus::PENDING_REVIEW->value]],
        ],
    ]);

    expect($stats[0]->getUrl())->toBe($expectedUrl)
        ->and($expectedUrl)->toContain('tableFilters%5Bstatus%5D%5Bvalues%5D%5B0%5D=pending_review');
});

test('top leaders table rows link to the leader filtered voter list', function () {
    $leader = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $leader->campaigns()->attach($this->campaign);

    Voter::factory()->count(5)->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'registered_by' => $leader->id,
    ]);

    $component = Livewire::test(TopLeadersTable::class);

    $recordUrl = $component->instance()->getTable()->getRecordUrl($leader);

    $expectedUrl = VoterResource::getUrl('index', [
        'tableFilters' => [
            'registered_by' => ['values' => [$leader->id]],
        ],
    ]);

    expect($recordUrl)->toBe($expectedUrl)
        ->and($recordUrl)->toContain((string) $leader->id);
});

test('territorial ownership table leader rows link to the leader filtered voter list', function () {
    $leader = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $leader->assignRole(UserRole::LEADER->value);
    $leader->campaigns()->attach($this->campaign);

    $component = Livewire::test(TerritorialOwnershipTable::class);
    $recordUrl = $component->instance()->getTable()->getRecordUrl($leader);

    $expectedUrl = VoterResource::getUrl('index', [
        'tableFilters' => [
            'registered_by' => ['values' => [$leader->id]],
        ],
    ]);

    expect($recordUrl)->toBe($expectedUrl);
});

test('territorial ownership table coordinator rows link to the coordinator team filtered voter list', function () {
    $coordinator = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $coordinator->assignRole(UserRole::COORDINATOR->value);
    $coordinator->campaigns()->attach($this->campaign);

    $leader1 = User::factory()->create(['coordinator_user_id' => $coordinator->id]);
    $leader1->campaigns()->attach($this->campaign);
    $leader2 = User::factory()->create(['coordinator_user_id' => $coordinator->id]);
    $leader2->campaigns()->attach($this->campaign);

    $component = Livewire::test(TerritorialOwnershipTable::class);
    $recordUrl = $component->instance()->getTable()->getRecordUrl($coordinator);

    $teamIds = $coordinator->leaders()->pluck('id')->push($coordinator->id)->all();

    $expectedUrl = VoterResource::getUrl('index', [
        'tableFilters' => [
            'registered_by' => ['values' => $teamIds],
        ],
    ]);

    expect($recordUrl)->toBe($expectedUrl)
        ->and($teamIds)->toContain($leader1->id, $leader2->id, $coordinator->id);
});

test('top polling places table rows link to the polling place filtered voter list', function () {
    $pollingPlace = PollingPlace::factory()->create(['municipality_id' => $this->municipality->id]);

    Voter::factory()->count(3)->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'polling_place_id' => $pollingPlace->id,
    ]);

    $component = Livewire::test(TopPollingPlacesTable::class);
    $recordUrl = $component->instance()->getTable()->getRecordUrl($pollingPlace);

    $expectedUrl = VoterResource::getUrl('index', [
        'tableFilters' => [
            'polling_place_id' => ['values' => [$pollingPlace->id]],
        ],
    ]);

    expect($recordUrl)->toBe($expectedUrl);
});

test('campaign stats overview total de apoyos and confirmados stats link to filtered voter lists', function () {
    Voter::factory()->count(3)->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'status' => VoterStatus::CONFIRMED,
    ]);

    $instance = Livewire::test(CampaignStatsOverview::class)->instance();
    $stats = (new ReflectionMethod($instance, 'getStats'))->invoke($instance);

    $expectedTotalUrl = VoterResource::getUrl('index');
    $expectedConfirmedUrl = VoterResource::getUrl('index', [
        'tableFilters' => [
            'status' => ['values' => [VoterStatus::CONFIRMED->value]],
        ],
    ]);

    expect($stats[0]->getUrl())->toBe($expectedTotalUrl)
        ->and($stats[1]->getUrl())->toBe($expectedConfirmedUrl)
        ->and($stats[2]->getUrl())->toBeNull()
        ->and($stats[3]->getUrl())->toBeNull();
});

test('top coordinators table rows link to the coordinator team filtered voter list', function () {
    $coordinator = User::factory()->create(['municipality_id' => $this->municipality->id]);
    $coordinator->assignRole(UserRole::COORDINATOR->value);
    $coordinator->campaigns()->attach($this->campaign);

    $leader1 = User::factory()->create(['coordinator_user_id' => $coordinator->id]);
    $leader1->campaigns()->attach($this->campaign);
    $leader2 = User::factory()->create(['coordinator_user_id' => $coordinator->id]);
    $leader2->campaigns()->attach($this->campaign);

    $component = Livewire::test(TopCoordinatorsTable::class);
    $recordUrl = $component->instance()->getTable()->getRecordUrl($coordinator);

    $teamIds = $coordinator->leaders()->pluck('id')->push($coordinator->id)->all();

    $expectedUrl = VoterResource::getUrl('index', [
        'tableFilters' => [
            'registered_by' => ['values' => $teamIds],
        ],
    ]);

    expect($recordUrl)->toBe($expectedUrl)
        ->and($teamIds)->toContain($leader1->id, $leader2->id, $coordinator->id);
});

test('jurisdiction report table rows link to the voter view page', function () {
    $voter = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
    ]);

    $component = Livewire::test(JurisdictionReportTable::class);
    $recordUrl = $component->instance()->getTable()->getRecordUrl($voter);

    $expectedUrl = VoterResource::getUrl('view', ['record' => $voter->id]);

    expect($recordUrl)->toBe($expectedUrl);
});

test('rejections report table rows link to the voter view page', function () {
    $voter = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'status' => VoterStatus::REJECTED_CENSUS,
    ]);

    $component = Livewire::test(RejectionsReportTable::class);
    $recordUrl = $component->instance()->getTable()->getRecordUrl($voter);

    $expectedUrl = VoterResource::getUrl('view', ['record' => $voter->id]);

    expect($recordUrl)->toBe($expectedUrl);
});

test('apoyos lideres coordinadores table rows link to the voter view page', function () {
    $voter = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
    ]);

    $component = Livewire::test(ApoyosLideresCoordinadoresTable::class);
    $recordUrl = $component->instance()->getTable()->getRecordUrl($voter);

    $expectedUrl = VoterResource::getUrl('view', ['record' => $voter->id]);

    expect($recordUrl)->toBe($expectedUrl);
});

test('duplicates report table rows link to the voter view page when the record belongs to the active campaign', function () {
    $voterX = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'document_number' => '5559999999',
        'duplicate_sequence' => 0,
    ]);

    $campaignB = Campaign::factory()->create(['status' => 'active']);
    Session::put('campaign_context.campaign_id', $campaignB->id);
    Session::put('campaign_context.mode', 'single');

    Voter::factory()->create([
        'campaign_id' => $campaignB->id,
        'municipality_id' => $this->municipality->id,
        'document_number' => '5559999999',
        'duplicate_sequence' => 1,
        'status' => VoterStatus::DUPLICATE,
    ]);

    Session::put('campaign_context.campaign_id', $this->campaign->id);
    Session::put('campaign_context.mode', 'single');

    $component = Livewire::test(DuplicatesReportTable::class);
    $recordUrl = $component->instance()->getTable()->getRecordUrl($voterX);

    $expectedUrl = VoterResource::getUrl('view', ['record' => $voterX->id]);

    expect($recordUrl)->toBe($expectedUrl);
});

test('duplicates report table rows have no drill-through when the record belongs to a different campaign (D-06 exception)', function () {
    $voterX = Voter::factory()->create([
        'campaign_id' => $this->campaign->id,
        'municipality_id' => $this->municipality->id,
        'document_number' => '5559999999',
        'duplicate_sequence' => 0,
    ]);

    $campaignB = Campaign::factory()->create(['status' => 'active']);
    Session::put('campaign_context.campaign_id', $campaignB->id);
    Session::put('campaign_context.mode', 'single');

    $voterY = Voter::factory()->create([
        'campaign_id' => $campaignB->id,
        'municipality_id' => $this->municipality->id,
        'document_number' => '5559999999',
        'duplicate_sequence' => 1,
        'status' => VoterStatus::DUPLICATE,
    ]);

    Session::put('campaign_context.campaign_id', $this->campaign->id);
    Session::put('campaign_context.mode', 'single');

    $component = Livewire::test(DuplicatesReportTable::class);
    $recordUrl = $component->instance()->getTable()->getRecordUrl($voterY);

    expect($recordUrl)->toBeNull();
});

<?php

use App\Enums\UserRole;
use App\Enums\VoterStatus;
use App\Filament\Resources\Voters\VoterResource;
use App\Filament\Widgets\FollowUpBacklogOverview;
use App\Filament\Widgets\TopLeadersTable;
use App\Models\Campaign;
use App\Models\Department;
use App\Models\Municipality;
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

<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Resources\Voters\Pages\ListVoters;
use App\Filament\Resources\Voters\VoterResource;
use App\Models\Campaign;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    collect(UserRole::values())->each(
        fn ($role) => Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])
    );

    $this->campaign = Campaign::factory()->create(['status' => 'active']);

    $this->reportsViewer = User::factory()->create();
    $this->reportsViewer->assignRole(UserRole::REPORTS_VIEWER->value);
    $this->reportsViewer->campaigns()->attach($this->campaign->id);
});

it('lets a reports_viewer user reach the reports panel dashboard', function () {
    actingAs($this->reportsViewer)
        ->get('/reports')
        ->assertOk();
});

it('denies a user without the reports_viewer role from the reports panel', function () {
    $leader = User::factory()->create();
    $leader->assignRole(UserRole::LEADER->value);

    actingAs($leader)
        ->get('/reports')
        ->assertForbidden();
});

it('lets a reports_viewer user view the voters index and a single voter record in the reports panel', function () {
    $voter = Voter::factory()->create(['campaign_id' => $this->campaign->id]);

    actingAs($this->reportsViewer);
    Session::put('campaign_context.campaign_id', $this->campaign->id);
    Session::put('campaign_context.mode', 'single');

    $this->get('/reports/voters')->assertOk();

    $this->get(VoterResource::getUrl('view', ['record' => $voter], panel: 'reports'))
        ->assertOk();
});

it('403s a reports_viewer user hitting the create/edit voter routes directly in the reports panel', function () {
    $voter = Voter::factory()->create(['campaign_id' => $this->campaign->id]);

    actingAs($this->reportsViewer);
    Session::put('campaign_context.campaign_id', $this->campaign->id);
    Session::put('campaign_context.mode', 'single');

    $this->get(VoterResource::getUrl('create', panel: 'reports'))
        ->assertForbidden();

    $this->get(VoterResource::getUrl('edit', ['record' => $voter], panel: 'reports'))
        ->assertForbidden();
});

it('hides mutating table actions/bulk actions from a reports_viewer user while keeping view visible', function () {
    $voter = Voter::factory()->create(['campaign_id' => $this->campaign->id]);

    actingAs($this->reportsViewer);
    Session::put('campaign_context.campaign_id', $this->campaign->id);
    Session::put('campaign_context.mode', 'single');

    Livewire::test(ListVoters::class)
        ->assertTableActionVisible('view', record: $voter)
        ->assertTableActionHidden('edit', record: $voter)
        ->assertTableBulkActionHidden('delete');
});

it('keeps mutating table actions/bulk actions visible for a super_admin (regression check)', function () {
    $voter = Voter::factory()->create(['campaign_id' => $this->campaign->id]);

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(UserRole::SUPER_ADMIN->value);

    actingAs($superAdmin);
    Session::put('campaign_context.mode', 'all');

    Livewire::test(ListVoters::class)
        ->assertTableActionVisible('view', record: $voter)
        ->assertTableActionVisible('edit', record: $voter)
        ->assertTableBulkActionVisible('delete');
});

it('hides validateCensus/exportCurrent/export/duplicatesReport from a reports_viewer user', function () {
    $voter = Voter::factory()->create(['campaign_id' => $this->campaign->id]);

    actingAs($this->reportsViewer);
    Session::put('campaign_context.campaign_id', $this->campaign->id);
    Session::put('campaign_context.mode', 'single');

    Livewire::test(ListVoters::class)
        ->assertTableActionHidden('validateCensus', record: $voter)
        ->assertActionHidden('exportCurrent')
        ->assertActionHidden('export')
        ->assertActionHidden('duplicatesReport');
});

it('keeps validateCensus/exportCurrent/export/duplicatesReport visible for a super_admin (regression check)', function () {
    $voter = Voter::factory()->create(['campaign_id' => $this->campaign->id]);

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(UserRole::SUPER_ADMIN->value);

    actingAs($superAdmin);
    Session::put('campaign_context.mode', 'all');

    Livewire::test(ListVoters::class)
        ->assertTableActionVisible('validateCensus', record: $voter)
        ->assertActionVisible('exportCurrent')
        ->assertActionVisible('export')
        ->assertActionVisible('duplicatesReport');
});

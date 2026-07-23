<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Resources\Voters\Pages\ListVoters;
use App\Models\CallAssignment;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    collect(UserRole::values())->each(function ($role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    });

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::SUPER_ADMIN->value);

    actingAs($this->admin);
    Session::put('campaign_context.mode', 'all');
    Session::forget('campaign_context.campaign_id');
});

test('filtering by has_open_call shows only voters with a pending or in-progress call assignment', function () {
    $voterWithOpenCall = Voter::factory()->create();
    CallAssignment::factory()->create([
        'voter_id' => $voterWithOpenCall->id,
        'campaign_id' => $voterWithOpenCall->campaign_id,
        'status' => 'pending',
    ]);

    $voterWithCompletedCall = Voter::factory()->create();
    CallAssignment::factory()->create([
        'voter_id' => $voterWithCompletedCall->id,
        'campaign_id' => $voterWithCompletedCall->campaign_id,
        'status' => 'completed',
    ]);

    $voterWithNoContact = Voter::factory()->create();

    Livewire::test(ListVoters::class)
        ->filterTable('contact_state', ['value' => 'has_open_call'])
        ->assertCanSeeTableRecords([$voterWithOpenCall])
        ->assertCanNotSeeTableRecords([$voterWithCompletedCall, $voterWithNoContact]);
});

test('filtering by survey_completed shows only voters with a survey response', function () {
    $voterWithSurvey = Voter::factory()->create();
    SurveyResponse::factory()->create([
        'voter_id' => $voterWithSurvey->id,
    ]);

    $voterWithoutSurvey = Voter::factory()->create();

    Livewire::test(ListVoters::class)
        ->filterTable('contact_state', ['value' => 'survey_completed'])
        ->assertCanSeeTableRecords([$voterWithSurvey])
        ->assertCanNotSeeTableRecords([$voterWithoutSurvey]);
});

test('filtering by no_contact shows only voters with neither a call assignment nor a survey response', function () {
    $voterWithNoContact = Voter::factory()->create();

    $voterWithOpenCall = Voter::factory()->create();
    CallAssignment::factory()->create([
        'voter_id' => $voterWithOpenCall->id,
        'campaign_id' => $voterWithOpenCall->campaign_id,
        'status' => 'pending',
    ]);

    $voterWithSurvey = Voter::factory()->create();
    SurveyResponse::factory()->create([
        'voter_id' => $voterWithSurvey->id,
    ]);

    Livewire::test(ListVoters::class)
        ->filterTable('contact_state', ['value' => 'no_contact'])
        ->assertCanSeeTableRecords([$voterWithNoContact])
        ->assertCanNotSeeTableRecords([$voterWithOpenCall, $voterWithSurvey]);
});

test('with no contact_state filter value all voters remain visible', function () {
    $voterWithOpenCall = Voter::factory()->create();
    CallAssignment::factory()->create([
        'voter_id' => $voterWithOpenCall->id,
        'campaign_id' => $voterWithOpenCall->campaign_id,
        'status' => 'pending',
    ]);

    $voterWithNoContact = Voter::factory()->create();

    Livewire::test(ListVoters::class)
        ->filterTable('contact_state', ['value' => null])
        ->assertCanSeeTableRecords([$voterWithOpenCall, $voterWithNoContact]);
});

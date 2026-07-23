<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Resources\Campaigns\CampaignResource;
use App\Filament\Resources\Surveys\SurveyResource;
use App\Filament\Resources\Voters\VoterResource;
use App\Models\Campaign;
use App\Models\Survey;
use App\Models\User;
use App\Models\Voter;
use App\Services\CampaignContext;
use Spatie\Permission\Models\Role;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => UserRole::SUPER_ADMIN->value, 'guard_name' => 'web']);

    $admin = User::factory()->create();
    $admin->assignRole(UserRole::SUPER_ADMIN->value);
    $this->actingAs($admin);

    $this->campaignA = Campaign::factory()->create();
    $this->campaignB = Campaign::factory()->create();
});

// CampaignContext::setCampaignId() mutates static properties that live for the whole
// test process (not per-test). Reset them after each test so this file never leaks a
// campaign override into unrelated test files that run afterward.
afterEach(function () {
    $reflection = new ReflectionClass(CampaignContext::class);

    foreach (['overrideCampaignId', 'overrideMode'] as $property) {
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }
});

test('VoterResource route binding does not leak a soft-deleted Voter from another campaign', function () {
    CampaignContext::setCampaignId($this->campaignB->id);
    $voterB = Voter::factory()->create(['campaign_id' => $this->campaignB->id]);
    $voterB->delete();

    CampaignContext::setCampaignId($this->campaignA->id);

    $found = VoterResource::getRecordRouteBindingEloquentQuery()->find($voterB->id);

    expect($found)->toBeNull();
});

test('VoterResource route binding resolves a soft-deleted Voter belonging to the active campaign', function () {
    CampaignContext::setCampaignId($this->campaignA->id);
    $voterA = Voter::factory()->create(['campaign_id' => $this->campaignA->id]);
    $voterA->delete();

    $found = VoterResource::getRecordRouteBindingEloquentQuery()->find($voterA->id);

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($voterA->id);
});

test('SurveyResource route binding does not leak a soft-deleted Survey from another campaign', function () {
    CampaignContext::setCampaignId($this->campaignB->id);
    $surveyB = Survey::factory()->create(['campaign_id' => $this->campaignB->id]);
    $surveyB->delete();

    CampaignContext::setCampaignId($this->campaignA->id);

    $found = SurveyResource::getRecordRouteBindingEloquentQuery()->find($surveyB->id);

    expect($found)->toBeNull();
});

test('SurveyResource route binding resolves a soft-deleted Survey belonging to the active campaign', function () {
    CampaignContext::setCampaignId($this->campaignA->id);
    $surveyA = Survey::factory()->create(['campaign_id' => $this->campaignA->id]);
    $surveyA->delete();

    $found = SurveyResource::getRecordRouteBindingEloquentQuery()->find($surveyA->id);

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($surveyA->id);
});

test('CampaignResource route binding still resolves a soft-deleted Campaign with no cross-campaign leak surface', function () {
    // Campaign is the campaign entity itself — it has no `campaign_id` column and is
    // therefore never wrapped by CampaignContextScope (that scope only applies to models
    // using the HasCampaignContext trait, which filter by their own campaign_id column).
    // Removing SoftDeletingScope here only exposes soft-deleted campaigns to this
    // resource's own route binding (the intended trashed-record editing behavior); there
    // is no cross-campaign data to leak from this call site.
    $this->campaignA->delete();

    $found = CampaignResource::getRecordRouteBindingEloquentQuery()->find($this->campaignA->id);

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($this->campaignA->id);
});

<?php

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Neighborhood;
use App\Models\User;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertSoftDeleted;

it('can create a campaign', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->create([
        'name' => 'Campaña 2024',
        'candidate_name' => 'Juan Pérez',
        'created_by' => $user->id,
    ]);

    expect($campaign)->toBeInstanceOf(Campaign::class);
    expect($campaign->name)->toBe('Campaña 2024');
    expect($campaign->candidate_name)->toBe('Juan Pérez');
    expect($campaign->status)->toBe(CampaignStatus::DRAFT);

    assertDatabaseHas('campaigns', [
        'name' => 'Campaña 2024',
        'candidate_name' => 'Juan Pérez',
    ]);
});

it('requires name, candidate_name, start_date, election_date and created_by', function () {
    expect(fn () => Campaign::create([]))->toThrow(Exception::class);
});

it('has default status draft', function () {
    $campaign = Campaign::factory()->create();

    expect($campaign->status)->toBe(CampaignStatus::DRAFT);
});

it('can have different statuses', function () {
    $draft = Campaign::factory()->create(['status' => CampaignStatus::DRAFT]);
    $active = Campaign::factory()->active()->create();
    $paused = Campaign::factory()->paused()->create();
    $completed = Campaign::factory()->completed()->create();

    expect($draft->status)->toBe(CampaignStatus::DRAFT);
    expect($active->status)->toBe(CampaignStatus::ACTIVE);
    expect($paused->status)->toBe(CampaignStatus::PAUSED);
    expect($completed->status)->toBe(CampaignStatus::COMPLETED);
});

it('casts status to CampaignStatus enum', function () {
    $campaign = Campaign::factory()->create();

    expect($campaign->status)->toBeInstanceOf(CampaignStatus::class);
});

it('casts dates correctly', function () {
    $campaign = Campaign::factory()->create();

    expect($campaign->start_date)->toBeInstanceOf(Carbon\Carbon::class);
    expect($campaign->election_date)->toBeInstanceOf(Carbon\Carbon::class);
});

it('casts settings to array', function () {
    $campaign = Campaign::factory()->create([
        'settings' => ['key' => 'value', 'primary_color' => '#FF0000'],
    ]);

    expect($campaign->settings)->toBeArray();
    expect($campaign->settings)->toHaveKey('key');
    expect($campaign->settings['key'])->toBe('value');
});

it('has creator relationship', function () {
    $campaign = Campaign::factory()->create();

    expect($campaign->creator())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

it('can retrieve creator', function () {
    $user = User::factory()->create(['name' => 'Admin User']);
    $campaign = Campaign::factory()->create(['created_by' => $user->id]);

    $campaign->load('creator');

    expect($campaign->creator->id)->toBe($user->id);
    expect($campaign->creator->name)->toBe('Admin User');
});

it('has neighborhoods relationship', function () {
    $campaign = Campaign::factory()->create();

    expect($campaign->neighborhoods())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\HasMany::class);
});

it('can retrieve neighborhoods', function () {
    $campaign = Campaign::factory()->create();
    $neighborhood = Neighborhood::factory()->create(['campaign_id' => $campaign->id]);

    $campaign->load('neighborhoods');

    expect($campaign->neighborhoods)->toHaveCount(1);
    expect($campaign->neighborhoods->first()->id)->toBe($neighborhood->id);
});

it('has users relationship for team members', function () {
    $campaign = Campaign::factory()->create();

    expect($campaign->users())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsToMany::class);
});

it('can attach users to campaign', function () {
    $campaign = Campaign::factory()->create();
    $user = User::factory()->create();

    $campaign->users()->attach($user->id, [
        'assigned_at' => now(),
    ]);

    $campaign->load('users');

    expect($campaign->users)->toHaveCount(1);
    expect($campaign->users->first()->id)->toBe($user->id);
});

it('scope active returns only active campaigns', function () {
    Campaign::factory()->create(['status' => CampaignStatus::DRAFT]);
    Campaign::factory()->active()->create();
    Campaign::factory()->active()->create();
    Campaign::factory()->paused()->create();

    $activeCampaigns = Campaign::active()->get();

    expect($activeCampaigns)->toHaveCount(1);
    expect($activeCampaigns->every(fn ($c) => $c->status === CampaignStatus::ACTIVE))->toBeTrue();
});

it('scope draft returns only draft campaigns', function () {
    Campaign::factory()->create(['status' => CampaignStatus::DRAFT]);
    Campaign::factory()->create(['status' => CampaignStatus::DRAFT]);
    Campaign::factory()->active()->create();

    $draftCampaigns = Campaign::draft()->get();

    expect($draftCampaigns)->toHaveCount(2);
    expect($draftCampaigns->every(fn ($c) => $c->status === CampaignStatus::DRAFT))->toBeTrue();
});

it('scope completed returns only completed campaigns', function () {
    Campaign::factory()->create(['status' => CampaignStatus::DRAFT]);
    Campaign::factory()->completed()->create();
    Campaign::factory()->completed()->create();

    $completedCampaigns = Campaign::completed()->get();

    expect($completedCampaigns)->toHaveCount(2);
    expect($completedCampaigns->every(fn ($c) => $c->status === CampaignStatus::COMPLETED))->toBeTrue();
});

it('can update a campaign', function () {
    $campaign = Campaign::factory()->create([
        'name' => 'Original Name',
    ]);

    $campaign->update(['name' => 'Updated Name']);

    expect($campaign->fresh()->name)->toBe('Updated Name');
    assertDatabaseHas('campaigns', ['name' => 'Updated Name']);
});

it('can soft delete a campaign', function () {
    $campaign = Campaign::factory()->create();
    $id = $campaign->id;

    $campaign->delete();

    expect(Campaign::find($id))->toBeNull();
    expect(Campaign::withTrashed()->find($id))->not->toBeNull();
    assertSoftDeleted('campaigns', ['id' => $id]);
});

it('can restore a soft deleted campaign', function () {
    $campaign = Campaign::factory()->create();
    $campaign->delete();

    $campaign->restore();

    expect(Campaign::find($campaign->id))->not->toBeNull();
    expect($campaign->deleted_at)->toBeNull();
});

it('deleting campaign sets neighborhoods campaign_id to null', function () {
    $campaign = Campaign::factory()->create();
    $neighborhood = Neighborhood::factory()->create(['campaign_id' => $campaign->id]);

    $campaign->forceDelete(); // Force delete para probar nullOnDelete

    expect($neighborhood->fresh()->campaign_id)->toBeNull();
});

it('enum has correct labels in Spanish', function () {
    expect(CampaignStatus::DRAFT->getLabel())->toBe('Borrador');
    expect(CampaignStatus::ACTIVE->getLabel())->toBe('Activa');
    expect(CampaignStatus::PAUSED->getLabel())->toBe('Pausada');
    expect(CampaignStatus::COMPLETED->getLabel())->toBe('Completada');
});

it('enum has correct colors', function () {
    expect(CampaignStatus::DRAFT->getColor())->toBe('gray');
    expect(CampaignStatus::ACTIVE->getColor())->toBe('success');
    expect(CampaignStatus::PAUSED->getColor())->toBe('warning');
    expect(CampaignStatus::COMPLETED->getColor())->toBe('info');
});

it('enum has correct icons', function () {
    expect(CampaignStatus::DRAFT->getIcon())->toBe('heroicon-m-document-text');
    expect(CampaignStatus::ACTIVE->getIcon())->toBe('heroicon-m-bolt');
    expect(CampaignStatus::PAUSED->getIcon())->toBe('heroicon-m-pause-circle');
    expect(CampaignStatus::COMPLETED->getIcon())->toBe('heroicon-m-check-circle');
});

it('enum has descriptions for each status', function () {
    expect(CampaignStatus::DRAFT->getDescription())->toContain('preparación');
    expect(CampaignStatus::ACTIVE->getDescription())->toContain('en curso');
    expect(CampaignStatus::PAUSED->getDescription())->toContain('pausada');
    expect(CampaignStatus::COMPLETED->getDescription())->toContain('finalizada');
});

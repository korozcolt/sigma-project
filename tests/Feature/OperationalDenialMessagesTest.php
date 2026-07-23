<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Exceptions\OperationalDenialException;
use App\Http\Middleware\EnsureUserHasRole;
use App\Models\Campaign;
use App\Models\User;
use App\Models\Voter;
use App\Services\CampaignContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    collect(UserRole::values())->each(function (string $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    });
});

test('EnsureUserHasRole denies access with a message naming the missing role', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRole::LEADER->value);

    $request = Request::create('/test', 'GET');
    $request->setUserResolver(fn () => $user);

    $middleware = new EnsureUserHasRole;

    try {
        $middleware->handle($request, fn () => new Response('success'), UserRole::COORDINATOR->value);
        $this->fail('Expected AuthorizationException was not thrown.');
    } catch (AuthorizationException $e) {
        expect($e->getMessage())->toContain('Coordinador');
    }
});

test('CampaignContext::enforceCampaignId throws OperationalDenialException mentioning campaign context', function () {
    $user = User::factory()->create();
    $user->assignRole(UserRole::LEADER->value);
    $this->actingAs($user);

    CampaignContext::setCampaignId(null);

    $voter = Voter::factory()->make();

    try {
        CampaignContext::enforceCampaignId($voter);
        $this->fail('Expected OperationalDenialException was not thrown.');
    } catch (OperationalDenialException $e) {
        expect($e->getMessage())->toContain('contexto de campaña');
    }
});

test('Gate::before denies cross-campaign records with a message naming the other campaign', function () {
    $campaignA = Campaign::factory()->create();
    $campaignB = Campaign::factory()->create();

    $user = User::factory()->create();
    $user->assignRole(UserRole::LEADER->value);
    $user->campaigns()->attach($campaignA->id, ['assigned_at' => now()]);

    CampaignContext::setCampaignId($campaignA->id);

    $voterFromOtherCampaign = Voter::factory()->create(['campaign_id' => $campaignB->id]);

    $response = Gate::forUser($user)->inspect('view', $voterFromOtherCampaign);

    expect($response->allowed())->toBeFalse()
        ->and($response->message())->toContain('otra campaña');
});

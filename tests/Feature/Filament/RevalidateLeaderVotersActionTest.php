<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Resources\Voters\Pages\ListVoters;
use App\Jobs\DispatchCensusRevalidation;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    collect(UserRole::values())->each(function ($role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    });
});

it('lets a super_admin/admin_campaign/reviewer see and call revalidateLeaderVoters, dispatching the job with the chosen leaderId', function (UserRole $role) {
    Bus::fake();

    $user = User::factory()->create();
    $user->assignRole($role->value);

    $leader = User::factory()->create();
    $leader->assignRole(UserRole::LEADER->value);

    actingAs($user);
    Session::put('campaign_context.mode', 'all');

    Livewire::test(ListVoters::class)
        ->assertTableActionVisible('revalidateLeaderVoters')
        ->callTableAction('revalidateLeaderVoters', data: ['leader_id' => $leader->id])
        ->assertNotified();

    Bus::assertDispatched(DispatchCensusRevalidation::class, fn ($job) => $job->leaderId === $leader->id);
})->with([
    'super_admin' => [UserRole::SUPER_ADMIN],
    'admin_campaign' => [UserRole::ADMIN_CAMPAIGN],
    'reviewer' => [UserRole::REVIEWER],
]);

it('hides revalidateLeaderVoters from the leader role', function () {
    $leader = User::factory()->create();
    $leader->assignRole(UserRole::LEADER->value);

    actingAs($leader);
    Session::put('campaign_context.mode', 'all');

    Livewire::test(ListVoters::class)
        ->assertTableActionHidden('revalidateLeaderVoters');
});

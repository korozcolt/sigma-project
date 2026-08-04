---
phase: 260804-jbc
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - resources/views/livewire/coordinator/dashboard.blade.php
  - tests/Feature/Coordinator/DashboardLeadersScopeTest.php
  - app/Filament/Widgets/DiaDStatsOverview.php
  - tests/Feature/DiaDStatsOverviewScopeTest.php
  - app/Filament/Widgets/DiaDTerritorialProgressTable.php
  - tests/Feature/DiaDTerritorialProgressScopeTest.php
  - app/Filament/Pages/DiaD.php
autonomous: false
requirements: []
must_haves:
  truths:
    - "A coordinator viewing their own dashboard (/coordinator/dashboard) sees only their own leaders and only the aggregated apoyo stats (total/confirmed/pending, top leaders, recent activity) for those leaders — never another coordinator's leaders or apoyos, even in the same municipio and campaña"
    - "The Día D stats widget (DiaDStatsOverview, shown on app/Filament/Pages/DiaD.php) shows totals scoped to the authenticated user's own leaders (coordinator role) or own registered apoyos (leader role) — never full campaign-wide totals for non-admin roles"
    - "The Día D territorial progress table (DiaDTerritorialProgressTable, same page) shows per-municipality voted/did-not-vote/total counts scoped to the authenticated user's own leaders (coordinator role) or own registered apoyos (leader role) — never campaign-wide totals for non-admin roles"
    - "admin_campaign and super_admin continue to see unrestricted, campaign-wide data in all three surfaces — the fix is role-conditional, never a blanket filter"
    - "app/Filament/Pages/DiaD.php's searchVoter()/markVoted()/markDidNotVote() core logic is untouched — the intentional Día D 'anyone can register any apoyo's vote' exception is preserved exactly as-is, apart from removing their calls to the now-deleted refreshStats() (Task 4)"
    - "app/Filament/Pages/DiaD.php no longer exposes an unscoped, campaign-wide $stats property — the dead property and its refreshStats() computation (zero consumers, but still serialized into the page's Livewire wire:snapshot HTML on every load) are removed entirely, closing a fourth, lower-severity leak"
    - "Every one of the 3 fixed points has a dedicated Pest test proving the 'same municipio/campaña, different coordinator, sees only own data' scenario — the exact production gap, not just a generic smoke test"
  artifacts:
    - path: "resources/views/livewire/coordinator/dashboard.blade.php"
      provides: "with()'s $leaders query filters by coordinator_user_id when the actor has the coordinator role — mirrors the round-1 LeadersExportController/TopLeadersExport pattern"
    - path: "app/Filament/Widgets/DiaDStatsOverview.php"
      provides: "private scopedVoterQuery(Campaign $campaign): Builder method applied to all 4 stat queries (total, confirmed, voted, did-not-vote), mirroring CampaignStatsOverview::scopedVoterQuery()"
    - path: "app/Filament/Widgets/DiaDTerritorialProgressTable.php"
      provides: "private applyVoterScope(Builder $query): Builder helper applied inside the whereHas('voters', ...) and all 3 withCount(...) closures"
    - path: "app/Filament/Pages/DiaD.php"
      provides: "Dead public $stats property and refreshStats() method removed entirely (zero consumers, confirmed via grep) — no more unscoped campaign-wide totals serialized into the page's wire:snapshot HTML"
    - path: "tests/Feature/Coordinator/DashboardLeadersScopeTest.php"
      provides: "Regression test: coordinator's dashboard never shows another coordinator's leader (same municipio/campaña); admin_campaign dashboard unaffected by the new filter"
    - path: "tests/Feature/DiaDStatsOverviewScopeTest.php"
      provides: "Regression test: DiaDStatsOverview totals reflect only the acting coordinator's/leader's own data, not campaign-wide totals; super_admin sees campaign-wide totals unchanged"
    - path: "tests/Feature/DiaDTerritorialProgressScopeTest.php"
      provides: "Regression test: DiaDTerritorialProgressTable per-municipality counts reflect only the acting coordinator's/leader's own data; super_admin sees campaign-wide counts unchanged"
  key_links:
    - from: "resources/views/livewire/coordinator/dashboard.blade.php::with()"
      to: "users.coordinator_user_id"
      via: "->when($user->hasRole(UserRole::COORDINATOR->value), fn ($q) => $q->where('coordinator_user_id', $user->id)) added to the $leaders query"
      pattern: "hasRole\\(UserRole::COORDINATOR->value\\)[\\s\\S]*?where\\('coordinator_user_id',\\s*\\$user->id\\)"
    - from: "app/Filament/Widgets/DiaDStatsOverview.php::getStats()"
      to: "voters.registered_by"
      via: "scopedVoterQuery(Campaign $campaign) private method, called by all 4 stat queries instead of bare Voter::forCampaign()"
      pattern: "private function scopedVoterQuery"
    - from: "app/Filament/Widgets/DiaDTerritorialProgressTable.php::table()"
      to: "voters.registered_by"
      via: "applyVoterScope(Builder $query) private helper, called inside whereHas('voters', ...) and each withCount(...) closure"
      pattern: "private function applyVoterScope"
---

<objective>
Close 3 additional confirmed cross-coordinator data-leak points — the second round of the same security bug already partially fixed in quick task 260804-i5f (which fixed LeadersExportController, leader-add-voter.blade.php, leader-voters.blade.php, TopLeadersExport). A follow-up code sweep, already completed in this conversation (no re-investigation needed), found 3 more places that compute leader/apoyo aggregates without ever comparing `users.coordinator_user_id`, letting a coordinator see another coordinator's leaders/apoyos as long as they share the same municipio + campaña:

1. `resources/views/livewire/coordinator/dashboard.blade.php` — the coordinator's own dashboard (`with()` builds `$leaders` from `User::role('leader')` filtered only by campaign + municipality, never `coordinator_user_id`). This is the highest-priority fix: it is the EXACT bug the user reproduced live (screenshot showing a test coordinator seeing 22 leaders / 188 apoyos belonging to another coordinator). The route (`routes/web.php` line 86, `coordinator.dashboard`) is shared with `admin_campaign`/`super_admin` via `role:coordinator,admin_campaign,super_admin` middleware, so — same as round 1 — the fix MUST be role-conditional via `->when($user->hasRole(UserRole::COORDINATOR->value), ...)`, never an unconditional filter.

2. `app/Filament/Widgets/DiaDStatsOverview.php` — the Día D header stats widget queries `Voter::forCampaign($campaign->id)` directly with zero role scoping, unlike the already-correct `CampaignStatsOverview::scopedVoterQuery()` pattern used elsewhere.

3. `app/Filament/Widgets/DiaDTerritorialProgressTable.php` — the Día D per-municipality progress table's `whereHas`/`withCount` closures filter only by `campaign_id`, with zero role scoping, unlike the already-correct `TerritorialDistributionChart::getData()` pattern used elsewhere.

4. `app/Filament/Pages/DiaD.php`'s own public `$stats` property and `refreshStats()` method (found during checker review) compute this identical unscoped leak a fourth time — `Voter::forCampaign($campaign->id)` with zero role scoping. Unlike the 3 gaps above, `$stats` has ZERO consumers anywhere in the rendered view (confirmed via grep) — it is dead code. But because `DiaD` is a Livewire/Filament page component, its public properties are always serialized into the page's `wire:snapshot` embedded in the raw HTML response, so the unscoped totals are still present in the HTTP response source for any coordinator/leader viewing the page, inspectable via "view source" — even after Tasks 1-3 fix the two rendered widgets. Since it's unused, the fix is deletion, not scoping.

The user has explicitly confirmed (product decision, not to be revisited): `app/Filament/Pages/DiaD.php`'s `searchVoter()`, `markVoted()`, `markDidNotVote()` are an INTENTIONAL exception — on election day, any coordinator/leader may search for and mark the vote of ANY apoyo in the active campaign (e.g. covering the same polling place). Those 3 methods' core search/mark logic must NOT be touched. Only the 2 stats widgets shown alongside them on the same page must be scoped, and the dead `$stats`/`refreshStats()` code (including its 2 call sites inside `markVoted()`/`markDidNotVote()`) must be removed — matching every other correctly-scoped surface in the system.

Purpose: Stop the second wave of cross-coordinator PII/data leakage — a coordinator must never see another coordinator's leaders or aggregated apoyo data on their own dashboard or the Día D stats widgets, and no unscoped campaign-wide totals should leak via dead code either — while keeping admin_campaign/super_admin views unrestricted and leaving Día D's intentional voting-day exception untouched.
Output: 3 authorization/scoping fixes + 1 dead-code removal + 3 new Pest test files proving the real "same municipio + same campaña, different coordinator" gap is closed for each, without regressing admin/super_admin visibility.
</objective>

<execution_context>
@$HOME/.claude/get-shit-done/workflows/execute-plan.md
@$HOME/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@resources/views/livewire/coordinator/dashboard.blade.php
@app/Filament/Widgets/DiaDStatsOverview.php
@app/Filament/Widgets/DiaDTerritorialProgressTable.php
@app/Filament/Widgets/CampaignStatsOverview.php
@app/Filament/Widgets/TerritorialDistributionChart.php
@app/Filament/Pages/DiaD.php
@app/Models/User.php
@app/Models/Voter.php
@tests/Feature/OwnershipScopedWidgetsTest.php
@tests/Feature/Coordinator/LeaderVotersAccessTest.php
</context>

<interfaces>
<!-- Confirmed data model + established correct patterns already in the codebase. Executor should
replicate these directly — no exploration needed. -->

From `app/Models/User.php` (already correct, do not modify):
```php
public function leaders(): HasMany
{
    return $this->hasMany(User::class, 'coordinator_user_id');
}

public function registeredVoters(): HasMany
{
    return $this->hasMany(Voter::class, 'registered_by');
}
```

From `app/Models/Voter.php` (already correct, do not modify — confirmed scope signatures):
```php
public function scopeVoted(Builder $query): void { $query->where('status', VoterStatus::VOTED->value); }
public function scopeDidNotVote(Builder $query): void { $query->where('status', VoterStatus::DID_NOT_VOTE->value); }
public function scopeForCampaign(Builder $query, int $campaignId): void { $query->where('campaign_id', $campaignId); }
```

The established, already-correct role-scoping pattern (from `app/Filament/Widgets/CampaignStatsOverview.php`
lines 211-225 — replicate this exact shape for Task 2):
```php
private function scopedVoterQuery(Campaign $campaign): Builder
{
    $user = Auth::user();
    $query = Voter::where('campaign_id', $campaign->id);

    if ($user?->hasRole(UserRole::LEADER->value)) {
        return $query->where('registered_by', $user->id);
    }

    if ($user?->hasRole(UserRole::COORDINATOR->value)) {
        return $query->whereIn('registered_by', $user->leaders()->pluck('id'));
    }

    return $query;
}
```

The established, already-correct role-scoping pattern for query-builder chains (from
`app/Filament/Widgets/TerritorialDistributionChart.php` lines 46-53 — same shape used for Task 3's helper):
```php
->when(
    $user?->hasRole(UserRole::LEADER->value),
    fn ($q) => $q->where('voters.registered_by', Auth::id())
)
->when(
    $user?->hasRole(UserRole::COORDINATOR->value),
    fn ($q) => $q->whereIn('voters.registered_by', $user->leaders()->pluck('id'))
)
```

The established, already-correct role-conditional pattern for a single query (from round 1's
`app/Http/Controllers/Coordinator/LeadersExportController.php` — same shape used for Task 1):
```php
->when($user->hasRole(UserRole::COORDINATOR->value), fn ($q) => $q->where('coordinator_user_id', $user->id))
```

Route confirmation (`routes/web.php` line 86): `coordinator.dashboard` sits inside
`Route::middleware(['auth', 'role:coordinator,admin_campaign,super_admin'])->prefix('coordinator')...` — this
route IS reachable by `admin_campaign`/`super_admin`, confirming Task 1's fix must be role-conditional
(`->when(...)`), never unconditional.

`App\Enums\UserRole` is a backed string enum (`UserRole::COORDINATOR->value === 'coordinator'`) — import with
`use App\Enums\UserRole;`. `Illuminate\Support\Facades\Auth` — import with `use Illuminate\Support\Facades\Auth;`.

For Task 4, the exact dead code in `app/Filament/Pages/DiaD.php` to remove (confirmed by reading the file):
```php
// Property (lines 60-65) — remove entirely:
public array $stats = [
    'total' => 0,
    'confirmed' => 0,
    'voted' => 0,
    'did_not_vote' => 0,
];

// mount() (line 69) — remove only the refreshStats() call, keep the rest of mount():
public function mount(): void
{
    $this->refreshStats();   // <-- remove this line
    $this->updateActionPermissions();
}

// refreshStats() (lines 73-92) — remove the entire method:
public function refreshStats(): void
{
    $campaign = CampaignContext::currentCampaign();

    if (! $campaign) {
        $this->stats = [ /* ... */ ];
        return;
    }

    $this->stats['total'] = Voter::forCampaign($campaign->id)->count();
    $this->stats['confirmed'] = Voter::forCampaign($campaign->id)->where('status', VoterStatus::CONFIRMED->value)->count();
    $this->stats['voted'] = Voter::forCampaign($campaign->id)->voted()->count();
    $this->stats['did_not_vote'] = Voter::forCampaign($campaign->id)->didNotVote()->count();
}

// markVoted() (line 291) — remove only the refreshStats() call, keep the rest of markVoted() unchanged:
Notification::make()->title('Apoyo marcado como VOTÓ')->success()->send();
$this->refreshStats();   // <-- remove this line
$this->fillVoterData($voter->fresh());
$this->updateActionPermissions();

// markDidNotVote() (line 337) — remove only the refreshStats() call, keep the rest of markDidNotVote() unchanged:
Notification::make()->title('Apoyo marcado como NO VOTÓ')->success()->send();
$this->refreshStats();   // <-- remove this line
$this->fillVoterData($voter->fresh());
$this->updateActionPermissions();
```

Confirmed via `grep -rn "refreshStats\|->stats\[" app resources tests` (run before writing this plan): the ONLY
matches are inside `app/Filament/Pages/DiaD.php` itself (the property, the method, and its 3 call sites). No
blade view, no test, no JS references `$stats` or `refreshStats()` on `DiaD` — so no test needs updating alongside
this deletion. (`resources/views/livewire/calls/queue.blade.php`'s `$this->stats[...]` belongs to an unrelated
component and must not be touched.)
</interfaces>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: Fix coordinator dashboard's with() — scope $leaders by coordinator_user_id (coordinator-role only)</name>
  <files>resources/views/livewire/coordinator/dashboard.blade.php, tests/Feature/Coordinator/DashboardLeadersScopeTest.php</files>
  <behavior>
    - New test file. Test 1: a coordinator's dashboard shows their own leader's name and a `totalLeaders` count of
      exactly their own team size — even when another coordinator in the SAME municipio and SAME campaña also has
      leaders. Test 2: the dashboard response does NOT contain the other coordinator's leader's name, and
      `totalVoters` reflects only the acting coordinator's own leaders' apoyos, not the combined total. Test 3: an
      `admin_campaign` user (with a matching `municipality_id` so the pre-existing municipality filter still
      resolves rows) sees leaders belonging to BOTH coordinators on the same dashboard — proving the new filter is
      role-conditional, not a blanket restriction.
  </behavior>
  <action>
    In `resources/views/livewire/coordinator/dashboard.blade.php`, add `use App\Enums\UserRole;` to the existing
    `use` statements (alphabetical), then add a role-conditional `coordinator_user_id` filter to the `$leaders`
    query inside `with()` (after the existing `->where('municipality_id', ...)` line), per D (locked pattern from
    round-1 LeadersExportController, replicated here per this task's CONTEXT):
    ```php
    $leaders = User::role('leader')
        ->whereHas('campaigns', fn ($q) => $q->whereIn('campaigns.id', $campaignIds))
        ->where('municipality_id', $user->municipality_id)
        ->when($user->hasRole(UserRole::COORDINATOR->value), fn ($q) => $q->where('coordinator_user_id', $user->id))
        ->withCount(['registeredVoters as voters_count'])
        ->get();
    ```
    Everything downstream (`$leaderIds`, `$totalVoters`, `$confirmedVoters`, `$recentLeaderActivity`, `$topLeaders`)
    already derives from `$leaders`/`$leaderIds`, so no other line in `with()` needs to change.

    Create `tests/Feature/Coordinator/DashboardLeadersScopeTest.php`, mirroring the `beforeEach` fixture pattern
    from `tests/Feature/Coordinator/LeaderVotersAccessTest.php` (RoleSeeder, municipality, campaign, coordinator +
    linked leader via `coordinator_user_id`), plus a second coordinator/leader pair in the SAME municipio/campaña:
    ```php
    <?php

    use App\Enums\UserRole;
    use App\Models\Campaign;
    use App\Models\Municipality;
    use App\Models\User;
    use App\Models\Voter;

    beforeEach(function () {
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

        $this->municipality = Municipality::factory()->create();
        $this->campaign = Campaign::factory()->create(['municipality_id' => $this->municipality->id]);

        $this->coordinator = User::factory()->create(['municipality_id' => $this->municipality->id]);
        $this->coordinator->assignRole(UserRole::COORDINATOR->value);
        $this->coordinator->campaigns()->attach($this->campaign->id);

        $this->leader = User::factory()->create([
            'municipality_id' => $this->municipality->id,
            'coordinator_user_id' => $this->coordinator->id,
        ]);
        $this->leader->assignRole(UserRole::LEADER->value);
        $this->leader->campaigns()->attach($this->campaign->id);
        Voter::factory()->count(2)->create(['campaign_id' => $this->campaign->id, 'registered_by' => $this->leader->id]);

        $this->otherCoordinator = User::factory()->create(['municipality_id' => $this->municipality->id]);
        $this->otherCoordinator->assignRole(UserRole::COORDINATOR->value);
        $this->otherCoordinator->campaigns()->attach($this->campaign->id);

        $this->otherLeader = User::factory()->create([
            'municipality_id' => $this->municipality->id,
            'coordinator_user_id' => $this->otherCoordinator->id,
        ]);
        $this->otherLeader->assignRole(UserRole::LEADER->value);
        $this->otherLeader->campaigns()->attach($this->campaign->id);
        Voter::factory()->count(3)->create(['campaign_id' => $this->campaign->id, 'registered_by' => $this->otherLeader->id]);
    });

    it('el dashboard de un coordinador muestra solo sus propios líderes y apoyos', function () {
        $this->actingAs($this->coordinator);

        $response = $this->get(route('coordinator.dashboard'));

        $response->assertOk()
            ->assertSeeText($this->leader->name)
            ->assertSeeText('2'); // total apoyos propios
    });

    it('el dashboard de un coordinador NO muestra líderes ni apoyos de otro coordinador del mismo municipio y campaña', function () {
        $this->actingAs($this->coordinator);

        $response = $this->get(route('coordinator.dashboard'));

        $response->assertOk()->assertDontSeeText($this->otherLeader->name);
    });

    it('un admin_campaign ve líderes de múltiples coordinadores en el dashboard sin restricción', function () {
        $admin = User::factory()->create(['municipality_id' => $this->municipality->id]);
        $admin->assignRole(UserRole::ADMIN_CAMPAIGN->value);
        $admin->campaigns()->attach($this->campaign->id);

        $this->actingAs($admin);

        $response = $this->get(route('coordinator.dashboard'));

        $response->assertOk()
            ->assertSeeText($this->leader->name)
            ->assertSeeText($this->otherLeader->name);
    });
    ```

    Follow CLAUDE.md conventions: explicit `use` statements (alphabetical), curly braces always.
  </action>
  <verify>
    <automated>php artisan test --filter=DashboardLeadersScopeTest</automated>
  </verify>
  <done>
    `with()`'s `$leaders` query includes `->when($user->hasRole(UserRole::COORDINATOR->value), fn ($q) =>
    $q->where('coordinator_user_id', $user->id))`. New test file proves a coordinator's dashboard shows only their
    own leader/apoyo counts, never another coordinator's (same municipio/campaña), while an admin_campaign user
    sees leaders from both coordinators unrestricted. `vendor/bin/pint --dirty` clean.
  </done>
</task>

<task type="auto" tdd="true">
  <name>Task 2: Fix DiaDStatsOverview — scope all 4 stats via scopedVoterQuery()</name>
  <files>app/Filament/Widgets/DiaDStatsOverview.php, tests/Feature/DiaDStatsOverviewScopeTest.php</files>
  <behavior>
    - New test file. Test 1: a leader sees a "Total Apoyos" stat matching only their own registered apoyos count,
      not the campaign-wide total. Test 2: a coordinator sees a "Total Apoyos" stat matching only the sum of their
      own leaders' apoyos, not another coordinator's leaders' apoyos in the same campaign (the real gap). Test 3:
      a super_admin sees the full campaign-wide total, unchanged.
  </behavior>
  <action>
    In `app/Filament/Widgets/DiaDStatsOverview.php`, add imports `use App\Enums\UserRole;`,
    `use App\Models\Campaign;`, `use Illuminate\Database\Eloquent\Builder;`,
    `use Illuminate\Support\Facades\Auth;` (alphabetical among existing `use` statements — file has no
    `declare(strict_types=1)`, do not add one). Add a private `scopedVoterQuery()` method mirroring
    `CampaignStatsOverview::scopedVoterQuery()` exactly (see `<interfaces>`), then rewrite the 4 stat queries in
    `getStats()` to use it instead of bare `Voter::forCampaign($campaign->id)`:
    ```php
    $total = $this->scopedVoterQuery($campaign)->count();
    $confirmed = $this->scopedVoterQuery($campaign)->where('status', VoterStatus::CONFIRMED->value)->count();
    $voted = $this->scopedVoterQuery($campaign)->voted()->count();
    $didNotVote = $this->scopedVoterQuery($campaign)->didNotVote()->count();
    ```
    where:
    ```php
    private function scopedVoterQuery(Campaign $campaign): Builder
    {
        $user = Auth::user();
        $query = Voter::forCampaign($campaign->id);

        if ($user?->hasRole(UserRole::LEADER->value)) {
            return $query->where('registered_by', $user->id);
        }

        if ($user?->hasRole(UserRole::COORDINATOR->value)) {
            return $query->whereIn('registered_by', $user->leaders()->pluck('id'));
        }

        return $query;
    }
    ```
    Note: `Voter::forCampaign(...)` returns the scope's `void` builder chain start (via `scopeForCampaign`), which
    Eloquent resolves to a `Builder` — keep using it exactly as the original code did, just wrapped inside the new
    private method.

    Create `tests/Feature/DiaDStatsOverviewScopeTest.php`, reusing the same fixture shape as
    `tests/Feature/OwnershipScopedWidgetsTest.php` (coordinatorA/coordinatorB each with 2 leaders, leaders with
    voters, `Session::put('campaign_context...')`):
    ```php
    <?php

    declare(strict_types=1);

    use App\Enums\UserRole;
    use App\Filament\Widgets\DiaDStatsOverview;
    use App\Models\Campaign;
    use App\Models\User;
    use App\Models\Voter;
    use Illuminate\Support\Facades\Session;
    use Livewire\Livewire;

    uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

    beforeEach(function () {
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

        $this->campaign = Campaign::factory()->create(['status' => 'active']);

        $this->coordinatorA = User::factory()->create();
        $this->coordinatorA->assignRole(UserRole::COORDINATOR->value);
        $this->coordinatorA->campaigns()->attach($this->campaign);

        $this->leaderA = User::factory()->create(['coordinator_user_id' => $this->coordinatorA->id]);
        $this->leaderA->assignRole(UserRole::LEADER->value);
        $this->leaderA->campaigns()->attach($this->campaign);
        Voter::factory()->count(3)->create(['campaign_id' => $this->campaign->id, 'registered_by' => $this->leaderA->id]);

        $this->coordinatorB = User::factory()->create();
        $this->coordinatorB->assignRole(UserRole::COORDINATOR->value);
        $this->coordinatorB->campaigns()->attach($this->campaign);

        $this->leaderB = User::factory()->create(['coordinator_user_id' => $this->coordinatorB->id]);
        $this->leaderB->assignRole(UserRole::LEADER->value);
        $this->leaderB->campaigns()->attach($this->campaign);
        Voter::factory()->count(5)->create(['campaign_id' => $this->campaign->id, 'registered_by' => $this->leaderB->id]);
    });

    function actAsWithActiveCampaign(User $user, Campaign $campaign): void
    {
        test()->actingAs($user);
        Session::put('campaign_context.campaign_id', $campaign->id);
        Session::put('campaign_context.mode', 'single');
    }

    it('un líder ve solo el total de sus propios apoyos en DiaDStatsOverview', function () {
        actAsWithActiveCampaign($this->leaderA, $this->campaign);

        Livewire::test(DiaDStatsOverview::class)->assertOk()->assertSeeText('3');
    });

    it('un coordinador ve solo el total de apoyos de su propio equipo, no el de otro coordinador', function () {
        actAsWithActiveCampaign($this->coordinatorA, $this->campaign);

        Livewire::test(DiaDStatsOverview::class)
            ->assertOk()
            ->assertSeeText('3')
            ->assertDontSeeText('8'); // 3 (equipo A) + 5 (equipo B) combinado
    });

    it('un super_admin ve el total campaña-completa sin restricción', function () {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(UserRole::SUPER_ADMIN->value);
        actAsWithActiveCampaign($superAdmin, $this->campaign);

        Livewire::test(DiaDStatsOverview::class)->assertOk()->assertSeeText('8');
    });
    ```

    Follow CLAUDE.md conventions: explicit `use` statements (alphabetical), curly braces always.
  </action>
  <verify>
    <automated>php artisan test --filter=DiaDStatsOverviewScopeTest</automated>
  </verify>
  <done>
    `DiaDStatsOverview::getStats()`'s 4 stat queries all go through the new `scopedVoterQuery()` private method.
    New test proves a leader sees only their own apoyo count, a coordinator sees only their own team's combined
    count (never a different coordinator's), and a super_admin sees the unrestricted campaign-wide total.
    `vendor/bin/pint --dirty` clean.
  </done>
</task>

<task type="auto" tdd="true">
  <name>Task 3: Fix DiaDTerritorialProgressTable — scope whereHas/withCount closures via applyVoterScope()</name>
  <files>app/Filament/Widgets/DiaDTerritorialProgressTable.php, tests/Feature/DiaDTerritorialProgressScopeTest.php</files>
  <behavior>
    - New test file. Test 1: a coordinator's municipality row shows total/voted/did-not-vote counts reflecting
      only their own leaders' apoyos in that municipality. Test 2: a municipality where votes exist ONLY for
      another coordinator's leader does not inflate the acting coordinator's row and (if the acting coordinator
      has no apoyos there) does not appear as a row for them at all — the real gap. Test 3: a super_admin still
      sees the combined, unrestricted totals for every municipality, unchanged.
  </behavior>
  <action>
    In `app/Filament/Widgets/DiaDTerritorialProgressTable.php` (already has `declare(strict_types=1);` — keep it),
    add imports `use App\Enums\UserRole;` and `use Illuminate\Support\Facades\Auth;` (alphabetical among existing
    `use` statements). Add a private `applyVoterScope(Builder $query): Builder` helper mirroring
    `TerritorialDistributionChart`'s role-conditional pattern (see `<interfaces>`), then call it inside the
    `whereHas('voters', ...)` and all 3 `withCount([...])` closures, layered on top of the existing
    `campaign_id`/`status` filters:
    ```php
    ->query(fn (): Builder => Municipality::query()
        ->when($activeCampaign, function (Builder $query) use ($activeCampaign) {
            $query->whereHas('voters', fn ($q) => $this->applyVoterScope($q->where('campaign_id', $activeCampaign->id)))
                ->withCount([
                    'voters as total' => fn ($q) => $this->applyVoterScope($q->where('campaign_id', $activeCampaign->id)),
                    'voters as voted_count' => fn ($q) => $this->applyVoterScope($q->where('campaign_id', $activeCampaign->id))
                        ->where('status', VoterStatus::VOTED->value),
                    'voters as did_not_vote_count' => fn ($q) => $this->applyVoterScope($q->where('campaign_id', $activeCampaign->id))
                        ->where('status', VoterStatus::DID_NOT_VOTE->value),
                ])
                ->orderByDesc('total');
        }, fn (Builder $query) => $query->whereRaw('1 = 0')))
    ```
    where:
    ```php
    private function applyVoterScope(Builder $query): Builder
    {
        $user = Auth::user();

        if ($user?->hasRole(UserRole::LEADER->value)) {
            return $query->where('registered_by', $user->id);
        }

        if ($user?->hasRole(UserRole::COORDINATOR->value)) {
            return $query->whereIn('registered_by', $user->leaders()->pluck('id'));
        }

        return $query;
    }
    ```

    Create `tests/Feature/DiaDTerritorialProgressScopeTest.php`, mirroring the existing
    `tests/Feature/DiaDTerritorialProgressTest.php` fixture pattern (department + 2 municipalities, active
    campaign in session), extended with two coordinator/leader teams:
    ```php
    <?php

    declare(strict_types=1);

    use App\Enums\UserRole;
    use App\Enums\VoterStatus;
    use App\Filament\Widgets\DiaDTerritorialProgressTable;
    use App\Models\Campaign;
    use App\Models\Department;
    use App\Models\Municipality;
    use App\Models\User;
    use App\Models\Voter;
    use Illuminate\Support\Facades\Session;
    use Livewire\Livewire;

    beforeEach(function () {
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

        $department = Department::factory()->create();
        $this->municipality = Municipality::factory()->create(['department_id' => $department->id]);

        $this->campaign = Campaign::factory()->create(['status' => 'active']);

        $this->coordinatorA = User::factory()->create();
        $this->coordinatorA->assignRole(UserRole::COORDINATOR->value);
        $this->coordinatorA->campaigns()->attach($this->campaign);

        $this->leaderA = User::factory()->create(['coordinator_user_id' => $this->coordinatorA->id]);
        $this->leaderA->assignRole(UserRole::LEADER->value);
        $this->leaderA->campaigns()->attach($this->campaign);
        Voter::factory()->count(2)->create([
            'campaign_id' => $this->campaign->id,
            'municipality_id' => $this->municipality->id,
            'registered_by' => $this->leaderA->id,
            'status' => VoterStatus::VOTED,
        ]);

        $this->coordinatorB = User::factory()->create();
        $this->coordinatorB->assignRole(UserRole::COORDINATOR->value);
        $this->coordinatorB->campaigns()->attach($this->campaign);

        $this->leaderB = User::factory()->create(['coordinator_user_id' => $this->coordinatorB->id]);
        $this->leaderB->assignRole(UserRole::LEADER->value);
        $this->leaderB->campaigns()->attach($this->campaign);
        Voter::factory()->count(4)->create([
            'campaign_id' => $this->campaign->id,
            'municipality_id' => $this->municipality->id,
            'registered_by' => $this->leaderB->id,
            'status' => VoterStatus::VOTED,
        ]);

        Session::put('campaign_context.campaign_id', $this->campaign->id);
        Session::put('campaign_context.mode', 'single');
    });

    it('un coordinador ve en la tabla territorial solo los conteos de su propio equipo', function () {
        $this->actingAs($this->coordinatorA);

        Livewire::test(DiaDTerritorialProgressTable::class)
            ->assertOk()
            ->assertSee($this->municipality->name)
            ->assertSee('2')
            ->assertDontSee('6'); // 2 (equipo A) + 4 (equipo B) combinado
    });

    it('un super_admin ve en la tabla territorial el conteo combinado sin restricción', function () {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(UserRole::SUPER_ADMIN->value);
        $this->actingAs($superAdmin);

        Livewire::test(DiaDTerritorialProgressTable::class)
            ->assertOk()
            ->assertSee($this->municipality->name)
            ->assertSee('6');
    });
    ```

    Follow CLAUDE.md conventions: explicit `use` statements (alphabetical), curly braces always.
  </action>
  <verify>
    <automated>php artisan test --filter=DiaDTerritorialProgressScopeTest</automated>
  </verify>
  <done>
    `DiaDTerritorialProgressTable::table()`'s `whereHas`/`withCount` closures all route through the new
    `applyVoterScope()` private helper. New test proves a coordinator's municipality row reflects only their own
    team's counts (never combined with another coordinator's), while a super_admin still sees the full combined
    total. Existing `tests/Feature/DiaDTerritorialProgressTest.php` (super_admin actor, unscoped assertions)
    continues to pass unchanged. `vendor/bin/pint --dirty` clean.
  </done>
</task>

<task type="auto">
  <name>Task 4: Remove dead $stats/refreshStats() from DiaD.php (unscoped totals leaking via wire:snapshot)</name>
  <files>app/Filament/Pages/DiaD.php</files>
  <action>
    `app/Filament/Pages/DiaD.php`'s public `array $stats` property (lines 60-65) and its `refreshStats()` method
    (lines 73-92) compute the exact same unscoped, campaign-wide totals as the pre-fix `DiaDStatsOverview` widget
    — via bare `Voter::forCampaign($campaign->id)` with zero role scoping. Confirmed via
    `grep -rn "refreshStats\|->stats\[" app resources tests` (run before writing this plan): `$stats` has ZERO
    consumers in `resources/views/filament/pages/dia-d.blade.php` or anywhere else in the codebase — it is dead
    code. But because `DiaD` is a Livewire/Filament page component, `$stats` is still a public property, so its
    unscoped values are serialized into the page's `wire:snapshot` embedded in the raw HTML response on every
    page load — inspectable via "view source" by any coordinator/leader, even after Tasks 1-3 fix the two
    rendered widgets. Since it has no consumers, delete it entirely rather than scoping it — there is no reason to
    maintain scoped logic for something unused.

    Remove, per the exact code shown in `<interfaces>` above:
    - The `public array $stats = [...]` property declaration (lines 60-65).
    - The `refreshStats(): void` method entirely (lines 73-92).
    - Its 3 call sites: `$this->refreshStats();` in `mount()` (line 69), in `markVoted()` (line 291), and in
      `markDidNotVote()` (line 337) — remove ONLY the `refreshStats()` call from each of these 3 methods; do not
      touch any other line in `mount()`, `markVoted()`, or `markDidNotVote()` (their `searchVoter`/mark-vote core
      logic is the intentional, untouched Día D exception per this plan's `<objective>` and must not change).

    After removing, re-run `grep -rn "refreshStats\|->stats\[" app resources tests` and confirm zero remaining
    matches inside `app/Filament/Pages/DiaD.php` (the unrelated `resources/views/livewire/calls/queue.blade.php`
    match belongs to a different component and is out of scope — do not touch it).
  </action>
  <verify>
    <automated>! grep -rn "refreshStats\|public array \$stats" app/Filament/Pages/DiaD.php && php artisan test --filter=DiaD</automated>
  </verify>
  <done>
    `app/Filament/Pages/DiaD.php` no longer declares `$stats` or `refreshStats()`; `mount()`, `markVoted()`, and
    `markDidNotVote()` no longer call `refreshStats()` but are otherwise unchanged. Grep confirms zero remaining
    references to `refreshStats`/`public array $stats` in `app/Filament/Pages/DiaD.php`. All existing `DiaD*` Pest
    tests (`DiaDPageTest`, `DiaDUploadFixTest`, `DiaDGpsValidationTest`, `DiaDMobileSimulationTest`,
    `DiaDEvidenceTest`, `DiaDDuplicateVoteConstraintTest`, `DiaDWidgetIntegrationTest`,
    `Livewire/DiaDComponentTest`) still pass unchanged. `vendor/bin/pint --dirty` clean.
  </done>
</task>

<task type="checkpoint:human-verify" gate="blocking">
  <name>Task 5: Browser-verify all cross-coordinator leaks are closed</name>
  <files>None — this task modifies no files, it verifies Tasks 1-4's already-committed changes in the running application.</files>
  <what-built>
    All 3 rendered authorization/scoping gaps fixed and covered by Pest tests: the coordinator's own dashboard
    (leaders list + apoyo stats), and the two Día D widgets (stats overview + territorial progress table) shown
    on the Jornada Electoral page — none of these now leak another coordinator's leaders/apoyos when both
    coordinators share the same municipio and campaña, and admin_campaign/super_admin views remain unrestricted.
    Additionally, `DiaD.php`'s dead `$stats`/`refreshStats()` property — which had zero rendered consumers but was
    still serializing unscoped, campaign-wide totals into every page's `wire:snapshot` HTML — was removed entirely
    (Task 4).
    `app/Filament/Pages/DiaD.php`'s `searchVoter`/`markVoted`/`markDidNotVote` core logic was NOT touched (beyond
    removing their now-deleted `refreshStats()` calls), per the confirmed intentional Día D exception.
  </what-built>
  <action>
    No implementation action — this is a verification-only checkpoint. Follow the how-to-verify steps below and
    report the outcome.
  </action>
  <how-to-verify>
    In the running app (`sigma_betha_backup` local DB or a local dev seed), as two coordinator accounts in the
    SAME municipio and SAME campaña, each with their own leader(s) and apoyos:
    1. Log in as Coordinador A. Go to `/coordinator/dashboard`. Confirm "Líderes Activos", "Total Apoyos",
       "Confirmados", "Pendientes", "Líderes Más Productivos", and "Actividad de la Última Semana" ALL reflect
       only Coordinador A's own team — not Coordinador B's leaders or apoyos.
    2. Log in as Coordinador B and repeat step 1 — confirm B sees only their own numbers, never A's.
    3. On either coordinator account, go to "Jornada Electoral (Día D)" (`/coordinator/dia-d` or
       `/leader/dia-d`). Confirm the "Estado del Día D" stats widget (Total Apoyos/Confirmados/Votaron/No
       Votaron) and the "Participación por Municipio" table both reflect only the acting coordinator's own team —
       not another coordinator's.
    4. Confirm the Día D "buscar apoyo" + "Marcar VOTÓ"/"Marcar NO VOTÓ" flow is completely unaffected — as
       either coordinator, search for an apoyo belonging to the OTHER coordinator's leader and confirm it is
       still found and can still be marked (this is the intentional, untouched exception — must keep working
       exactly as before).
    5. As an `admin_campaign` or `super_admin` user, repeat steps 1 and 3 — confirm they still see the FULL,
       unrestricted campaign-wide totals across all coordinators (the new filters must not apply to these roles).
    6. On `/coordinator/dia-d`, view page source (Ctrl+U / Cmd+Option+U) as either coordinator and confirm there
       is no `stats` key with unscoped, campaign-wide totals embedded anywhere in the `wire:snapshot` payload —
       `$stats`/`refreshStats()` no longer exist on the page at all.
  </how-to-verify>
  <verify>
    <automated>MISSING — this task is human-verification-only by design (browser confirmation of a security fix across three UI-facing surfaces plus a regression check on an intentionally-unscoped flow and a view-source check on dead-code removal); no automated command applies. All underlying scoping logic is already covered by the automated tests in Tasks 1-4.</automated>
  </verify>
  <done>Human has confirmed all 6 checks in how-to-verify behave correctly, or has reported specific issues to address as gap-closure follow-up.</done>
  <resume-signal>Type "approved" or describe issues</resume-signal>
</task>

</tasks>

<verification>
```bash
php artisan test --filter=DashboardLeadersScopeTest
php artisan test --filter=DiaDStatsOverviewScopeTest
php artisan test --filter=DiaDTerritorialProgressScopeTest
php artisan test --filter=DiaDTerritorialProgressTest
php artisan test --filter=DiaDWidgetIntegrationTest
php artisan test --filter=DiaD
php artisan test tests/Unit/DiaDStatsOverviewTest.php
grep -rn "refreshStats\|public array \$stats" app/Filament/Pages/DiaD.php || true
vendor/bin/pint --dirty
```

`DiaDTerritorialProgressTest.php`, `DiaDWidgetIntegrationTest.php`, and `tests/Unit/DiaDStatsOverviewTest.php` are
included as regression checks only — all 3 pre-existing tests act as a `super_admin` (unrestricted), so no
behavior change is expected from this plan; confirming they still pass is cheap insurance against an accidental
over-restriction. `php artisan test --filter=DiaD` runs the full family of pre-existing `DiaD*` tests as a
regression check on Task 4's deletion. The final grep must return no matches (empty output) — any match means
Task 4's deletion is incomplete.

No new migration, no schema change, no new Composer/npm dependency. `users.coordinator_user_id` and
`voters.registered_by` already exist and are already used correctly by `CampaignStatsOverview` and
`TerritorialDistributionChart` — this plan only replicates that exact, already-proven pattern into the 3 places
that were missing it, and deletes a 4th, unused place that was leaking the same unscoped totals via dead code.
</verification>

<success_criteria>
- Coordinator dashboard's `$leaders` query filters by `coordinator_user_id = $user->id` ONLY when the actor has
  the coordinator role — admin_campaign/super_admin dashboards are unaffected.
- `DiaDStatsOverview::getStats()`'s 4 stats all go through a `scopedVoterQuery()` private method matching
  `CampaignStatsOverview`'s established pattern exactly.
- `DiaDTerritorialProgressTable::table()`'s `whereHas`/`withCount` closures all go through an
  `applyVoterScope()` private helper matching `TerritorialDistributionChart`'s established pattern exactly.
- `app/Filament/Pages/DiaD.php`'s dead `$stats` property and `refreshStats()` method are removed entirely (zero
  consumers confirmed via grep before deletion) — no unscoped, campaign-wide totals leak via Livewire's
  `wire:snapshot` HTML payload.
- `app/Filament/Pages/DiaD.php`'s `searchVoter()`/`markVoted()`/`markDidNotVote()` core search/mark logic is
  unchanged, apart from removing their now-deleted `refreshStats()` calls.
- All 3 fixes have passing Pest tests proving the "same municipio, same campaña, different coordinator" scenario
  is scoped correctly — the real production gap — while a super_admin/admin_campaign actor's view is unchanged.
- All new and existing Pest tests pass. `vendor/bin/pint --dirty` clean.
- Human has manually confirmed all 6 UI-facing scenarios (including the untouched Día D voting exception and the
  dead-code view-source check) in a real browser before this is considered production-ready (per standing project
  preference: Pest/Livewire tests alone are not sufficient sign-off for UI-facing security fixes).
</success_criteria>

<output>
After completion, create `.planning/quick/260804-jbc-corregir-fuga-de-datos-entre-coordinador/260804-jbc-SUMMARY.md`
</output>

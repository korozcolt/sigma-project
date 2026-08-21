<?php

use App\Enums\UserRole;
use App\Enums\VoterStatus;
use App\Models\Campaign;
use App\Models\User;
use App\Models\Voter;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('renders VoterHappyPathFunnelChart and VoterLifecycleBranchCountersOverview with real VoterStatus data', function () {
    $campaign = Campaign::factory()->create(['status' => 'active']);
    $admin = User::factory()->withoutTwoFactor()->create([
        'email' => 'voter-happy-path-funnel@example.com',
        'password' => 'password',
    ]);
    $admin->assignRole(UserRole::SUPER_ADMIN->value);
    $admin->campaigns()->attach($campaign->id);

    Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'registered_by' => $admin->id,
        'status' => VoterStatus::PENDING_REVIEW,
    ]);
    Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'registered_by' => $admin->id,
        'status' => VoterStatus::VOTED,
    ]);
    Voter::factory()->create([
        'campaign_id' => $campaign->id,
        'registered_by' => $admin->id,
        'status' => VoterStatus::REJECTED_CENSUS,
    ]);

    loginRealBrowserUser($admin);

    $page = visit(route('filament.admin.pages.dashboard'));

    // Both widgets are lazy-loaded (x-intersect), sort-last widgets on a dashboard with ~20+
    // widgets - a single scrollTo+wait doesn't reliably trigger the intersection observer at
    // this depth (established precedent from VoterStatusDonutChartTest/CoordinatorTeamStackedBarChartTest).
    foreach (range(1, 8) as $i) {
        $page->script('window.scrollTo(0, document.body.scrollHeight)');
        $page->wait(1);
    }

    $page->assertVisible('[data-chart-kind="funnel"]');
    // FunnelChart.jsx renders always-visible stage-name labels via LabelList (unlike PieChart's
    // hover-only tooltip), so a direct assertSee proves real stage content, not a hover round trip.
    $page->assertSee('Pendiente de Revisión');
    $page->assertSee('Votó');

    // VoterLifecycleBranchCountersOverview is a plain StatsOverviewWidget - its Stat labels
    // render as always-visible static text, not inside the React chart island at all.
    $page->assertSee('Rechazado en Censo');

    $page->assertNoJavaScriptErrors();
});

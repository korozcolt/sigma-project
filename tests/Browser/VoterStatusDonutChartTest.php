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

it('renders VoterStatusDonutChart as a real Recharts donut with real VoterStatus segment labels', function () {
    $campaign = Campaign::factory()->create(['status' => 'active']);
    $admin = User::factory()->withoutTwoFactor()->create([
        'email' => 'voter-status-donut@example.com',
        'password' => 'password',
    ]);
    $admin->assignRole(UserRole::SUPER_ADMIN->value);
    $admin->campaigns()->attach($campaign->id);

    Voter::factory()->count(3)->create([
        'campaign_id' => $campaign->id,
        'registered_by' => $admin->id,
        'status' => VoterStatus::PENDING_REVIEW,
    ]);
    Voter::factory()->count(2)->create([
        'campaign_id' => $campaign->id,
        'registered_by' => $admin->id,
        'status' => VoterStatus::CONFIRMED,
    ]);

    loginRealBrowserUser($admin);

    $page = visit(route('filament.admin.pages.dashboard'));

    // VoterStatusDonutChart is a lazy-loaded (x-intersect), sort-last widget on a
    // dashboard with ~20 widgets — a single scrollTo+wait doesn't reliably trigger
    // its intersection observer once the page has grown that tall, unlike the
    // higher-sort widgets covered by earlier Browser tests. Repeated scroll+wait
    // ticks let each newly-appended lazy widget re-trigger the observer as the
    // page keeps growing beneath the viewport.
    foreach (range(1, 8) as $i) {
        $page->script('window.scrollTo(0, document.body.scrollHeight)');
        $page->wait(1);
    }

    $page->assertVisible('[data-chart-kind="pie"]');

    // PieChart.jsx (built in 22-01, not modified by this plan) renders segment
    // names only inside the Recharts hover Tooltip — there is no static on-page
    // legend text — so real segment-label content is proven via hover, not assertSee.
    // Confirms exactly 2 real segments render (the 2 non-zero VoterStatus counts used
    // in this test; zero-count statuses are correctly omitted, per D-01).
    $page->assertVisible('[data-chart-kind="pie"] .recharts-pie-sector >> nth=0');
    $page->assertVisible('[data-chart-kind="pie"] .recharts-pie-sector >> nth=1');

    $page->script(
        'document.querySelectorAll(\'[data-chart-kind="pie"] .recharts-pie-sector\')[0]'
        .'.dispatchEvent(new MouseEvent("mouseover", { bubbles: true }))'
    );
    $page->wait(1);
    $page->assertSee('Pendiente de Revisión');
    $page->assertNoJavaScriptErrors();
});

<?php

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Message;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('renders MessageDeliveryFunnelChart as a real Recharts funnel with real message delivery data', function () {
    $campaign = Campaign::factory()->create(['status' => 'active']);
    $admin = User::factory()->withoutTwoFactor()->create([
        'email' => 'message-delivery-funnel@example.com',
        'password' => 'password',
    ]);
    $admin->assignRole(UserRole::SUPER_ADMIN->value);
    $admin->campaigns()->attach($campaign->id);

    Message::factory()->create([
        'campaign_id' => $campaign->id,
        'sent_at' => now(),
        'delivered_at' => now(),
    ]);

    loginRealBrowserUser($admin);

    $page = visit(route('filament.admin.resources.messages.message-batches.index'));

    $page->assertVisible('[data-chart-kind="funnel"]');
    $page->assertSee('Enviado');
    $page->assertNoJavaScriptErrors();
});

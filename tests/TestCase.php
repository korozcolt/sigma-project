<?php

namespace Tests;

use App\Services\CampaignContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use ReflectionClass;

abstract class TestCase extends BaseTestCase
{
    /**
     * App\Services\CampaignContext caches the active-campaign selection in private static
     * properties (set via CampaignContext::setCampaignId()) so the selection survives
     * across requests within the same PHP-FPM worker in production. In a serial
     * `php artisan test` run, every Feature/E2E test shares a single PHP process too, so
     * those statics leak from one test file into the next unrelated one — most visibly
     * when a file's afterEach()/test body calls CampaignContext::setCampaignId(null),
     * which sets the private $overrideMode to 'all' (a real, non-null value, not a true
     * reset). Any later test that instead sets context the "production way" via
     * Session::put('campaign_context.mode'|'campaign_id', ...) then gets silently
     * ignored, because CampaignContext::sessionCampaignId() checks the static override
     * before ever reading the session. Reset both statics after every test so no test
     * file can poison whichever unrelated file happens to run next in the same process.
     */
    protected function tearDown(): void
    {
        $reflection = new ReflectionClass(CampaignContext::class);

        foreach (['overrideCampaignId', 'overrideMode'] as $property) {
            $prop = $reflection->getProperty($property);
            $prop->setAccessible(true);
            $prop->setValue(null, null);
        }

        parent::tearDown();
    }
}

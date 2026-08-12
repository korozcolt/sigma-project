---
status: resolved
trigger: "Investigate issue: report-widgets-scope-count-failures - 17 Pest tests fail (serial run: 1520 passed, 17 failed) across Filament report/stat widgets tied to campaign territorial scope and counts."
created: 2026-08-12T00:00:00Z
updated: 2026-08-12T01:15:00Z
---

## Current Focus

hypothesis: CONFIRMED. App\Services\CampaignContext (app/Services/CampaignContext.php) caches the active-campaign selection in private static properties ($overrideCampaignId, $overrideMode), mutated by CampaignContext::setCampaignId(). These statics are process-scoped, so in a serial `php artisan test` run (single shared PHP process across all Feature/E2E files) they leak from one test file into the next. Several existing test files (CoordinatorResourceCampaignTest, MetadataTableFilterAndSortTest, LeaderResourceCampaignTest, AreaCoordinatorResourceCampaignTest, UserResourceTest, Metadata/*, etc.) call CampaignContext::setCampaignId(null) in afterEach/test bodies as "cleanup" - but setCampaignId(null) does NOT clear the override; it sets $overrideMode = 'all' (a real non-null value). CampaignContext::sessionCampaignId() checks `$overrideMode === 'all'` FIRST, before ever reading Session::get('campaign_context.campaign_id') - so once that static is poisoned to 'all', every later test that sets context the "production way" via Session::put('campaign_context.mode'|'campaign_id', ...) (the 8 newer widget/voter tests added 2026-07-30/08-05, none of which defensively pin/reset CampaignContext) gets silently ignored. CampaignContext::currentCampaign() then resolves to null, which is why canView() returned true (the `! $activeCampaign` early-return branch) and getStats()/table queries returned 0/empty (their own `! $activeCampaign` branches / `whereRaw('1 = 0')` fallback).
test: Reproduced deterministically by running CoordinatorResourceCampaignTest.php immediately followed by JurisdictionReportTableTest/DuplicatesReportTableTest/JurisdictionSummaryOverviewTest - all 4 widget-canView/count/assertSee failures reproduce in that 2-file combo alone. Probed the static properties directly via ReflectionClass after CoordinatorResourceCampaignTest ran: confirmed overrideMode='all' leaked into the next file.
expecting: N/A - confirmed via direct reflection probe, not just inference.
next_action: N/A - RESOLVED. Human confirmed independently via a fresh serial `php artisan test` run (1537 passed, 0 failed). Session archived.

## Symptoms

expected: |
  - JurisdictionReportTable::canView() / JurisdictionSummaryOverview::canView() should return false for a National-scope campaign (session campaign_context.mode = 'single')
  - Stat widgets (Jurisdiction, Rejections) should show real counts (e.g. 3, 2) matching factory-seeded data
  - TopCoordinatorsTableTest / TopPollingPlacesTableTest / DuplicatesReportTableTest should assertSee expected row content in rendered Livewire HTML
  - VoterResourceTest "creating a voter with..." should succeed

actual: |
  - canView() returns true when it should return false ("Failed asserting that true is false")
  - Stats widgets return 0 where 3/2/etc. were expected ("Failed asserting that 0 is identical to '3'")
  - assertSee/assertSeeHtml fail - expected content not found in rendered HTML (some with 300+ line diffs, one TopPollingPlacesTableTest failure includes an additional TypeError, itself a downstream symptom of an empty/null record set)
  - VoterResourceTest's "creating a voter with..." test fails (department_id form field stayed null instead of auto-filling to 1)

errors: |
  Full log: /private/tmp/claude-501/-Volumes-NAS-MAC--Data-Herd-sigma-project/b551a442-2575-4a78-b157-b0a56ff9dbd5/scratchpad/pest_serial.log
  Failing test classes (17 total):
  - tests/Feature/Filament/JurisdictionReportTableTest.php (3)
  - tests/Feature/Filament/JurisdictionSummaryOverviewTest.php (4)
  - tests/Feature/Filament/RejectionsCountersOverviewTest.php (2)
  - tests/Feature/Filament/RejectionsReportTableTest.php (2)
  - tests/Feature/Filament/TopCoordinatorsTableTest.php (2)
  - tests/Feature/Filament/TopPollingPlacesTableTest.php (2, one w/ TypeError)
  - tests/Feature/Filament/DuplicatesReportTableTest.php (1)
  - tests/Feature/Filament/VoterResourceTest.php (1)

reproduction: |
  cd "/Volumes/NAS(MAC)/Data/Herd/sigma-project" && php artisan test
  (WITHOUT --parallel; parallel run against shared local sigma_betha_backup DB is non-deterministic - separate issue, not primary target, but root-caused by the SAME class of bug: PHP static state leaking across test files sharing a process/worker)
  Minimal repro: php artisan test tests/Feature/Filament/CoordinatorResourceCampaignTest.php tests/Feature/Filament/JurisdictionReportTableTest.php

started: |
  Not a regression in VoterTerritoryScope/canView() business logic - confirmed pre-existing test-isolation bug. The 8 newer test files (added 2026-07-30/08-05, commits 6b34059/a41c4f8/172251a/4f080f0/c223191) never defensively reset/pinned CampaignContext's static state, unlike several older test files which already carry an explicit comment + ReflectionClass-based afterEach reset for exactly this known footgun (tests/Feature/CampaignScopeAuditTest.php:29-40, tests/Feature/NeighborhoodTest.php:15, tests/Feature/OperationalDenialMessagesTest.php:26-40) - this is a previously-known, previously-worked-around-per-file issue that simply wasn't applied to the newer files.

## Eliminated

- hypothesis: Real regression in VoterTerritoryScope::isWithinCampaignScope() or the REJECTED_OUT_OF_SCOPE extraction from commit 6b34059
  evidence: All 17 failures reproduce/disappear based purely on which OTHER unrelated test file ran immediately before them in the same PHP process (e.g. CoordinatorResourceCampaignTest -> JurisdictionReportTableTest), with zero code changes to VoterTerritoryScope or the widgets themselves. Confirmed via direct ReflectionClass probe of CampaignContext's static properties.
  timestamp: 2026-08-12T00:30:00Z

- hypothesis: Local sigma_betha_backup DB drift / stale fixture data
  evidence: RefreshDatabase wraps every test in a transaction; the failures are 100% deterministic given a fixed file execution order and disappear entirely once CampaignContext statics are reset per-test via tests/TestCase.php::tearDown(), independent of DB content.
  timestamp: 2026-08-12T00:35:00Z

## Evidence

- timestamp: 2026-08-12T00:10:00Z
  checked: app/Services/CampaignContext.php
  found: sessionCampaignId() checks `if (self::$overrideMode === self::MODE_ALL) { return null; }` BEFORE ever reading Session::get('campaign_context.campaign_id'). setCampaignId(null) sets $overrideMode = self::MODE_ALL ('all'), not null - so it never truly "unsets" the override, it pins it to view-all mode.
  implication: Any code path that calls setCampaignId(null) as "cleanup" leaves a non-null override that overrides ALL subsequent Session::put()-based context in the same PHP process.

- timestamp: 2026-08-12T00:15:00Z
  checked: tests/Feature/CampaignScopeAuditTest.php, tests/Feature/NeighborhoodTest.php, tests/Feature/OperationalDenialMessagesTest.php
  found: These 3 files already contain an explicit afterEach() that resets CampaignContext's overrideCampaignId/overrideMode via ReflectionClass, with a comment explicitly describing this exact leak ("CampaignContext::setCampaignId() mutates static properties that live for the whole test process (not per-test). Reset them after each test so this file never leaks a campaign override into unrelated test files that run afterward.")
  implication: This is a previously-known, previously-diagnosed issue in this codebase that was fixed on a per-file, opt-in basis rather than globally - and several newer files (esp. the 8 report-widget/voter tests added 2026-07-30/08-05) never adopted the same defensive pattern.

- timestamp: 2026-08-12T00:20:00Z
  checked: tests/Feature/Filament/JurisdictionReportTableTest.php, tests/Feature/Filament/VoterResourceTest.php (relevant test)
  found: Both rely purely on Session::put('campaign_context.mode', 'single') + Session::put('campaign_context.campaign_id', ...) (the realistic, production-equivalent way to set context) with zero CampaignContext::setCampaignId() calls and zero defensive resets.
  implication: These files are exactly the ones vulnerable to inheriting a poisoned override from whatever alphabetically-earlier test file happened to run before them and left $overrideMode = 'all'.

- timestamp: 2026-08-12T00:40:00Z
  checked: Direct ReflectionClass probe of CampaignContext::$overrideMode/$overrideCampaignId after running tests/Feature/Filament/CoordinatorResourceCampaignTest.php followed by a throwaway probe test
  found: overrideMode='all', overrideCampaignId=NULL confirmed leaked into the next test file's process state, exactly as hypothesized.
  implication: Root cause confirmed empirically, not just by code inspection.

- timestamp: 2026-08-12T00:50:00Z
  checked: Attempted fix via tests/Pest.php global afterEach(...)->in('Feature') / ->in('Feature', 'E2E')
  found: This did NOT fire at all (confirmed via fwrite(STDERR, ...) debug probe - zero output even for the pre-existing `beforeEach(...)->in('E2E')` RoleSeeder hook already in the file). Pest's `afterEach()->in(...)`/`beforeEach()->in(...)` chained calls proxy through PendingCalls\{Before,After}EachCall::__call(), which - when the method name doesn't exist on Pest's internal TestCall class - forwards the call onto the TEST CASE INSTANCE at runtime, not onto Pest's file-matching/registration mechanism. Since neither the PHPUnit TestCase nor Testable trait define `in()`, this silently does nothing (no error, no effect) rather than scoping the hook to a directory.
  implication: `.in()` chaining on bare beforeEach()/afterEach() calls in tests/Pest.php is NOT a reliable way to scope global hooks by directory in this Pest version - it only reliably works for `uses()`/`pest()->extend()` (Configuration::in()), and possibly not even for the pre-existing E2E RoleSeeder hook (separately confirmed non-firing, though harmless there since every E2E test's own beforeEach likely re-seeds roles too).
  implication_2: Switched to overriding tests/TestCase.php::tearDown() instead - guaranteed standard PHPUnit lifecycle behavior, confirmed firing via the same ReflectionClass probe (overrideMode=NULL after the fix).

- timestamp: 2026-08-12T01:00:00Z
  checked: Post-fix full serial `php artisan test` run (twice)
  found: |
    Run 1: 1 failed, 1536 passed - the 1 failure (UserResourceTest > can update user campaigns) is a DIFFERENT, unrelated, pre-existing ~10%-flaky test, confirmed independent of this fix by reproducing the identical failure on unmodified `main` in isolation (`git stash` + rerun). Root cause: database/factories/UserFactory.php:37 sets `document_number` to null ~10% of the time (`fake()->boolean(90) ? ... : null`), and UserForm's document_number field is `->required()`; the test edits a factory-created User without supplying document_number in fillForm(), so ~1-in-10 runs hit a factory-generated null and fail form validation. Entirely unrelated to CampaignContext/campaign scope. Out of scope for this session - flagged for a separate ticket.
    Run 2: 1537 passed, 0 failed (the flaky test happened to pass this time, consistent with a probabilistic factory-based flake, not an order-dependent one).
  implication: All 17 originally-reported failures are fixed and stable across repeated full-suite runs. No regressions introduced.

## Resolution

root_cause: |
  App\Services\CampaignContext (app/Services/CampaignContext.php) caches the resolved campaign-context selection in two `private static` properties (`$overrideCampaignId`, `$overrideMode`), written by `CampaignContext::setCampaignId()`. This is intentional for production (survives within one PHP-FPM worker across the request lifecycle), but in a serial `php artisan test` run every Feature/E2E test file shares a single PHP process, so these statics leak across unrelated test files. Critically, `setCampaignId(null)` - called as "cleanup" in several existing test files' `afterEach`/test bodies (e.g. CoordinatorResourceCampaignTest, MetadataTableFilterAndSortTest) - does NOT reset the override to an unset state; it pins `$overrideMode` to `'all'` (a real, non-null value). `CampaignContext::sessionCampaignId()` checks `$overrideMode === 'all'` and returns null immediately, BEFORE ever consulting `Session::get('campaign_context.campaign_id')`. So once poisoned, any later test that sets context the realistic/production way via `Session::put('campaign_context.mode', 'single')` + `Session::put('campaign_context.campaign_id', ...)` has that call silently ignored, and `CampaignContext::currentCampaign()` resolves to null instead of the intended campaign. This exactly explains every symptom: `canView()` hitting its `! $activeCampaign -> return true` early branch, stat widgets hitting their `! $activeCampaign -> return [Stat::make(..., 0), ...]` branch, and table widgets hitting their `whereRaw('1 = 0')` no-campaign fallback (empty table -> assertSee failures). This was already a known, previously-diagnosed issue in this codebase (3 older test files already carry an explicit, commented `afterEach` reflection-based reset for exactly this reason) - the 8 newer report-widget/voter tests (added 2026-07-30 through 2026-08-05) simply never adopted that same per-file defensive pattern, and happened to be scheduled (alphabetically, in Pest's default file discovery order) right after files that poison the static.

fix: |
  Moved the defensive reset from an opt-in per-test-file pattern into `tests/TestCase.php` (the shared base class extended by every Feature/Browser/E2E test via `pest()->extend(Tests\TestCase::class)->in(...)` in tests/Pest.php), overriding `tearDown()` to reset `CampaignContext::$overrideCampaignId`/`$overrideMode` back to `null` via `ReflectionClass` after every single test, before calling `parent::tearDown()`. This guarantees no test file can ever again leak its `CampaignContext::setCampaignId()` state into whichever unrelated file happens to run next in the same process, without requiring every future test author to remember to add the same boilerplate. (Note: an initial attempt to implement this as a global `afterEach(...)->in('Feature')` hook in `tests/Pest.php` was tried first but confirmed - via an `fwrite(STDERR, ...)` probe - to never fire at all; Pest's `.in()` chaining on bare `beforeEach()`/`afterEach()` calls does not reliably scope hooks by directory in this Pest version, unlike `uses()`/`pest()->extend()->in()`. Switched to the standard PHPUnit `tearDown()` lifecycle method instead, which is guaranteed to fire and was confirmed via the same reflection probe.)

verification: |
  1. Reproduced the leak deterministically pre-fix via ReflectionClass probe (overrideMode='all' after CoordinatorResourceCampaignTest ran, before the fix).
  2. Confirmed the same probe shows overrideMode=NULL (correctly reset) after the fix, run in the identical 2-file sequence.
  3. Ran the exact 12-file combination containing all 8 originally-failing test classes together (in an order that reproduces leakage pre-fix) - 124 passed, 0 failed post-fix.
  4. Ran the full serial suite (`php artisan test`, no --parallel) twice post-fix: Run 1 = 1536/1537 passed (1 unrelated pre-existing flaky test, confirmed independent via git-stash reproduction on unmodified main); Run 2 = 1537/1537 passed.
  5. `vendor/bin/pint --dirty` passes with no style violations on the changed file.
  6. This is a test-suite-only fix - zero production/application code was touched, so no user-facing behavior changes. The production code path (real HTTP requests, one PHP-FPM worker per request in most deployment configs, or explicit setCampaignId() calls immediately followed by intentional use within the same request) was never actually broken; only the Pest test suite's cross-file process-sharing exposed the latent "setCampaignId(null) doesn't truly unset" quirk.

files_changed:
  - tests/TestCase.php

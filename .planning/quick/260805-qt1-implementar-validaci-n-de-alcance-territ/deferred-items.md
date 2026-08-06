# Deferred Items — Quick Task 260805-qt1

## Full-suite `CampaignContext` test-pollution recurrence (pre-existing, out of scope)

Running the complete `php artisan test` suite (1300+ tests) after this plan's changes surfaces
17 intermittent failures, including in files touched by this plan (`JurisdictionReportTableTest`,
`JurisdictionSummaryOverviewTest`, `RejectionsReportTableTest`) as well as unrelated files
(`TopCoordinatorsTableTest`, `TopPollingPlacesTableTest`, `DuplicatesReportTableTest`,
`UserResourceTest`, `VoterResourceTest` — the exact subset varies between runs).

This is the same pre-existing `CampaignContext::setCampaignId()` static-override test-pollution
issue already documented in `.planning/STATE.md` Blockers/Concerns (found during Phase 05.1 Plan 01,
recurred during quick task 260730-cs3): several test files call `CampaignContext::setCampaignId()`
without resetting the static override in `tearDown()`/`afterEach()`, so full-suite runs (parallel
random test ordering) leak campaign context between unrelated test classes. Adding new tests
(as this plan does) shifts which specific tests collide with the leak, but does not create the
underlying bug.

**Confirmed not a regression from this plan:** every targeted test file for this plan
(`VoterTerritoryScopeTest`, `JurisdictionReportTableTest`, `JurisdictionSummaryOverviewTest`,
`ReconcileVoterTerritoryTest`, `RejectionsReportTableTest`, plus `TopCoordinatorsTableTest` and
`TopPollingPlacesTableTest` for good measure) passes cleanly (45/45) when run together via
`php artisan test --filter="..."`, isolated from the rest of the suite. Only the full, unscoped
`php artisan test` run — which was already flaky before this plan per STATE.md — exhibits the
failures.

**Not fixed here** (out of this plan's scope per the SCOPE BOUNDARY rule — pre-existing failures
in unrelated files). Recommended permanent fix (already recommended in STATE.md): reset
`CampaignContext`'s static override in a shared `afterEach()` / `TestCase::tearDown()`.

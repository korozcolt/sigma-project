# Deferred Items — Quick Task 260730-cs3

## Pre-existing full-suite test-pollution flakiness (out of scope)

When running the FULL `php artisan test` suite (not a targeted subset), the following
files intermittently fail, but pass cleanly both:
- on the pre-Task-1 baseline (confirmed via `git stash` + targeted run), and
- in isolation with all of this quick task's changes applied.

- `tests/Feature/Filament/DuplicatesReportTableTest.php`
- `tests/Feature/Filament/JurisdictionReportTableTest.php`
- `tests/Feature/Filament/RejectionsReportTableTest.php`
- `tests/Feature/Filament/TopCoordinatorsTableTest.php`
- `tests/Feature/Filament/TopPollingPlacesTableTest.php`
- `tests/Feature/Filament/VoterResourceTest.php`
- `tests/Feature/IsElectionDayMiddlewareTest.php`
- `tests/Feature/RevalidationProgressWidgetTest.php` (Task 4's new widget test — also a
  victim, not a cause: passes cleanly alone or paired with any single other file tried;
  only fails when batched alongside the files above, in the same
  `CampaignContext`-session-vs-static-override collision pattern)

This matches a test-pollution class of issue already documented in
`.planning/STATE.md` ("Pre-existing test files ... call `CampaignContext::setCampaignId()`
without resetting the static override — latent test-pollution risk. Found/scoped during
Phase 05.1 Plan 01."). Adding a new test file (`CensusUnifiedResolutionTest.php`) shifts
parallel/alphabetical test execution order, which appears to change which tests collide
with the pre-existing static-state leak — it is not a regression introduced by this
quick task's code changes.

Out of scope for this quick task per the SCOPE BOUNDARY rule (pre-existing issue in
unrelated files). Logged here rather than fixed.

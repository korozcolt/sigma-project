# Deferred Items - Phase 06 Plan 01

## Pre-existing full-suite instability in this worktree (out of scope)

**Found during:** Task 3 verification (full `php artisan test` run).

**Observation:** Running the entire suite in this freshly-synced worktree (`worktree-agent-ae9f012d50fef4e54`, fast-forwarded to `main` and given a fresh `composer install`) surfaces widespread failures unrelated to this plan's changes: `Tests\Feature\Filament\SurveyResourceTest`, `TopCoordinatorsTableTest`, `TopPollingPlacesTableTest`, `UserResourceTest`, `VoterRegistraduriaRefreshTest`, and eventually a PHP fatal `Allowed memory size ... exhausted` partway through the run. None of these touch `national_census_records`, `NationalCensusRecord`, or `census:import-national`.

**Root cause (not investigated in depth):** Most likely environment-setup gaps specific to this worktree checkout (no `.env`, no `storage:link`, base seeders/config caches not primed) rather than a regression — this worktree was reconstructed mid-session (see plan SUMMARY "Deviations" section) and had never run its test suite before this session.

**Scope decision:** Out of scope for 06-01 per the plan's scope boundary. The plan's own test file (`tests/Feature/ImportNationalCensusTest.php`) is fully green in isolation (`php artisan test --filter=ImportNationalCensus` → 7 passed / 20 assertions / 0 failed). Not fixed here.

**Action for a human/future session:** Before relying on this worktree for further phase 06+ work, run the full suite once with `.env` present / storage linked / seeders primed and confirm whether these failures pre-date this session on `main` too.

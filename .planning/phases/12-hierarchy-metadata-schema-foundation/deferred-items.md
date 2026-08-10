# Deferred Items — Phase 12

## Plan 12-02

### Pre-existing full-suite test pollution (out of scope)

During `php artisan test` (full suite) after Task 2, 17-18 tests failed across
`DuplicatesReportTableTest`, `JurisdictionReportTableTest`, `JurisdictionSummaryOverviewTest`,
`RejectionsCountersOverviewTest`, `RejectionsReportTableTest`, `TopCoordinatorsTableTest`,
`TopPollingPlacesTableTest`, `VoterResourceTest`, and `IsElectionDayMiddlewareTest`.

None of these files are touched by this plan (which only adds
`database/migrations/2026_08_10_120100_create_metadata_keys_table.php`,
`database/migrations/2026_08_10_120200_create_user_metadata_values_table.php`,
`app/Models/MetadataKey.php`, `app/Models/UserMetadataValue.php`,
`database/factories/MetadataKeyFactory.php`, `database/factories/UserMetadataValueFactory.php`,
and `tests/Feature/MetadataCatalogSchemaTest.php`).

Confirmed pre-existing and not a regression:
- `MetadataCatalogSchemaTest` passes cleanly (6/6) both standalone and in the full suite.
- `VoterResourceTest` re-run in isolation (`--filter=VoterResourceTest`) passes cleanly (60/60).
- This matches the already-documented `CampaignContext` static-override test-pollution issue in
  `.planning/STATE.md`'s Blockers/Concerns section ("a different subset fails each time, always
  disjoint from files touched by the task, always passing in isolation").

Not fixed — out of scope per this plan's boundary and the standing project-level recommendation
to reset `CampaignContext`'s static override in a shared `afterEach`/`TestCase::tearDown()`.

### Worktree provisioning

This worktree (`agent-a8ab1a1bce4d62ed3`) was stale at session start — behind `main` by 2 commits
(missing this phase's own `12-01-PLAN.md`/`12-02-PLAN.md`), and missing `vendor/`, `.env`,
`node_modules/`, and `public/build/`. Resolved with the established workaround: confirmed
fast-forward ancestry (`git merge-base --is-ancestor HEAD main`), `git merge --ff-only main`,
copied `.env` from the main checkout, ran `composer install`, `npm install`, `npm run build`.
Same recurring class of issue already logged multiple times in `.planning/STATE.md`.

# Deferred Items — Quick Task 260731-n0n

## Pre-existing full-suite flakiness (out of scope, not caused by this task)

Running `php artisan test --testsuite=Feature --filter="User|Campaign|Voter|Login|Logout|Auth"` (a
superset filter that happens to also catch report-table tests) showed 15 failures alongside 571
passes. All 15 were confirmed unrelated to this task's changes:

- `Tests\Feature\Filament\DuplicatesReportTableTest`
- `Tests\Feature\Filament\JurisdictionReportTableTest` (x3)
- `Tests\Feature\Filament\JurisdictionSummaryOverviewTest` (x4)
- `Tests\Feature\Filament\RejectionsReportTableTest`
- `Tests\Feature\Filament\TopCoordinatorsTableTest` (x2)
- `Tests\Feature\Filament\TopPollingPlacesTableTest`
- `Tests\Feature\Filament\UserResourceTest > can update user campaigns`
- `Tests\Feature\Filament\VoterResourceTest > creating a voter with...`
- `Tests\Feature\Livewire\DiaDComponentTest` (x2)

Verification that these are pre-existing, not new regressions:
- Every failing test (except `UserResourceTest > can update user campaigns`) passes cleanly when
  run in isolation or in its own file group — the classic signature of the already-documented
  `CampaignContext` static-override test-pollution issue (see STATE.md Blockers/Concerns:
  "Pre-existing test files ... call `CampaignContext::setCampaignId()` without resetting the
  static override — latent test-pollution risk", confirmed multiple times across prior quick
  tasks, most recently 260730-cs3).
- `UserResourceTest > can update user campaigns` is a separately, explicitly documented
  pre-existing flake (STATE.md: "Intermittent flake in
  `Tests/Feature/Filament/UserResourceTest > can update user campaigns` (~1/3 of full-suite runs);
  pre-existing, logged in 04.1 deferred-items.md") that also failed when run alone in this session.
- None of the 15 failing test files were touched by this task (`AuditLog`, `AuditObserver`,
  `AuditAuthActivitySubscriber`, and their own 3 test files are the only files this task added or
  modified).

This task's own 3 test files (`AuditLogTest`, `AuditObserverTest`,
`AuditAuthActivitySubscriberTest` — 11 tests total) pass reliably together and were not part of the
above failure list. No action taken on the pre-existing flakiness per this task's scope boundary
(out-of-scope, already tracked in STATE.md).

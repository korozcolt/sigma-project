---
status: resolved
trigger: "voter-status-match-missing-cases"
created: 2026-08-06T00:00:00Z
updated: 2026-08-06T00:00:00Z
---

## Symptoms

expected: Viewing or listing any voter/apoyo in the Filament admin, regardless of `status`, should render successfully.
actual: Live production 500 error ("Unhandled match case of type App\Enums\VoterStatus") when opening `/admin/voters/{id}` for any voter marked `REJECTED_OUT_OF_SCOPE` (the status added earlier the same day by the territorial-scope feature, see `.planning/debug/resolved/voter-territory-scope-ignores-resolved-polling-place.md`). Discovered immediately after confirming that fix in production, when the product owner opened one of the newly-flagged voters.
errors: `Illuminate\View\ViewException: Unhandled match case of type App\Enums\VoterStatus at app/Filament/Resources/Voters/Pages/ViewVoter.php:106`.
reproduction: Open the ViewVoter page, or the main Apoyos list, for any voter with `status = REJECTED_OUT_OF_SCOPE`.
started: Introduced the moment the first voters were marked with the new status (same day the status was added), but the underlying pattern (non-exhaustive `match($voter->status)` with no `default` arm) is a pre-existing fragility — `ViewVoter::nextStepGuidance()` was ALSO already missing `CENSUS_NOT_FOUND` and `VERIFIED_REGISTRADURIA` (2 pre-existing statuses), undetected because no test ever rendered that page for a voter with either of those statuses.

## Resolution

root_cause: Two non-exhaustive `match($record->status)` blocks over the `VoterStatus` enum, with no `default` arm, so any status value not explicitly listed throws instead of degrading gracefully: (1) `ViewVoter::nextStepGuidance()` (app/Filament/Resources/Voters/Pages/ViewVoter.php:97-107) was missing `CENSUS_NOT_FOUND`, `VERIFIED_REGISTRADURIA` (pre-existing gap), and `REJECTED_OUT_OF_SCOPE` (new). (2) `VotersTable`'s status badge `->color()` closure (app/Filament/Resources/Voters/Tables/VotersTable.php:89-101) was missing only `REJECTED_OUT_OF_SCOPE` — breaking the main Apoyos listing itself for any row with that status.
fix: Added the missing arms to both matches (`CENSUS_NOT_FOUND`, `VERIFIED_REGISTRADURIA`, `REJECTED_OUT_OF_SCOPE` guidance text in ViewVoter; `REJECTED_OUT_OF_SCOPE => 'danger'` color in VotersTable, matching the enum's own `getColor()`). Added parameterized Pest regression tests (`->with(VoterStatus::cases())`) asserting both `ViewVoter` and `ListVoters` render successfully for every current enum case, so any future `VoterStatus` addition that's missed in one of these matches fails CI instead of production.
verification: 57 tests / 143 assertions in `VoterResourceTest` pass, including the new 12-case-each parameterized coverage. `vendor/bin/pint --dirty` clean. Deployed to both instances (commit 698bc8e). Product owner confirmed fixed after reload.
files_changed:
  - app/Filament/Resources/Voters/Pages/ViewVoter.php
  - app/Filament/Resources/Voters/Tables/VotersTable.php
  - tests/Feature/Filament/VoterResourceTest.php

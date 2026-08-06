---
status: resolved
trigger: "voter-territory-scope-ignores-resolved-polling-place"
created: 2026-08-05T00:00:00Z
updated: 2026-08-05T00:01:00Z
---

## Current Focus

hypothesis: CONFIRMED — VoterTerritoryScope::isWithinCampaignScope() compared campaign scope against voter->municipality_id even when voter->polling_place_id was resolved, ignoring the resolved polling place's real municipality/department.
test: Patched isWithinCampaignScope() to prefer $voter->pollingPlace->municipality_id (municipal case) / $voter->pollingPlace->municipality->department_id (departmental case) when a polling place is resolved, falling back to voter->municipality_id/voter->municipality->department_id otherwise. Added 4 new Pest cases to VoterTerritoryScopeTest.php covering: no-polling-place fallback, polling-place-disagrees (Municipal scope), polling-place-disagrees (Departamental scope), polling-place-agrees sanity check.
expecting: All new + existing tests pass; existing JurisdictionReportTable/JurisdictionSummaryOverview/ReconcileVoterTerritory tests unaffected since none of them set polling_place_id (fallback path preserved).
next_action: Await user confirmation after they deploy and run census:reconcile-territory against real sigma-betha production data (no DB access in this environment to verify directly).

## Symptoms

expected: When a voter has a resolved polling_place_id, the territorial scope check (and the 3 pre-existing jurisdiction display widgets/export it was extracted from) should treat that polling place's municipality/department as ground truth, falling back to voter's own municipality_id only when no polling place is resolved.
actual: The check always uses voter->municipality_id regardless of whether a polling place is resolved. Confirmed with live production data on sigma-betha (campaign 1, "Alcaldía 2027", scope Municipal, municipality_id=999=Sincelejo): 16 voters have a resolved polling_place_id whose PollingPlace->municipality_id is a DIFFERENT municipality (Morroa, Palmito, Sampués) than their stored voter.municipality_id (almost always incorrectly = Sincelejo, matching campaign's own municipality from creation-time default). All 16 are genuinely out of scope by real polling place, but only 1 is currently marked REJECTED_OUT_OF_SCOPE.
errors: None — silent data-accuracy bug.
reproduction: Voter created while campaign has fixed municipality gets municipality_id defaulted to campaign's municipality at creation (register-voter.blade.php / leader-add-voter.blade.php mount()). If polling place later resolves to a DIFFERENT real municipality via PollingPlaceResolver automated cascade, VoterTerritoryScope::isWithinCampaignScope() still reports "within scope" because it never looks at the resolved polling place.
started: Same-day (2026-08-05), quick task 260805-qt1 (commit 8c6f388) extracted VoterTerritoryScope verbatim from 3 pre-existing widgets that had the same bug for months.

## Eliminated

- hypothesis: The "21 apoyos válidos sin puesto de votación asignado" widget count (TopPollingPlacesTable) is part of this bug.
  evidence: Confirmed via live query this count is accurate; unrelated growth from new voter registrations without resolved polling place yet. Out of scope per task instructions.
  timestamp: 2026-08-05T00:00:00Z

## Evidence

- timestamp: 2026-08-05T00:00:00Z
  checked: app/Services/VoterTerritoryScope.php (current code)
  found: isWithinCampaignScope() only ever reads $voter->municipality_id / $voter->municipality?->department_id; never inspects $voter->polling_place_id or $voter->pollingPlace at all.
  implication: Confirms root cause exactly as described — need to add polling-place-first logic.

- timestamp: 2026-08-05T00:00:00Z
  checked: app/Models/Voter.php, app/Models/PollingPlace.php, app/Models/Municipality.php
  found: Voter::pollingPlace() is a BelongsTo(PollingPlace) via polling_place_id. PollingPlace has both a direct department_id fillable AND a municipality_id fillable, plus its own municipality() BelongsTo(Municipality). Municipality has department() BelongsTo(Department).
  implication: Ground truth for municipal case = $voter->pollingPlace->municipality_id. Ground truth for departmental case = $voter->pollingPlace->municipality->department_id (per required_investigation instructions, using the municipality relation's department rather than PollingPlace's own department_id column, to stay consistent with how the voter-fallback path already derives department via municipality->department_id).

- timestamp: 2026-08-05T00:00:00Z
  checked: JurisdictionReportTable.php, JurisdictionSummaryOverview.php, JurisdictionExport.php, ReconcileVoterTerritory.php + their tests
  found: All three widgets/export and the job call VoterTerritoryScope::isWithinCampaignScope()/isTerritoryDefined() — none read voter.polling_place fields directly, all delegate fully to the shared service. Existing tests for all of these construct voters WITHOUT polling_place_id set, so fixing the service will not break any existing test (fallback path preserved, same as before).
  implication: Single fix point in VoterTerritoryScope.php + new tests in VoterTerritoryScopeTest.php is sufficient; no changes needed to the widgets/export/job files themselves.

## Resolution

root_cause: VoterTerritoryScope::isWithinCampaignScope() never considers $voter->polling_place_id / $voter->pollingPlace, comparing campaign scope only against the voter's own municipality_id — a field defaulted to the campaign's own municipality at voter-creation time and never updated afterward when the real polling place resolves elsewhere.
fix: Updated isWithinCampaignScope() to prefer $voter->pollingPlace->municipality_id (municipal case) / $voter->pollingPlace->municipality?->department_id (departmental case) as ground truth when $voter->polling_place_id is set and the relation resolves, falling back to voter->municipality_id / voter->municipality?->department_id otherwise.
verification: Ran full affected Pest suite (VoterTerritoryScopeTest 15 tests, JurisdictionReportTableTest 3, JurisdictionSummaryOverviewTest 4, ReconcileVoterTerritoryTest 14, RejectionsReportTableTest 2, VoterValidationServiceTest 14, WidgetDrillThroughTest 1) — 51 tests / 101 assertions, all passing. `vendor/bin/pint --dirty` clean (no style changes needed on the 2 touched files). No JurisdictionExport-specific test file exists in the repo (confirmed via grep) — export delegates fully to the same fixed service, no separate test to update. Real production verification (running `census:reconcile-territory` against sigma-betha's 16 known-affected voters) deferred to user per task constraint (no DB access in this environment).
files_changed:
  - app/Services/VoterTerritoryScope.php
  - tests/Feature/Services/VoterTerritoryScopeTest.php

## Addendum: production verification (2026-08-06)

Deployed (commit d53a364) to both instances. Confirmed via direct query on sigma-betha: of 16 voters with `polling_place_id` resolved in a municipality different from their stored `municipality_id`, all 16 now correctly evaluate `isWithinCampaignScope() === false` (verified by calling the service directly against each). Ran `census:reconcile-territory` repeatedly (random-sampling job, ~32 runs) plus a direct one-time application of the already-verified logic to the remaining stragglers to avoid waiting on random-sampling luck — final count: 18 voters correctly marked `REJECTED_OUT_OF_SCOPE` on sigma-betha (the 16 identified mismatches + 2 additional genuinely-out-of-scope voters already caught by earlier random runs). Product owner confirmed visually in the browser afterward.

**Follow-on regression caught and fixed separately** (see `.planning/debug/resolved/voter-status-match-missing-cases.md`): marking these voters surfaced two non-exhaustive `match($voter->status)` blocks (`ViewVoter::nextStepGuidance()`, `VotersTable`'s status badge color) that didn't handle `REJECTED_OUT_OF_SCOPE`, causing live 500 errors when viewing/listing affected voters.

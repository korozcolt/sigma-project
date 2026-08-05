---
status: awaiting_human_verify
trigger: "polling-place-id-not-persisted-by-resolver"
created: 2026-08-05T00:00:00Z
updated: 2026-08-05T00:00:00Z
---

## Current Focus

hypothesis: CONFIRMED - see Resolution below.
test: Fix applied and self-verified via new + existing Pest coverage. Full affected suite green.
expecting: n/a - awaiting user confirmation before archiving.
next_action: user runs the new census:backfill-polling-place-id --dry-run against sigma-betha to confirm the ~507 affected voters get correctly re-linked, then confirms.

## Symptoms

expected: A voter whose polling place has been genuinely resolved (polling_place_source is non-null, e.g. LIVE/SNAPSHOT/DB_RECONSTRUCTION) should have polling_place_id populated too, so the "sin puesto de votacion asignado" widget count and the "Ranking de Puestos de Votacion" table (which counts via the PollingPlace::voters() hasMany relation, i.e. depends on polling_place_id) both reflect reality.
actual: PollingPlaceResolver::persist() (app/Services/PollingPlaceResolver.php:211-245) only writes polling_place_source and polling_place_resolved_at to the Voter - it NEVER writes polling_place_id, even though the PollingPlaceResolutionResult it receives already carries a computed pollingPlaceId (via resolveOrCreatePollingPlace(), lines 371-440) whenever resolution succeeded. This method is the sole write path used by the ENTIRE automated cascade (resolveAutomated(), lines 319-355, called by ReconcileFallbackPollingPlaces::handle() and by VoterValidationService::validateAgainstCensus()/DispatchCensusRevalidation) - i.e. the highest-volume resolution path never links the FK. Only the interactive admin Filament flow (HasRegistraduriaPolling::fillPollingPlaceFields(), ~lines 325-340) ends up with polling_place_id set, because it assigns $this->data['polling_place_id'] directly in the Livewire form before the normal form submit - it never goes through persist() for that field.
errors: None - silent data-completeness bug, not an exception.
reproduction: Any voter resolved via the automated jobs (census:reconcile-live, census:reconcile-validation) ends up with polling_place_source set but polling_place_id staying NULL forever, unless later manually re-resolved through the interactive admin form.
started: Structural - present since persist()/resolveAutomated() were introduced.

## Eliminated

(none yet)

## Evidence

- timestamp: 2026-08-05T00:00:00Z
  checked: app/Services/PollingPlaceResolver.php full file (persist(), resolveAutomated(), resolveFromPermanentLookup(), resolveFromNationalSnapshot(), resolveFromCampaignCensus(), resolveOrCreatePollingPlace())
  found: All four resolve* methods already compute and attach pollingPlaceId to the PollingPlaceResolutionResult they construct. persist() (lines 227-230) only ever writes polling_place_source and polling_place_resolved_at in its $voter->update() call. resolveOrCreatePollingPlace() can return null (e.g. municipality not found, or blank name after failing DIVIPOLE-code match) - so pollingPlaceId on a result CAN legitimately be null even when source resolution succeeded.
  implication: Fix must add polling_place_id to persist()'s update call, but must NOT unconditionally include it (would null out a previously-good FK on a later re-resolution attempt where resolveOrCreatePollingPlace momentarily returns null, e.g. municipality data changed). Must only set it when $result->pollingPlaceId is non-null.

- timestamp: 2026-08-05T00:00:00Z
  checked: app/Console/Commands/BackfillLiveStatusDesync.php + its Pest test (tests/Feature/Console/BackfillLiveStatusDesyncTest.php)
  found: Established convention for resolver-free backfill commands - {--dry-run} option, breakdown reporting via countBy, DB::transaction wrapping the real write loop, and a dedicated Mockery-based test asserting the resolver/live-adapter methods are never invoked (shouldNotReceive on resolveAutomated, resolveFromPermanentLookup, resolveFromNationalSnapshot, resolveFromCampaignCensus, startLiveLookup, isLiveReachable).
  implication: New backfill command (census:backfill-polling-place-id or similar) should mirror this shape exactly - resolveOrCreatePollingPlace() itself is a pure local-DB read/write (matches/creates PollingPlace rows only, no live adapter calls), so it is safe to call directly from the backfill command, but resolveAutomated()/resolveFromPermanentLookup()/startLiveLookup()/isLiveReachable() must never be invoked.

- timestamp: 2026-08-05T00:00:00Z
  checked: app/Filament/Resources/Voters/Concerns/HasRegistraduriaPolling.php lines 253-340 (applyResolvedFields, fillPollingPlaceFields)
  found: The interactive admin flow sets $this->data['polling_place_id'] = $pollingPlace->id directly inside fillPollingPlaceFields() (form data bag), which then gets saved by Filament's normal EditRecord/CreateRecord save lifecycle - it never goes through persist() for that specific field. This is why only the interactive flow ends up with polling_place_id populated in practice.
  implication: Confirms the entire automated cascade (resolveAutomated -> persist) is the sole path missing this write. No change needed to HasRegistraduriaPolling.php.

## Resolution

root_cause: PollingPlaceResolver::persist() (app/Services/PollingPlaceResolver.php) never included polling_place_id in its $voter->update() call, despite every resolve*() method already computing it on the PollingPlaceResolutionResult (via resolveOrCreatePollingPlace()). This is the sole write path for the entire automated cascade (resolveAutomated(), used by ReconcileFallbackPollingPlaces and VoterValidationService), so polling_place_id silently stayed NULL for every automatically-resolved voter, undercounting the "Ranking de Puestos de Votación" widget and inflating the "sin puesto de votación asignado" count by roughly 507 voters on sigma-betha.
fix: Added polling_place_id to persist()'s $voter->update() call, but ONLY when $result->pollingPlaceId is non-null - deliberately never clearing an existing polling_place_id when a later resolution's pollingPlaceId comes back null (e.g. resolveOrCreatePollingPlace() couldn't match a municipality at re-resolution time), since that would silently downgrade a previously-good FK. This write happens inside the same $voter->update() call already gated by the existing no-downgrade source guard, so a blocked downgrade blocks polling_place_id too; a no-op source reconfirmation (e.g. re-running LIVE -> LIVE) still passes the guard and now correctly backfills a previously-missing FK. Added a new resolver-free `census:backfill-polling-place-id` Artisan command (mirrors BackfillLiveStatusDesync's pattern) to fix already-affected historical voters: for each voter with polling_place_source set but polling_place_id null, it re-derives the FK using ONLY the one local-read-only resolve*() method matching that voter's existing source (LIVE -> resolveFromPermanentLookup, SNAPSHOT -> resolveFromNationalSnapshot, DB_RECONSTRUCTION -> resolveFromCampaignCensus; MANUAL is skipped, no local re-derivation possible), supports --dry-run, and never calls resolveAutomated()/startLiveLookup()/isLiveReachable() (asserted by a Mockery-based test).
verification: New Pest coverage added to tests/Feature/Services/PollingPlaceResolverTest.php (4 new tests: fresh-voter write, re-resolution to a different PollingPlace, no-null-overwrite guard, no-op-reconfirmation backfill) - all pass. New tests/Feature/Console/BackfillPollingPlaceIdTest.php (9 tests covering all 3 source tiers, already-set skip, null-source skip, MANUAL skip, unmatched-LIVE skip, dry-run no-write, and a live-adapter-never-invoked assertion) - all pass. Full affected suite re-run (PollingPlaceResolverTest, PollingPlaceResolverPriorityTest, ReconcileFallbackPollingPlacesTest, RevalidationCoverageTest, BackfillLiveStatusDesyncTest, RegisterVoterRegistraduriaLookupTest, BackfillPollingPlaceIdTest) - 51 tests, all pass. Full project suite run twice (with and without the fix, via git stash) confirms the ~13-15 pre-existing failures (DuplicatesReportTableTest, JurisdictionReportTableTest, JurisdictionSummaryOverviewTest, RejectionsReportTableTest, TopCoordinatorsTableTest, TopPollingPlacesTableTest, UserResourceTest, VoterResourceTest, IsElectionDayMiddlewareTest) are pre-existing full-suite-order flakiness unrelated to this change - each passes individually in isolation. vendor/bin/pint --dirty clean on all 4 changed/new files. Backfill command intentionally NOT run against any real database in this environment per scope boundaries - left to the user.
files_changed: [app/Services/PollingPlaceResolver.php, app/Console/Commands/BackfillPollingPlaceId.php, tests/Feature/Services/PollingPlaceResolverTest.php, tests/Feature/Console/BackfillPollingPlaceIdTest.php]

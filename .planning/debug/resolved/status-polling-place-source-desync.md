---
status: resolved
trigger: "status-polling-place-source-desync (root cause confirmed by separate investigation, supersedes estado-badge-visual-misalign-voters-table)"
created: 2026-08-05T23:00:00Z
updated: 2026-08-05T23:45:00Z
---

## Current Focus
<!-- OVERWRITE on each update - reflects NOW -->

hypothesis: CONFIRMED — Voter.status and Voter.polling_place_source are two independent state machines updated by two separate, uncoordinated hourly cron jobs (census:reconcile-live and census:reconcile-validation), causing a voter to sit with status = PENDING_REVIEW (or other non-terminal status) while polling_place_source = LIVE for hours/days. A second, inverse desync exists in the manual voter-creation flow (register-voter.blade.php), where status can be set to VERIFIED_REGISTRADURIA without polling_place_source being set in the same write.
test: Fixed both desync points and added Pest regression coverage; ran full affected test suites.
expecting: Both desync points closed with zero new paid Registraduria/live lookups, plus a safe, resolver-free backfill command for historical data.
next_action: DONE — fix implemented and verified by Pest; user must run the backfill command manually against real data (no DB access in this environment) and confirm in a real browser per project convention (browser-verify before prod).

## Symptoms
<!-- Written during gathering, then IMMUTABLE -->

expected: A voter's `status` badge and `polling_place_source` badge should never be contradictory — specifically, a voter whose polling place was resolved via a genuine live Registraduría lookup (`polling_place_source = LIVE`) should not still be sitting in an unreviewed/pending `status` (e.g. PENDING_REVIEW).
actual: Product owner spotted, in a real screenshot of the Apoyos/Voters table, rows showing a "Pendiente de Revisión" status badge next to an "En Vivo" (LIVE) polling-place-source badge for the same voter — a contradictory combination per business rules.
errors: None (no exception/stacktrace — a silent data-consistency bug, not a rendering bug).
reproduction: Any voter whose `polling_place_source` was upgraded to LIVE by the `census:reconcile-live` hourly job (`ReconcileFallbackPollingPlaces`) before the separate `census:reconcile-validation` hourly job (`DispatchCensusRevalidation` / `VoterValidationService::validateAndUpdate()`) got around to also updating that voter's `status` — under backlog (each job caps at ~50 voters/run), this gap can persist for hours or days. Also reproducible immediately via the manual "Registrar Apoyo" flow (`resources/views/livewire/leader/register-voter.blade.php`): a cédula found in `RegistraduriaLookup` sets `status = VERIFIED_REGISTRADURIA` on `Voter::create()` but historically left `polling_place_source` unset (null) in that same call.
started: Structural — present since `ReconcileFallbackPollingPlaces` and `census:reconcile-validation` were introduced as two independent jobs with no shared ordering/coordination (see `routes/console.php:17,20`).

## Eliminated
<!-- APPEND only - prevents re-investigating -->

- hypothesis: Visual/CSS bug — badges rendering stacked/misaligned in the Filament table due to layout, hasMany relation notation, recent CSS changes, row entry animation, or wire:poll re-renders.
  evidence: Full elimination work from the original (wrongly-framed) session `estado-badge-visual-misalign-voters-table.md` — Playwright verification across 5 viewport widths against the real Vite production build found no CSS/rendering defect. This ruled OUT rendering as the cause and (via the persisting product-owner report) redirected investigation toward the badges' underlying VALUES instead of their rendering.
  timestamp: 2026-08-05 (prior session)

## Evidence
<!-- APPEND only - facts discovered -->

- timestamp: 2026-08-05
  checked: routes/console.php, app/Jobs/ReconcileFallbackPollingPlaces.php, app/Jobs/DispatchCensusRevalidation.php (via VoterValidationService)
  found: census:reconcile-live and census:reconcile-validation are both Schedule::command(...)->hourly()->withoutOverlapping(10) — two fully independent jobs with no shared run, no shared ordering, and no signal between them. ReconcileFallbackPollingPlaces::handle() calls PollingPlaceResolver::resolveAutomated() and, on a LIVE result, previously only reset reconciliation_attempts/reconciliation_exhausted_at — it never touched `status`.
  implication: Any voter upgraded to polling_place_source = LIVE by this job keeps whatever status it already had until the separate validation job (capped at 50/run) happens to reach it — an unbounded window under backlog.

- timestamp: 2026-08-05
  checked: app/Services/VoterValidationService.php (updateVoterStatus(), NON_DOWNGRADABLE_STATUSES)
  found: updateVoterStatus(Voter $voter, bool $found) already safely no-ops for voters in VERIFIED_REGISTRADURIA, VERIFIED_CALL, CONFIRMED, VOTED, or DID_NOT_VOTE (never downgrades a stronger post-verification/Day-D state), and otherwise sets VERIFIED_CENSUS (found) or CENSUS_NOT_FOUND (not found) plus a ValidationHistory audit row. It does NOT call the resolver/live adapters at all — only validateAgainstCensus()/validateAndUpdate() do that.
  implication: updateVoterStatus() is safe to call broadly from any already-LIVE-confirming code path (the reconciliation job's LIVE branch, and a pure-local backfill command) with zero new external/paid calls and zero downgrade risk.

- timestamp: 2026-08-05
  checked: app/Services/PollingPlaceResolver.php (resolveAutomated(), resolveFromPermanentLookup(), attemptLiveAutomated())
  found: resolveAutomated() ALWAYS tries the free, local resolveFromPermanentLookup() (a DB read against registraduria_lookups) before ever touching a live/2captcha adapter. Every polling_place_source = LIVE voter got there via a path that also wrote a permanent-lookup row.
  implication: Confirms the forward-looking fix in ReconcileFallbackPollingPlaces (calling updateVoterStatus() on an already-fetched LIVE $result) adds zero new paid lookups — it reuses data the resolver already fetched in that same run.

- timestamp: 2026-08-05
  checked: resources/views/livewire/leader/register-voter.blade.php save()
  found: $foundInRegistraduria (a pure RegistraduriaLookup::exists() check, no live call) drives status = VERIFIED_REGISTRADURIA in the match(true) block, but the accompanying Voter::create([...]) never set polling_place_source or polling_place_resolved_at — they defaulted to null until a later cron job filled them in.
  implication: A second, independent desync point confirmed and fixed alongside the cron-job one, using data already available in that same request (no new lookup).

## Resolution
<!-- OVERWRITE as understanding evolves -->

root_cause: Voter.status and Voter.polling_place_source are two independent state machines updated by two separate, uncoordinated hourly cron jobs (census:reconcile-live -> ReconcileFallbackPollingPlaces, and census:reconcile-validation -> DispatchCensusRevalidation/VoterValidationService::validateAndUpdate()). A voter upgraded to polling_place_source = LIVE by the first job could sit with a stale non-terminal status (e.g. PENDING_REVIEW) for hours/days until the second, separately-capped job happened to reach it. A second, inverse desync existed in the manual voter-creation flow (register-voter.blade.php), which set status = VERIFIED_REGISTRADURIA without setting polling_place_source in the same Voter::create() call.

fix: (1) ReconcileFallbackPollingPlaces::handle() now injects VoterValidationService and, inside the existing `$result->source === PollingPlaceSource::LIVE` branch, calls `$validationService->updateVoterStatus($voter, found: true)` to sync `status` in the same pass — reusing the already-fetched/already-paid-for LIVE result, adding zero new resolver/live-adapter calls. (2) register-voter.blade.php's Voter::create() now also sets `polling_place_source` (LIVE) and `polling_place_resolved_at` whenever `$foundInRegistraduria` is true, reusing the already-checked RegistraduriaLookup existence flag — no new lookup. (3) A new Artisan command, `census:backfill-live-status-desync` (App\Console\Commands\BackfillLiveStatusDesync), was added to remediate already-affected historical voters: it selects voters where polling_place_source = LIVE and calls VoterValidationService::updateVoterStatus($voter, found: true) directly for each — this method only writes to voters/validation_history, never touches the resolver or any live adapter. Supports --dry-run (reports a status breakdown without writing). A dedicated Pest test asserts the resolver is NEVER invoked by this command (Mockery::mock(PollingPlaceResolver::class) with shouldNotReceive on every method), in both normal and dry-run modes.

verification: Pest test suites passed for all three changes: tests/Feature/Jobs/ReconcileFallbackPollingPlacesTest.php (existing 9 tests + 2 new: status syncs to VERIFIED_CENSUS on LIVE upgrade, and the NON_DOWNGRADABLE_STATUSES guard still protects a CONFIRMED voter from being downgraded), tests/Feature/Leader/RegisterVoterRegistraduriaLookupTest.php (existing tests + 1 new asserting polling_place_source = LIVE is persisted alongside VERIFIED_REGISTRADURIA, plus a regression assertion that the PENDING_REVIEW path still leaves polling_place_source null), and the new tests/Feature/Console/BackfillLiveStatusDesyncTest.php (5 tests: syncs a desynced voter, ignores non-LIVE-source voters, respects the non-downgradable guard, dry-run writes nothing, and the resolver is never invoked in either mode). Also updated 4 pre-existing call sites in tests/Feature/RevalidationCoverageTest.php that called ReconcileFallbackPollingPlaces::handle() directly with only one argument (now requires the injected VoterValidationService too) — all 9 tests in that file still pass. Ran the full affected-area suite (36 tests, 106 assertions) and the broader Voter/Filament/Services suites — all green. A handful of unrelated Filament report-table tests (Duplicates/Jurisdiction/Rejections/TopCoordinators/TopPollingPlaces/UserResource) fail intermittently only under the FULL test-suite run and pass cleanly in isolation and on a clean git stash of this change — confirmed as pre-existing full-suite test-order flakiness, unrelated to this fix. vendor/bin/pint --dirty run clean (6 files reformatted, no logic changes). NOT run against real/production data — no DB access in this environment; running the backfill command against sigma_betha_backup/production is left to the user, as instructed.

files_changed:
  - app/Jobs/ReconcileFallbackPollingPlaces.php (injects VoterValidationService, syncs status on LIVE upgrade)
  - resources/views/livewire/leader/register-voter.blade.php (sets polling_place_source/polling_place_resolved_at alongside VERIFIED_REGISTRADURIA on manual creation)
  - app/Console/Commands/BackfillLiveStatusDesync.php (new backfill command, resolver-free)
  - tests/Feature/Jobs/ReconcileFallbackPollingPlacesTest.php (updated handle() call sites + 2 new tests)
  - tests/Feature/Leader/RegisterVoterRegistraduriaLookupTest.php (1 new test + regression assertion)
  - tests/Feature/Console/BackfillLiveStatusDesyncTest.php (new, 5 tests)
  - tests/Feature/RevalidationCoverageTest.php (updated 4 handle() call sites for the new method signature)

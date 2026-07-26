# Deferred Items — Quick Task 260726-jao

## Environment fix applied (not a code deviation)

`tests/Feature/Leader/LeaderAppTest.php` initially failed all 6 tests with
`Illuminate\View\ViewException: Vite manifest not found at: public/build/manifest.json`.
Root cause: this execution worktree (`agent-a2b66af830c2ca5b4`) was stale at session start
(missing `vendor/`, `.env`, behind `main`) and has never had `npm install`/`npm run build`
run in it. Resolved by copying the already-built, gitignored `public/build/` directory from
the main checkout (same precedent as quick task 260726-ifp's SUMMARY.md deviation #2) — no
source files changed, nothing committed (gitignored). Re-ran `LeaderAppTest` after the copy:
30/30 pass.

## Pre-existing flaky test (out of scope, not caused by this task)

`tests/Feature/Filament/UserResourceTest.php > can update user campaigns` fails
intermittently (~1/3 of full-suite runs, per `.planning/STATE.md`'s Blockers/Concerns
section, originally logged in `04.1 deferred-items.md`) with "Component has errors:
data.phone". Confirmed still present and unrelated to this task: it fails when run as part
of the broader `tests/Feature/Filament` sweep but passes 28/28 every time when run in
isolation (`php artisan test --filter=UserResourceTest`). Neither this task's plan nor any
file it touches (`VoterStatus`, `VotersTable`, `PollingPlaceResolver`, `HasRegistraduriaPolling`,
`register-voter.blade.php`, `create-leader.blade.php`) is involved. Not fixed here per the
SCOPE BOUNDARY rule.

## Verification summary

Full targeted sweep (`tests/Feature/Filament`, `tests/Feature/Leader`, `tests/Feature/Coordinator`,
`tests/Feature/Services`, `tests/Feature/Jobs`, `tests/Feature/VoterTest.php`,
`tests/Feature/CreateLeaderOtpTest.php`): 300 passed, 1 failed (the pre-existing flake above),
966 assertions. The plan's own named verification filters (PollingPlaceResolverTest,
PollingPlaceResolverPriorityTest, VoterResourceTest, VoterRegistraduriaRefreshTest,
RegisterVoterRegistraduriaLookupTest, RegisterVoterCensusWarningTest,
CreateLeaderRegistraduriaLookupTest, CreateLeaderOtpTest, ReconcileFallbackPollingPlacesTest)
all pass: 114/114, 412 assertions.

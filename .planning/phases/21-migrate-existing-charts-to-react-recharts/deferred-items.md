# Deferred Items — Phase 21

## Pre-existing `CampaignContext` full-suite test pollution (out of scope)

**Found during:** Plan 21-07, Task 1/2 regression run (`php artisan test`, full suite).

**Symptom:** `php artisan test` (all suites) fails 1-3 tests per run, with a *different* failing set each run (observed: `Tests\Feature\IsElectionDayMiddlewareTest`, `Tests\Feature\Filament\UserResourceTest`, `Tests\Feature\DashboardWidgetsTest`, `Tests\Feature\Services\PollingPlaceResolverTest` across 3 consecutive runs). Every failing test passes 100% in isolation.

**Root cause:** Already documented in `.planning/PROJECT.md`'s "Post-v1.2 state" note as a known, non-blocking tech debt item: "a pre-existing `CampaignContext` static-override test-pollution issue that produces a non-deterministic failure set on full-suite runs (100% pass rate in isolation, unrelated to any specific feature)".

**Verification this is not caused by Plan 21-07:** Ran `php artisan test` against the working tree with this plan's Task 1/2 changes `git stash`-ed (i.e. pre-PoC-removal code) — the exact same failure class (`IsElectionDayMiddlewareTest`, different-tests-fail-each-run) reproduced with zero code changes from this plan. Confirmed pre-existing and out of this plan's scope per the Deviation Rules "Scope Boundary" (pre-existing failures in unrelated files are out of scope).

**Status:** Not fixed (root-causing `CampaignContext`'s static-override test isolation is a cross-cutting test-infra fix unrelated to Phase 21's chart migration scope). Left as-is, consistent with `.planning/PROJECT.md`'s existing tracking of this same issue.

**Mitigating evidence for this plan's own regression:** All 14 `tests/Browser/*.php` tests pass (including all 6 chart-widget Browser tests: `ValidationProgressChartTest`, `TerritorialDistributionChartTest`, `SurveyResultsWidgetTest`, `CampaignVotersSparklineWidgetTest`, `SurveyResponsesSparklineWidgetTest`, `CallCenterCallsSparklineWidgetTest`), and re-running the full suite's `Feature` tests filtered to just the intermittently-failing test names in isolation also passes cleanly.

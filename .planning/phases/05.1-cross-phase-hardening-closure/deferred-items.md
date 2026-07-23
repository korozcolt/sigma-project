# Deferred Items — Phase 05.1

## Pre-existing full-suite test flakiness (out of scope for plan 05.1-06)

Observed while running `composer test` (full suite, 838 tests) during execution of plan 05.1-06:

- Run 1: `Tests\Feature\Filament\UserResourceTest > can search users by name` failed (assertion about a `wire:key` row no longer present after a Livewire table re-render); passed cleanly when re-run in isolation.
- Run 2 (immediately after): a different test, `Tests\Feature\IsElectionDayMiddlewareTest > it allows access when there is an active event today`, failed instead; both runs otherwise green (837/838).

Neither failing test touches the widgets modified by this plan (`TopLeadersTable`, `CampaignStatsOverview`, `TerritorialDistributionChart`, `ValidationProgressChart`). This matches the flake already logged in `.planning/STATE.md` for Phase 04.1 (`UserResourceTest > can update user campaigns`, ~1/3 of full-suite runs) — the flake appears to affect different tests in the same run depending on ordering/timing, not a single fixed test. Root cause is likely test-order-dependent state bleed or a time-sensitive assertion, not related to this plan's ownership-scoping changes. Deferred for future investigation outside Phase 05.1 plan 06's scope.

`OwnershipScopedWidgetsTest` and `DashboardWidgetsTest` (the tests directly covering this plan's changes) pass consistently and were not observed to flake.
